<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;
use Throwable;

/**
 * ماژول مدیریت میوت (سکوت)
 * 
 * دستورات:
 * - /mute [@username|user_id] [زمان] [دلیل] – میوت کردن کاربر
 * - /unmute [@username|user_id] – آنمیوت کردن کاربر
 * - /mute [@username|user_id] [زمان] – میوت موقت (مثلاً /mute @user 1h)
 */
class MuteModule implements ModuleInterface
{
    public const COMMANDS = ['mute', 'unmute'];

    private TelegramApi $telegram;
    private AuthorizationManager $authManager;
    private UserManager $userManager;
    private WarnManager $warnManager;
    private Logger $logger;

    public function __construct(
        TelegramApi $telegram,
        AuthorizationManager $authManager,
        UserManager $userManager,
        WarnManager $warnManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->authManager = $authManager;
        $this->userManager = $userManager;
        $this->warnManager = $warnManager;
        $this->logger = $logger;
    }

    /**
     * اجرای ماژول
     */
    public function execute(int $chatId, int $userId, string $param, array $message): mixed
    {
        // تشخیص دستور (از پیام اصلی)
        $text = $message['text'] ?? '';
        if (empty($text)) {
            return null;
        }

        // استخراج نام دستور (بدون /)
        $command = substr(trim($text), 1);
        $parts = explode(' ', $command, 2);
        $commandName = strtolower($parts[0]);
        $param = $parts[1] ?? '';

        // پردازش دستورات مختلف
        return match ($commandName) {
            'mute' => $this->handleMute($chatId, $userId, $param, $message),
            'unmute' => $this->handleUnmute($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * میوت کردن کاربر
     */
    private function handleMute(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی میوت کردن کاربران را ندارید.');
        }

        // استخراج کاربر هدف و پارامترها
        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username یا ID مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';
        $duration = $result['duration'] ?? null; // مثلاً 1h, 1d
        $reason = $result['reason'] ?? 'تخلف از قوانین گروه';

        // جلوگیری از میوت کردن ادمین‌ها و مالک
        if ($this->authManager->isAdmin($chatId, $targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید ادمین را میوت کنید.');
        }
        if ($this->authManager->isOwner($targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید مالک ربات را میوت کنید.');
        }

        // تبدیل زمان به timestamp برای میوت موقت
        $untilDate = null;
        if ($duration !== null) {
            $untilDate = $this->parseDuration($duration);
            if ($untilDate === null) {
                return $this->sendError($chatId, '❌ فرمت زمان نامعتبر. مثال: 1h, 30m, 2d');
            }
        }

        try {
            // تنظیمات دسترسی برای میوت (فقط می‌تواند پیام بخواند)
            $permissions = [
                'can_send_messages' => false,
                'can_send_media_messages' => false,
                'can_send_polls' => false,
                'can_send_other_messages' => false,
                'can_add_web_page_previews' => false,
                'can_change_info' => false,
                'can_invite_users' => false,
                'can_pin_messages' => false,
            ];

            // میوت کردن کاربر در تلگرام
            $this->telegram->restrictChatMember($chatId, $targetUserId, $permissions, $untilDate);

            // ثبت در دیتابیس (اختیاری)
            $this->logMute($chatId, $targetUserId, $adminId, $reason, $untilDate);

            // ارسال پیام موفقیت
            $durationText = $untilDate ? " تا " . date('Y-m-d H:i:s', $untilDate) : " (دائمی)";
            $messageText = "🔇 کاربر @{$targetUsername} با موفقیت میوت شد{$durationText}.\n📝 دلیل: {$reason}";

            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User muted.', ['chat' => $chatId, 'user' => $targetUserId, 'admin' => $adminId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Mute failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در میوت کردن کاربر: ' . $e->getMessage());
        }
    }

    /**
     * آنمیوت کردن کاربر
     */
    private function handleUnmute(int $chatId, int $adminId, string $param, array $message): array
    {
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی آنمیوت کردن کاربران را ندارید.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            // تنظیمات دسترسی کامل برای آنمیوت
            $permissions = [
                'can_send_messages' => true,
                'can_send_media_messages' => true,
                'can_send_polls' => true,
                'can_send_other_messages' => true,
                'can_add_web_page_previews' => true,
                'can_change_info' => true,
                'can_invite_users' => true,
                'can_pin_messages' => true,
            ];

            // آنمیوت کردن کاربر در تلگرام
            $this->telegram->restrictChatMember($chatId, $targetUserId, $permissions);

            $this->logUnmute($chatId, $targetUserId, $adminId);

            $messageText = "✅ کاربر @{$targetUsername} با موفقیت آنمیوت شد.";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User unmuted.', ['chat' => $chatId, 'user' => $targetUserId, 'admin' => $adminId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unmute failed.', ['chat' => $chatId, 'user' => $targetUserId, 'error' => $e->getMessage()]);
            return $this->sendError($chatId, '❌ خطا در آنمیوت کردن کاربر.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها و پیام
     * 
     * @return array|null ['user_id' => int, 'username' => string|null, 'duration' => string|null, 'reason' => string|null]
     */
    private function parseTargetUser(string $param, array $message): ?array
    {
        if (empty($param)) {
            // اگر کاربر ریپلی داده باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                    'duration' => null,
                    'reason' => null,
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id] [duration] [reason]
        $parts = preg_split('/\s+/', $param, 3);
        $target = $parts[0] ?? '';
        $duration = $parts[1] ?? null;
        $reason = $parts[2] ?? null;

        // شناسایی کاربر هدف
        $userId = null;
        $username = null;

        // اگر با @ شروع شود
        if (strpos($target, '@') === 0) {
            $username = ltrim($target, '@');
            // جستجوی کاربر در دیتابیس
            $user = $this->userManager->searchByUsername($username);
            if ($user !== null) {
                $userId = (int)$user['user_id'];
            } else {
                // اگر در دیتابیس نبود، از API تلگرام دریافت کنیم (اختیاری)
                return null;
            }
        } elseif (is_numeric($target)) {
            $userId = (int)$target;
            $user = $this->userManager->getUser($userId);
            if ($user !== null) {
                $username = $user['username'] ?? null;
            }
        } else {
            return null;
        }

        if ($userId === null || $userId <= 0) {
            return null;
        }

        // اگر duration شناسایی نشد، ممکن است reason به جای duration باشد
        if ($duration !== null && !$this->isDuration($duration)) {
            // duration در واقع reason است
            $reason = $duration . ($reason ? ' ' . $reason : '');
            $duration = null;
        }

        return [
            'user_id' => $userId,
            'username' => $username,
            'duration' => $duration,
            'reason' => $reason,
        ];
    }

    /**
     * بررسی اینکه یک رشته فرمت زمان است یا خیر
     */
    private function isDuration(string $str): bool
    {
        return (bool)preg_match('/^(\d+)([smhdw])$/i', $str);
    }

    /**
     * تبدیل رشته زمان به timestamp
     * فرمت‌های پشتیبانی‌شده: 30s, 5m, 2h, 1d, 1w
     */
    private function parseDuration(string $duration): ?int
    {
        if (!preg_match('/^(\d+)([smhdw])$/i', $duration, $matches)) {
            return null;
        }

        $value = (int)$matches[1];
        $unit = strtolower($matches[2]);

        $seconds = match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            'w' => $value * 604800,
            default => null,
        };

        if ($seconds === null) {
            return null;
        }

        return time() + $seconds;
    }

    /**
     * ارسال پیام خطا
     */
    private function sendError(int $chatId, string $message): array
    {
        $this->telegram->sendMessage($chatId, $message);
        return ['success' => false, 'message' => $message];
    }

    // ============================================================
    // متدهای لاگینگ (قابل توسعه)
    // ============================================================

    private function logMute(int $chatId, int $userId, int $adminId, string $reason, ?int $untilDate): void
    {
        // میتوان در جدول mutes ذخیره کرد
        $this->logger->info('Mute logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
            'reason' => $reason,
            'until' => $untilDate,
        ]);
    }

    private function logUnmute(int $chatId, int $userId, int $adminId): void
    {
        $this->logger->info('Unmute logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
        ]);
    }
}