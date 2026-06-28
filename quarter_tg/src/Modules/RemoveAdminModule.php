<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\AdminManager;

/**
 * ماژول حذف ادمین از گروه
 * فقط ادمین‌های اصلی می‌توانند ادمین حذف کنند
 * مالک اصلی قابل حذف نیست
 */
class RemoveAdminModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $adminManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        AdminManager $adminManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->adminManager = $adminManager;
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

        // فقط ادمین‌های اصلی می‌توانند حذف کنند
        if (!$this->authManager->isMainAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ فقط ادمین‌های اصلی می‌توانند ادمین حذف کنند.",
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
                . "مثال: `/remadmin @username` یا ریپلای به پیام کاربر",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف خودش نباشد
        if ($targetUser['id'] == $userId) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید خودتان را حذف کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف مالک اصلی نباشد
        if ($this->authManager->isOwner($targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ مالک اصلی قابل حذف نیست.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف ادمین است یا خیر
        if (!$this->authManager->isAdmin($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} ادمین نیست.",
                $message['message_id'] ?? null
            );
            return;
        }

        // حذف ادمین
        $result = false;
        $isMainAdmin = $this->authManager->isMainAdmin($chatId, $targetUser['id']);
        
        if ($isMainAdmin) {
            $result = $this->adminManager->removeAdmin($chatId, $targetUser['id']);
        } else {
            // ساب‌ادمین
            $result = $this->adminManager->removeSubAdmin($chatId, $targetUser['id']);
        }

        if ($result) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "✅ ادمین {$username} با موفقیت حذف شد.",
                $message['message_id'] ?? null
            );

            $this->logger->info("Admin {$targetUser['id']} removed from group $chatId by $userId");
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "❌ حذف ادمین با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                $message['message_id'] ?? null
            );

            $this->logger->error("Failed to remove admin {$targetUser['id']} from group $chatId by $userId");
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
                $chatMember = $this->telegram->getChatMember($message['chat']['id'], $usernameOrId);
                if ($chatMember && isset($chatMember['user'])) {
                    return [
                        'id' => $chatMember['user']['id'],
                        'username' => $chatMember['user']['username'] ?? null,
                        'first_name' => $chatMember['user']['first_name'] ?? null,
                        'last_name' => $chatMember['user']['last_name'] ?? null,
                    ];
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
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "حذف ادمین از گروه / Remove admin from group";
    }
}