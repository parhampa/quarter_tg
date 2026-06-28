<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\WarningManager;

/**
 * ماژول حذف تمام اخطارهای یک کاربر
 * فقط ادمین‌ها می‌توانند اخطارها را حذف کنند
 */
class RemoveWarningModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $warningManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        WarningManager $warningManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->warningManager = $warningManager;
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

        // فقط ادمین‌ها می‌توانند اخطارها را حذف کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه حذف اخطارها را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // استخراج کاربر هدف
        $targetUser = $this->extractTargetUser($message, $params);
        if (!$targetUser) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ لطفاً یک کاربر را مشخص کنید.\n"
                . "مثال: `/remwarning @username`",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر اخطار دارد یا خیر
        if (!$this->warningManager->hasWarnings($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} هیچ اخطاری ندارد.",
                $message['message_id'] ?? null
            );
            return;
        }

        // دریافت تعداد اخطارهای فعلی
        $currentCount = $this->warningManager->getWarningCount($chatId, $targetUser['id']);

        // حذف اخطارها
        try {
            $result = $this->warningManager->clearWarnings($chatId, $targetUser['id']);

            if ($result) {
                $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
                $this->telegram->sendMessage(
                    $chatId,
                    "✅ تمام اخطارهای کاربر {$username} (تعداد: {$currentCount}) با موفقیت حذف شد.",
                    $message['message_id'] ?? null
                );

                $this->logger->info("All warnings removed for user {$targetUser['id']} in group $chatId by $userId", [
                    'count' => $currentCount,
                ]);
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ حذف اخطارها با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to remove warnings for user {$targetUser['id']} in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در حذف اخطارها: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Remove warning exception: " . $e->getMessage());
        }
    }

    /**
     * استخراج کاربر هدف از پیام یا پارامترها
     * @return array|null ['id' => int, 'username' => string|null, 'first_name' => string|null, 'last_name' => string|null]
     */
    private function extractTargetUser(array $message, string $params): ?array
    {
        // 1. بررسی پارامترها
        if (!empty($params)) {
            $usernameOrId = trim($params);
            
            if (is_numeric($usernameOrId)) {
                return [
                    'id' => (int)$usernameOrId,
                    'username' => null,
                    'first_name' => null,
                    'last_name' => null,
                ];
            }
            
            if (strpos($usernameOrId, '@') === 0) {
                try {
                    $chatMember = $this->telegram->getChatMember($message['chat']['id'], $usernameOrId);
                    if ($chatMember && isset($chatMember['user'])) {
                        return [
                            'id' => $chatMember['user']['id'],
                            'username' => $chatMember['user']['username'] ?? null,
                            'first_name' => $chatMember['user']['first_name'] ?? null,
                            'last_name' => $chatMember['user']['last_name'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    // اگر کاربر در گروه نیست، اطلاعات را از دیتابیس می‌گیریم
                    $userInfo = $this->getUserFromWarnings($message['chat']['id'], $usernameOrId);
                    if ($userInfo) {
                        return $userInfo;
                    }
                }
                return null;
            }
        }

        // 2. بررسی ریپلای
        if (isset($message['reply_to_message']) && isset($message['reply_to_message']['from'])) {
            $from = $message['reply_to_message']['from'];
            return [
                'id' => $from['id'],
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
            ];
        }

        return null;
    }

    /**
     * دریافت اطلاعات کاربر از جدول اخطارها (در صورت عدم حضور در گروه)
     */
    private function getUserFromWarnings(int $groupId, string $username): ?array
    {
        // حذف @ از ابتدا
        $cleanUsername = ltrim($username, '@');
        
        $sql = "SELECT user_id, username, first_name, last_name 
                FROM bot_warnings 
                WHERE group_id = ? AND username = ? 
                LIMIT 1";
        $result = $this->db->queryRow($sql, [$groupId, $cleanUsername]);
        
        if ($result) {
            return [
                'id' => $result['user_id'],
                'username' => $result['username'] ?? null,
                'first_name' => $result['first_name'] ?? null,
                'last_name' => $result['last_name'] ?? null,
            ];
        }
        
        return null;
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "حذف تمام اخطارهای کاربر / Remove all warnings for user";
    }
}