<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\AdminManager;

/**
 * ماژول بن کردن کاربر از گروه
 * فقط ادمین‌ها می‌توانند بن کنند
 * ادمین‌ها و مالک اصلی قابل بن نیستند
 */
class BanModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $adminManager;
    private $banTable = 'bot_bans';

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

        // فقط ادمین‌ها می‌توانند بن کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه بن کردن کاربران را ندارید.",
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
                . "مثال: `/ban @username` یا ریپلای به پیام کاربر",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف خودش نباشد
        if ($targetUser['id'] == $userId) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید خودتان را بن کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف ادمین نباشد
        if ($this->authManager->isAdmin($chatId, $targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید یک ادمین را بن کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف مالک اصلی نباشد
        if ($this->authManager->isOwner($targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید مالک اصلی را بن کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر قبلاً بن نشده باشد
        if ($this->isUserBanned($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} قبلاً بن شده است.",
                $message['message_id'] ?? null
            );
            return;
        }

        // استخراج دلیل بن (اگر در پارامترها وجود داشته باشد)
        $reason = $this->extractReason($params, $targetUser);

        // انجام عملیات بن
        try {
            $result = $this->telegram->banChatMember($chatId, $targetUser['id']);

            if ($result && isset($result['result']) && $result['result'] === true) {
                // ذخیره در دیتابیس
                $this->saveBan($chatId, $targetUser['id'], $userId, $reason);

                $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
                $messageText = "✅ کاربر {$username} با موفقیت بن شد.";
                if ($reason) {
                    $messageText .= "\n📝 دلیل: {$reason}";
                }

                $this->telegram->sendMessage(
                    $chatId,
                    $messageText,
                    $message['message_id'] ?? null
                );

                $this->logger->info("User {$targetUser['id']} banned in group $chatId by $userId", [
                    'reason' => $reason,
                ]);
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ بن کردن کاربر با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to ban user {$targetUser['id']} in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در بن کردن کاربر: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Ban exception: " . $e->getMessage());
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
            // پارامترها ممکن است شامل دلیل نیز باشند، بنابراین اولین کلمه را به عنوان یوزرنیم در نظر می‌گیریم
            $parts = explode(' ', $params, 2);
            $usernameOrId = trim($parts[0]);
            
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
     * استخراج دلیل بن از پارامترها
     */
    private function extractReason(string $params, array $targetUser): ?string
    {
        $parts = explode(' ', $params, 2);
        if (count($parts) > 1) {
            return trim($parts[1]);
        }

        // اگر دلیل در ریپلای وجود داشت
        // (این قابلیت را می‌توان به‌صورت اختیاری اضافه کرد)

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
     * ذخیره اطلاعات بن در دیتابیس
     */
    private function saveBan(int $groupId, int $userId, int $bannedBy, ?string $reason): void
    {
        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'banned_by' => $bannedBy,
            'reason' => $reason,
            'banned_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert($this->banTable, $data);
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "بن کردن کاربر از گروه / Ban user from group";
    }
}