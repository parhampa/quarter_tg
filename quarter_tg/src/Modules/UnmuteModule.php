<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\MuteManager;

/**
 * ماژول رفع سکوت (آن‌میوت) کاربر در گروه
 * فقط ادمین‌ها می‌توانند رفع سکوت کنند
 */
class UnmuteModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $muteManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        MuteManager $muteManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->muteManager = $muteManager;
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

        // فقط ادمین‌ها می‌توانند رفع سکوت کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه رفع سکوت کاربران را ندارید.",
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
                . "مثال: `/unmute @username` یا ریپلای به پیام کاربر",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر سکوت شده است یا خیر
        if (!$this->muteManager->isMuted($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} در لیست سکوت‌ها وجود ندارد.",
                $message['message_id'] ?? null
            );
            return;
        }

        // انجام عملیات رفع سکوت
        try {
            $result = $this->muteManager->unmute($chatId, $targetUser['id']);

            if ($result) {
                $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
                $this->telegram->sendMessage(
                    $chatId,
                    "✅ رفع سکوت کاربر {$username} با موفقیت انجام شد.\n"
                    . "🔓 کاربر می‌تواند دوباره پیام ارسال کند.",
                    $message['message_id'] ?? null,
                    'HTML'
                );

                $this->logger->info("User {$targetUser['id']} unmuted in group $chatId by $userId");
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ رفع سکوت کاربر با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to unmute user {$targetUser['id']} in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در رفع سکوت کاربر: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Unmute exception: " . $e->getMessage());
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
                    $userInfo = $this->getUserFromMutes($message['chat']['id'], $usernameOrId);
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
     * دریافت اطلاعات کاربر از جدول سکوت‌ها (در صورت عدم حضور در گروه)
     */
    private function getUserFromMutes(int $groupId, string $username): ?array
    {
        // حذف @ از ابتدا
        $cleanUsername = ltrim($username, '@');
        
        $sql = "SELECT user_id, username, first_name, last_name 
                FROM bot_mutes 
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
        return "رفع سکوت کاربر در گروه / Unmute user in group";
    }
}