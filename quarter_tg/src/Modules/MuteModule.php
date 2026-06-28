<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\MuteManager;

/**
 * ماژول سکوت (میوت) کاربر در گروه
 * فقط ادمین‌ها می‌توانند سکوت کنند
 * ادمین‌ها و مالک اصلی قابل سکوت نیستند
 * پشتیبانی از سکوت با مدت زمان مشخص
 */
class MuteModule
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

        // فقط ادمین‌ها می‌توانند سکوت کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه سکوت کاربران را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // استخراج کاربر هدف و پارامترها
        $targetUser = $this->extractTargetUser($message, $params);
        if (!$targetUser) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ لطفاً یک کاربر را مشخص کنید.\n"
                . "مثال: `/mute @username 60` (سکوت ۶۰ ثانیه)\n"
                . "یا: `/mute @username` (سکوت دائمی)",
                $message['message_id'] ?? null
            );
            return;
        }

        // استخراج مدت زمان و دلیل
        $duration = $this->extractDuration($params, $targetUser);
        $reason = $this->extractReason($params, $targetUser, $duration);

        // بررسی اینکه کاربر هدف خودش نباشد
        if ($targetUser['id'] == $userId) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید خودتان را سکوت کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف ادمین نباشد
        if ($this->authManager->isAdmin($chatId, $targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید یک ادمین را سکوت کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر هدف مالک اصلی نباشد
        if ($this->authManager->isOwner($targetUser['id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ شما نمی‌توانید مالک اصلی را سکوت کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی اینکه کاربر قبلاً سکوت نشده باشد
        if ($this->muteManager->isMuted($chatId, $targetUser['id'])) {
            $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
            
            // دریافت اطلاعات میوت موجود
            $muteInfo = $this->muteManager->getMuteInfo($chatId, $targetUser['id']);
            $until = isset($muteInfo['until']) ? date('Y/m/d H:i', strtotime($muteInfo['until'])) : 'نامحدود';
            
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ کاربر {$username} قبلاً سکوت شده است.\n"
                . "⏱️ تا: {$until}",
                $message['message_id'] ?? null
            );
            return;
        }

        // انجام عملیات سکوت
        try {
            $result = $this->muteManager->mute(
                $chatId,
                $targetUser['id'],
                $userId,
                $reason,
                $duration,
                true // حذف پیام‌ها
            );

            if ($result) {
                $username = $targetUser['username'] ? '@' . $targetUser['username'] : "کاربر با آیدی {$targetUser['id']}";
                
                $messageText = "✅ کاربر {$username} با موفقیت سکوت شد.";
                
                if ($duration !== null) {
                    $messageText .= "\n⏱️ مدت زمان: " . $this->formatDuration($duration);
                } else {
                    $messageText .= "\n⏱️ مدت زمان: <b>دائمی</b>";
                }
                
                if ($reason) {
                    $messageText .= "\n📝 دلیل: {$reason}";
                }

                $this->telegram->sendMessage(
                    $chatId,
                    $messageText,
                    $message['message_id'] ?? null,
                    'HTML'
                );

                $this->logger->info("User {$targetUser['id']} muted in group $chatId by $userId", [
                    'duration' => $duration,
                    'reason' => $reason,
                ]);
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ سکوت کاربر با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to mute user {$targetUser['id']} in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در سکوت کاربر: " . $e->getMessage(),
                $message['message_id'] ?? null
            );

            $this->logger->error("Mute exception: " . $e->getMessage());
        }
    }

    /**
     * استخراج کاربر هدف از پیام یا پارامترها
     * @return array|null ['id' => int, 'username' => string|null, 'first_name' => string|null, 'last_name' => string|null]
     */
    private function extractTargetUser(array $message, string $params): ?array
    {
        // پارامترها را به بخش‌ها تقسیم می‌کنیم
        $parts = !empty($params) ? explode(' ', $params) : [];
        $usernameOrId = $parts[0] ?? '';

        // 1. بررسی پارامترها
        if (!empty($usernameOrId)) {
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
                    return null;
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
     * استخراج مدت زمان سکوت از پارامترها (بر حسب ثانیه)
     */
    private function extractDuration(string $params, array $targetUser): ?int
    {
        $parts = explode(' ', $params);
        
        // اگر پارامتر اول کاربر بود، پارامتر دوم می‌تواند مدت زمان باشد
        $durationStr = $parts[1] ?? '';
        
        if (empty($durationStr)) {
            return null; // سکوت دائمی
        }

        // اگر عدد است
        if (is_numeric($durationStr)) {
            return (int)$durationStr;
        }

        // پشتیبانی از فرمت‌های زمان: 1m, 1h, 1d
        $unit = substr($durationStr, -1);
        $value = (int)substr($durationStr, 0, -1);
        
        if ($value <= 0) {
            return null;
        }

        switch ($unit) {
            case 'm': // دقیقه
                return $value * 60;
            case 'h': // ساعت
                return $value * 3600;
            case 'd': // روز
                return $value * 86400;
            default:
                // اگر عدد با واحد نبود، به عنوان ثانیه در نظر بگیر
                if (is_numeric($durationStr)) {
                    return (int)$durationStr;
                }
                return null;
        }
    }

    /**
     * استخراج دلیل سکوت از پارامترها
     */
    private function extractReason(string $params, array $targetUser, ?int $duration): ?string
    {
        $parts = explode(' ', $params);
        
        // اگر مدت زمان وجود داشت، بخش دوم به بعد دلیل است
        if ($duration !== null) {
            // پارامتر اول: کاربر، پارامتر دوم: مدت زمان، بقیه: دلیل
            if (count($parts) > 2) {
                return implode(' ', array_slice($parts, 2));
            }
        } else {
            // پارامتر اول: کاربر، بقیه: دلیل
            if (count($parts) > 1) {
                return implode(' ', array_slice($parts, 1));
            }
        }

        return null;
    }

    /**
     * فرمت کردن مدت زمان به صورت خوانا
     */
    private function formatDuration(int $seconds): string
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
        return "سکوت کاربر در گروه / Mute user in group";
    }
}