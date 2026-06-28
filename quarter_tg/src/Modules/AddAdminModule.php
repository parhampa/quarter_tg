<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\AdminManager;

/**
 * ماژول افزودن ادمین جدید به گروه
 * فقط ادمین‌های اصلی می‌توانند ادمین جدید اضافه کنند
 */
class AddAdminModule
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

        // بررسی ادمین بودن کاربر فرستنده (فقط ادمین‌های اصلی می‌توانند اضافه کنند)
        if (!$this->authManager->isMainAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ فقط ادمین‌های اصلی می‌توانند ادمین جدید اضافه کنند.",
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
                . "مثال: `/addadmin @username` یا ریپلای به پیام کاربر",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف خودش نباشد
        if ($targetUser['id'] == $userId) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید خودتان را ادمین کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف قبلاً ادمین نیست
        if ($this->authManager->isAdmin($chatId, $targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر @" . ($targetUser['username'] ?? $targetUser['id']) . " قبلاً ادمین است.",
                $message['message_id'] ?? null
            );
            return;
        }

        // افزودن ادمین
        $result = $this->adminManager->addAdmin(
            $chatId,
            $targetUser['id'],
            $userId,
            $targetUser['username'] ?? null,
            $targetUser['first_name'] ?? null,
            $targetUser['last_name'] ?? null
        );

        if ($result) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "✅ ادمین {$username} با موفقیت اضافه شد.",
                $message['message_id'] ?? null
            );

            $this->logger->info("Admin {$targetUser['id']} added to group $chatId by $userId");
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "❌ افزودن ادمین با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                $message['message_id'] ?? null
            );

            $this->logger->error("Failed to add admin {$targetUser['id']} to group $chatId by $userId");
        }
    }

    /**
     * استخراج کاربر هدف از پیام یا پارامترها
     * @return array|null ['id' => int, 'username' => string|null, 'first_name' => string|null, 'last_name' => string|null]
     */
    private function extractTargetUser(array $message, string $params): ?array
    {
        // 1. بررسی پارامترها (یوزرنیم یا آیدی)
        if (!empty($params)) {
            $usernameOrId = trim($params);
            
            // اگر عدد است، به عنوان آیدی در نظر بگیر
            if (is_numeric($usernameOrId)) {
                return [
                    'id' => (int)$usernameOrId,
                    'username' => null,
                    'first_name' => null,
                    'last_name' => null,
                ];
            }
            
            // اگر با @ شروع می‌شود، یوزرنیم است
            if (strpos($usernameOrId, '@') === 0) {
                // دریافت اطلاعات کاربر از تلگرام
                $chatMember = $this->telegram->getChatMember($message['chat']['id'], $usernameOrId);
                if ($chatMember && isset($chatMember['user'])) {
                    return [
                        'id' => $chatMember['user']['id'],
                        'username' => $chatMember['user']['username'] ?? null,
                        'first_name' => $chatMember['user']['first_name'] ?? null,
                        'last_name' => $chatMember['user']['last_name'] ?? null,
                    ];
                }
                
                // اگر یوزرنیم معتبر نبود
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
        return "افزودن ادمین جدید به گروه / Add new admin to group";
    }
}