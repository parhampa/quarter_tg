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
 * ماژول مدیریت بن
 * 
 * دستورات:
 * - /ban [@username|user_id] [دلیل] – بن کردن کاربر
 * - /unban [@username|user_id] – آنبن کردن کاربر
 * - /kick [@username|user_id] – کیک کردن کاربر (بن و آنبن سریع)
 * - /ban [@username|user_id] [زمان] [دلیل] – بن موقت (مثلاً /ban @user 1h اسپم)
 */
class BanModule implements ModuleInterface
{
    public const COMMANDS = ['ban', 'unban', 'kick', 'banlist'];

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
            'ban' => $this->handleBan($chatId, $userId, $param, $message),
            'unban' => $this->handleUnban($chatId, $userId, $param, $message),
            'kick' => $this->handleKick($chatId, $userId, $param, $message),
            'banlist' => $this->handleBanList($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * بن کردن کاربر
     */
    private function handleBan(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی بن کردن کاربران را ندارید.');
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

        // جلوگیری از بن کردن ادمین‌ها و مالک
        if ($this->authManager->isAdmin($chatId, $targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید ادمین را بن کنید.');
        }
        if ($this->authManager->isOwner($targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید مالک ربات را بن کنید.');
        }

        // تبدیل زمان به timestamp برای بن موقت
        $untilDate = null;
        if ($duration !== null) {
            $untilDate = $this->parseDuration($duration);
            if ($untilDate === null) {
                return $this->sendError($chatId, '❌ فرمت زمان نامعتبر. مثال: 1h, 30m, 2d');
            }
        }

        try {
            // بن کردن کاربر در تلگرام
            $this->telegram->banChatMember($chatId, $targetUserId, $untilDate);

            // ثبت در دیتابیس (اختیاری)
            $this->logBan($chatId, $targetUserId, $adminId, $reason, $untilDate);

            // پاک کردن اخطارهای کاربر (اختیاری)
            $this->warnManager->clearWarns($chatId, $targetUserId);

            // ارسال پیام موفقیت
            $durationText = $untilDate ? " تا " . date('Y-m-d H:i:s', $untilDate) : " (دائمی)";
            $messageText = "🚫 کاربر @{$targetUsername} با موفقیت بن شد{$durationText}.\n📝 دلیل: {$reason}";

            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User banned.', ['chat' => $chatId, 'user' => $targetUserId, 'admin' => $adminId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Ban failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در بن کردن کاربر: ' . $e->getMessage());
        }
    }

    /**
     * آنبن کردن کاربر
     */
    private function handleUnban(int $chatId, int $adminId, string $param, array $message): array
    {
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی آنبن کردن کاربران را ندارید.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            $this->telegram->unbanChatMember($chatId, $targetUserId);
            $this->logUnban($chatId, $targetUserId, $adminId);

            $messageText = "✅ کاربر @{$targetUsername} با موفقیت آنبن شد.";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User unbanned.', ['chat' => $chatId, 'user' => $targetUserId, 'admin' => $adminId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unban failed.', ['chat' => $chatId, 'user' => $targetUserId, 'error' => $e->getMessage()]);
            return $this->sendError($chatId, '❌ خطا در آنبن کردن کاربر.');
        }
    }

    /**
     * کیک کردن کاربر (بن و سپس آنبن سریع)
     */
    private function handleKick(int $chatId, int $adminId, string $param, array $message): array
    {
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی کیک کردن کاربران را ندارید.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            // بن کردن
            $this->telegram->banChatMember($chatId, $targetUserId);
            // آنبن کردن (برای کیک)
            $this->telegram->unbanChatMember($chatId, $targetUserId);

            $this->logKick($chatId, $targetUserId, $adminId);

            $messageText = "👢 کاربر @{$targetUsername} با موفقیت کیک شد.";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User kicked.', ['chat' => $chatId, 'user' => $targetUserId, 'admin' => $adminId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Kick failed.', ['chat' => $chatId, 'user' => $targetUserId, 'error' => $e->getMessage()]);
            return $this->sendError($chatId, '❌ خطا در کیک کردن کاربر.');
        }
    }

    /**
     * دریافت لیست کاربران بن‌شده (قابل توسعه)
     */
    private function handleBanList(int $chatId, int $adminId, array $message): array
    {
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی مشاهده لیست بن‌ها را ندارید.');
        }

        // این متد نیاز به جدول bans دارد که هنوز پیادهسازی نشده
        return $this->sendError($chatId, '⚠️ لیست بن‌ها در حال توسعه است.');
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

    private function logBan(int $chatId, int $userId, int $adminId, string $reason, ?int $untilDate): void
    {
        // میتوان در جدول bans ذخیره کرد
        $this->logger->info('Ban logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
            'reason' => $reason,
            'until' => $untilDate,
        ]);
    }

    private function logUnban(int $chatId, int $userId, int $adminId): void
    {
        $this->logger->info('Unban logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
        ]);
    }

    private function logKick(int $chatId, int $userId, int $adminId): void
    {
        $this->logger->info('Kick logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
        ]);
    }
}