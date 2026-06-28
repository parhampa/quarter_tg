<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول حذف پیام از گروه
 * فقط ادمین‌ها می‌توانند پیام را حذف کنند
 * نیاز به ریپلای به پیام مورد نظر دارد
 */
class DeleteModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $messageTable = 'bot_messages';

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

        // فقط ادمین‌ها می‌توانند پیام را حذف کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه حذف پیام را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی وجود ریپلای
        if (!isset($message['reply_to_message']) || !isset($message['reply_to_message']['message_id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ لطفاً روی پیام مورد نظر ریپلای کنید و سپس دستور `/del` را ارسال کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        $targetMessageId = $message['reply_to_message']['message_id'];
        $targetUserId = $message['reply_to_message']['from']['id'] ?? 0;

        // بررسی اینکه کاربر هدف ادمین نباشد (اختیاری - می‌توان حذف کرد)
        if ($targetUserId && $this->authManager->isAdmin($chatId, $targetUserId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ شما نمی‌توانید پیام یک ادمین را حذف کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // انجام عملیات حذف
        try {
            $result = $this->telegram->deleteMessage($chatId, $targetMessageId);

            if ($result && isset($result['result']) && $result['result'] === true) {
                // حذف از دیتابیس (اختیاری)
                $this->removeFromDatabase($chatId, $targetMessageId);

                // ارسال پیام تایید (که بعد از چند ثانیه خودش حذف می‌شود)
                $confirmMessage = $this->telegram->sendMessage(
                    $chatId,
                    "✅ پیام با موفقیت حذف شد.",
                    $message['message_id'] ?? null
                );

                // حذف پیام تایید بعد از ۵ ثانیه
                if ($confirmMessage && isset($confirmMessage['result']['message_id'])) {
                    $confirmId = $confirmMessage['result']['message_id'];
                    sleep(3);
                    $this->telegram->deleteMessage($chatId, $confirmId);
                }

                $this->logger->info("Message $targetMessageId deleted in group $chatId by $userId");
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ حذف پیام با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to delete message $targetMessageId in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // بررسی خطاهای خاص
            if (strpos($errorMessage, 'message_not_found') !== false) {
                $errorMessage = "پیام مورد نظر یافت نشد. ممکن است قبلاً حذف شده باشد.";
            } elseif (strpos($errorMessage, 'not enough rights') !== false) {
                $errorMessage = "ربات دسترسی کافی برای حذف پیام ندارد. لطفاً ربات را ادمین کنید.";
            } elseif (strpos($errorMessage, 'CHAT_ADMIN_REQUIRED') !== false) {
                $errorMessage = "ربات باید ادمین گروه باشد تا بتواند پیام را حذف کند.";
            } elseif (strpos($errorMessage, 'message can\'t be deleted') !== false) {
                $errorMessage = "این پیام قابل حذف نیست (ممکن است پیام قدیمی یا از نوع خاص باشد).";
            }

            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در حذف پیام: " . $errorMessage,
                $message['message_id'] ?? null
            );

            $this->logger->error("Delete exception: " . $e->getMessage());
        }
    }

    /**
     * حذف پیام از دیتابیس
     */
    private function removeFromDatabase(int $groupId, int $messageId): void
    {
        $sql = "DELETE FROM {$this->messageTable} WHERE group_id = ? AND message_id = ?";
        $this->db->execute($sql, [$groupId, $messageId]);
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "حذف پیام از گروه (نیاز به ریپلای) / Delete message from group (requires reply)";
    }
}