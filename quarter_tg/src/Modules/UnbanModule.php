<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول رفع بن کاربر از گروه
 * فقط ادمین‌ها می‌توانند آن‌بن کنند
 */
class UnbanModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $banTable = 'bot_bans';

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

        // فقط ادمین‌ها می‌توانند آن‌بن کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه آن‌بن کردن کاربران را ندارید.",
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
                . "مثال: `/unban @username` یا ریپلای به پیام کاربر",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر قبلاً بن شده است یا خیر
        if (!$this->isUserBanned($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} در لیست بن‌ها وجود ندارد.",
                $message['message_id'] ?? null
            );
            return;
        }

        // انجام عملیات آن‌بن
        try {
            $result = $this->telegram->unbanChatMember($chatId, $targetUser['id']);

            if ($result && isset($result['result']) && $result['result'] === true) {
                // حذف از دیتابیس
                $this->removeBan($chatId, $targetUser['id']);

                $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
                $this->telegram->sendMessage(
                    $chatId,
                    "✅ آن‌بن کاربر {$username} با موفقیت انجام شد.",
                    $message['message_id'] ?? null
                );

                $this->logger->info("User {$targetUser['id']} unbanned in group $chatId by $userId");
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ آن‌بن کردن کاربر با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to unban user {$targetUser['id']} in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در آن‌بن کردن کاربر: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Unban exception: " . $e->getMessage());
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
                // سعی می‌کنیم اطلاعات کاربر را از تلگرام بگیریم
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
                    // اگر کاربر در گروه نیست، فقط آیدی را از دیتابیس می‌گیریم
                    $userInfo = $this->getUserFromBans($message['chat']['id'], $usernameOrId);
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
     * دریافت اطلاعات کاربر از جدول بن‌ها (در صورت عدم حضور در گروه)
     */
    private function getUserFromBans(int $groupId, string $username): ?array
    {
        // حذف @ از ابتدا
        $cleanUsername = ltrim($username, '@');
        
        $sql = "SELECT user_id, username, first_name, last_name 
                FROM {$this->banTable} 
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
     * بررسی اینکه کاربر قبلاً بن شده است یا خیر
     */
    private function isUserBanned(int $groupId, int $userId): bool
    {
        $sql = "SELECT id FROM {$this->banTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);
        return $result !== false;
    }

    /**
     * حذف اطلاعات بن از دیتابیس
     */
    private function removeBan(int $groupId, int $userId): void
    {
        $sql = "DELETE FROM {$this->banTable} WHERE group_id = ? AND user_id = ?";
        $this->db->execute($sql, [$groupId, $userId]);
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "رفع بن کاربر از گروه / Unban user from group";
    }
}