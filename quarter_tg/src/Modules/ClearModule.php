<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول پاکسازی پیام‌های گروه
 * فقط ادمین‌ها می‌توانند پاکسازی کنند
 * حداکثر ۵۰۰۰ پیام با کول‌داون ۲۴ ساعته
 */
class ClearModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $messageTable = 'bot_messages';
    private $maxMessages = 5000;
    private $cooldownSeconds = 86400; // 24 ساعت

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
    }

    /**
     * اجرای ماژول
     */
    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // فقط ادمین‌ها می‌توانند پاکسازی کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه پاکسازی گروه را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی کول‌داون
        $cooldownCheck = $this->checkCooldown($chatId, $userId);
        if (!$cooldownCheck['allowed']) {
            $remaining = $this->formatRemainingTime($cooldownCheck['remaining']);
            $this->telegram->sendMessage(
                $chatId,
                "⏳ لطفاً صبر کنید.\n"
                . "مدت زمان باقی‌مانده تا پاکسازی بعدی: <b>{$remaining}</b>",
                $message['message_id'] ?? null,
                'HTML'
            );
            return;
        }

        // تعیین تعداد پیام‌های قابل پاکسازی
        $count = $this->maxMessages;
        if (!empty($params) && is_numeric($params)) {
            $requested = (int)$params;
            $count = min($requested, $this->maxMessages);
            if ($requested > $this->maxMessages) {
                $this->telegram->sendMessage(
                    $chatId,
                    "⚠️ حداکثر تعداد قابل پاکسازی {$this->maxMessages} پیام است. تعداد {$this->maxMessages} پیام حذف می‌شود.",
                    $message['message_id'] ?? null
                );
            }
        }

        // ارسال پیام شروع
        $statusMessage = $this->telegram->sendMessage(
            $chatId,
            "🔄 در حال پاکسازی <b>{$count}</b> پیام آخر گروه...\n⏳ لطفاً منتظر بمانید.",
            $message['message_id'] ?? null,
            'HTML'
        );

        try {
            $deleted = $this->clearMessages($chatId, $count);

            // حذف پیام وضعیت
            if ($statusMessage && isset($statusMessage['result']['message_id'])) {
                $this->telegram->deleteMessage($chatId, $statusMessage['result']['message_id']);
            }

            // ارسال پیام نتیجه
            $resultText = "✅ پاکسازی با موفقیت انجام شد.\n"
                        . "📊 تعداد پیام‌های حذف‌شده: <b>{$deleted}</b>";

            if ($deleted < $count) {
                $resultText .= "\n⚠️ تنها {$deleted} پیام قابل حذف بود (ممکن است برخی پیام‌ها قدیمی یا غیرقابل حذف باشند).";
            }

            $this->telegram->sendMessage(
                $chatId,
                $resultText,
                null,
                'HTML'
            );

            // ثبت کول‌داون
            $this->saveCooldown($chatId, $userId);

            $this->logger->info("Cleared $deleted messages in group $chatId by $userId", [
                'requested' => $count,
            ]);

        } catch (\Exception $e) {
            // حذف پیام وضعیت
            if ($statusMessage && isset($statusMessage['result']['message_id'])) {
                $this->telegram->deleteMessage($chatId, $statusMessage['result']['message_id']);
            }

            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در پاکسازی پیام‌ها: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Clear exception: " . $e->getMessage());
        }
    }

    /**
     * پاکسازی پیام‌های گروه
     * @return int تعداد پیام‌های حذف‌شده
     */
    private function clearMessages(int $groupId, int $count): int
    {
        // دریافت آخرین پیام‌های گروه از دیتابیس
        $sql = "SELECT message_id FROM {$this->messageTable} 
                WHERE group_id = ? 
                ORDER BY sent_at DESC 
                LIMIT ?";
        $messages = $this->db->query($sql, [$groupId, $count]);

        $deleted = 0;
        foreach ($messages as $msg) {
            try {
                $result = $this->telegram->deleteMessage($groupId, $msg['message_id']);
                if ($result && isset($result['result']) && $result['result'] === true) {
                    $deleted++;
                    // حذف از دیتابیس
                    $this->db->execute(
                        "DELETE FROM {$this->messageTable} WHERE group_id = ? AND message_id = ?",
                        [$groupId, $msg['message_id']]
                    );
                }
            } catch (\Exception $e) {
                // اگر پیام قابل حذف نبود، ادامه می‌دهیم
                $this->logger->debug("Could not delete message {$msg['message_id']}: " . $e->getMessage());
            }
        }

        return $deleted;
    }

    /**
     * بررسی کول‌داون
     * @return array ['allowed' => bool, 'remaining' => int]
     */
    private function checkCooldown(int $groupId, int $userId): array
    {
        $sql = "SELECT last_clear FROM bot_clear_cooldown 
                WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $userId]);

        if (!$result) {
            return ['allowed' => true, 'remaining' => 0];
        }

        $lastClear = strtotime($result['last_clear']);
        $elapsed = time() - $lastClear;
        $remaining = $this->cooldownSeconds - $elapsed;

        if ($remaining <= 0) {
            // حذف رکورد منقضی شده
            $this->db->execute(
                "DELETE FROM bot_clear_cooldown WHERE group_id = ? AND user_id = ?",
                [$groupId, $userId]
            );
            return ['allowed' => true, 'remaining' => 0];
        }

        return ['allowed' => false, 'remaining' => $remaining];
    }

    /**
     * ذخیره کول‌داون
     */
    private function saveCooldown(int $groupId, int $userId): void
    {
        // حذف رکورد قبلی (اگر وجود داشته باشد)
        $this->db->execute(
            "DELETE FROM bot_clear_cooldown WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        );

        // درج رکورد جدید
        $this->db->insert('bot_clear_cooldown', [
            'group_id' => $groupId,
            'user_id' => $userId,
            'last_clear' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * فرمت کردن زمان باقی‌مانده به صورت خوانا
     */
    private function formatRemainingTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' ثانیه';
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return $minutes . ' دقیقه';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        if ($hours < 24) {
            return $hours . ' ساعت' . ($remainingMinutes > 0 ? " و {$remainingMinutes} دقیقه" : '');
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        return $days . ' روز' . ($remainingHours > 0 ? " و {$remainingHours} ساعت" : '');
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "پاکسازی پیام‌های گروه (حداکثر ۵۰۰۰ پیام، کول‌داون ۲۴س) / Clear group messages";
    }
}