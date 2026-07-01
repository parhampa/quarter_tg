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
 * ماژول مدیریت اخطارها
 * 
 * دستورات:
 * - /warn [@username|user_id] [دلیل] – اخطار به کاربر
 * - /unwarn [@username|user_id] – کاهش یک اخطار
 * - /warns [@username|user_id] – مشاهده اخطارهای کاربر
 * - /clearwarns [@username|user_id] – پاک کردن همه اخطارهای کاربر
 */
class WarnModule implements ModuleInterface
{
    public const COMMANDS = ['warn', 'unwarn', 'warns', 'clearwarns'];

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
            'warn' => $this->handleWarn($chatId, $userId, $param, $message),
            'unwarn' => $this->handleUnwarn($chatId, $userId, $param, $message),
            'warns' => $this->handleWarns($chatId, $userId, $param, $message),
            'clearwarns' => $this->handleClearWarns($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * اخطار به کاربر
     */
    private function handleWarn(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین یا مودریتور
        if (!$this->authManager->isModerator($chatId, $adminId) && !$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی اخطار دادن به کاربران را ندارید.');
        }

        // استخراج کاربر هدف و دلیل
        $result = $this->parseTargetUserWithReason($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';
        $reason = $result['reason'] ?? 'تخلف از قوانین گروه';

        // جلوگیری از اخطار به ادمین‌ها و مالک
        if ($this->authManager->isAdmin($chatId, $targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید به ادمین اخطار دهید.');
        }
        if ($this->authManager->isOwner($targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید به مالک ربات اخطار دهید.');
        }

        try {
            // افزایش اخطار
            $result = $this->warnManager->addWarn($chatId, $targetUserId, $reason, $adminId);

            if (!$result['success']) {
                return $this->sendError($chatId, $result['message']);
            }

            // ارسال پیام موفقیت به گروه (اگر بن نشده باشد)
            if (!$result['banned']) {
                $messageText = "⚠️ کاربر @{$targetUsername} اخطار دریافت کرد.\n" .
                               "📝 دلیل: {$reason}\n" .
                               "📊 تعداد اخطار: {$result['warns']} از {$this->warnManager->getMaxWarns()}";
                $this->telegram->sendMessage($chatId, $messageText);
            } else {
                // کاربر بن شده، پیام بن قبلاً توسط WarnManager ارسال شده
                $messageText = $result['message'];
            }

            $this->logger->info('Warn command executed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
                'reason' => $reason,
                'warns' => $result['warns'],
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Warn command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در ثبت اخطار: ' . $e->getMessage());
        }
    }

    /**
     * کاهش یک اخطار از کاربر
     */
    private function handleUnwarn(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین یا مودریتور
        if (!$this->authManager->isModerator($chatId, $adminId) && !$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی کاهش اخطار را ندارید.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            $result = $this->warnManager->removeWarn($chatId, $targetUserId);

            if (!$result['success']) {
                return $this->sendError($chatId, $result['message']);
            }

            $messageText = "✅ یک اخطار از کاربر @{$targetUsername} کاهش یافت.\n" .
                           "📊 تعداد فعلی: {$result['warns']}";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Unwarn command executed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
                'warns' => $result['warns'],
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unwarn command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در کاهش اخطار: ' . $e->getMessage());
        }
    }

    /**
     * مشاهده اخطارهای یک کاربر
     */
    private function handleWarns(int $chatId, int $userId, string $param, array $message): array
    {
        // اگر پارامتر وجود ندارد، اخطارهای خود کاربر را نشان بده
        if (empty($param) && !isset($message['reply_to_message'])) {
            $targetUserId = $userId;
            $targetUsername = 'شما';
        } else {
            $result = $this->parseTargetUser($param, $message);
            if ($result === null) {
                return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
            }
            $targetUserId = $result['user_id'];
            $targetUsername = $result['username'] ?? 'کاربر';
        }

        try {
            // دریافت تعداد اخطارها
            $count = $this->warnManager->getWarnCount($chatId, $targetUserId);
            
            // دریافت لیست اخطارها (حداکثر ۵ مورد آخر)
            $warns = $this->warnManager->getWarns($chatId, $targetUserId);
            $warns = array_slice($warns, 0, 5);

            $messageText = "📋 اخطارهای کاربر @{$targetUsername}:\n";
            $messageText .= "📊 تعداد کل: {$count} از {$this->warnManager->getMaxWarns()}\n\n";

            if (empty($warns)) {
                $messageText .= "✅ این کاربر هیچ اخطاری ندارد.";
            } else {
                $messageText .= "📝 آخرین اخطارها:\n";
                foreach ($warns as $index => $warn) {
                    $num = $index + 1;
                    $reason = $warn['reason'] ?? 'بدون دلیل';
                    $date = date('Y-m-d H:i', strtotime($warn['created_at']));
                    $messageText .= "{$num}. {$reason} ({$date})\n";
                }
            }

            $this->telegram->sendMessage($chatId, $messageText);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Warns command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت اطلاعات اخطارها.');
        }
    }

    /**
     * پاک کردن همه اخطارهای یک کاربر
     */
    private function handleClearWarns(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین (فقط ادمین‌ها میتوانند همه اخطارها را پاک کنند)
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند همه اخطارهای یک کاربر را پاک کنند.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            $result = $this->warnManager->clearWarns($chatId, $targetUserId);

            if (!$result['success']) {
                return $this->sendError($chatId, $result['message']);
            }

            $messageText = "✅ همه اخطارهای کاربر @{$targetUsername} پاک شد.";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Clear warns command executed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Clear warns command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در پاک کردن اخطارها.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها (بدون دلیل)
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
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id]
        $target = trim($param);
        $userId = null;
        $username = null;

        // اگر با @ شروع شود
        if (strpos($target, '@') === 0) {
            $username = ltrim($target, '@');
            $user = $this->userManager->searchByUsername($username);
            if ($user !== null) {
                $userId = (int)$user['user_id'];
            } else {
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

        return [
            'user_id' => $userId,
            'username' => $username,
        ];
    }

    /**
     * استخراج کاربر هدف با دلیل (برای دستور warn)
     */
    private function parseTargetUserWithReason(string $param, array $message): ?array
    {
        if (empty($param)) {
            // اگر کاربر ریپلی داده باشد، دلیل را از متن ریپلی بگیریم (اختیاری)
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                // دلیل میتواند از متن پیام اصلی بعد از دستور باشد (که خالی است)
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                    'reason' => 'تخلف از قوانین گروه',
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id] [reason...]
        $parts = preg_split('/\s+/', $param, 2);
        $target = $parts[0] ?? '';
        $reason = $parts[1] ?? 'تخلف از قوانین گروه';

        $userId = null;
        $username = null;

        // اگر با @ شروع شود
        if (strpos($target, '@') === 0) {
            $username = ltrim($target, '@');
            $user = $this->userManager->searchByUsername($username);
            if ($user !== null) {
                $userId = (int)$user['user_id'];
            } else {
                return null;
            }
        } elseif (is_numeric($target)) {
            $userId = (int)$target;
            $user = $this->userManager->getUser($userId);
            if ($user !== null) {
                $username = $user['username'] ?? null;
            }
        } else {
            // اگر پارامتر اول عدد یا @ نبود، ممکن است کاربر ریپلی داده باشد و پارامتر فقط دلیل باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                $userId = (int)$target['id'];
                $username = $target['username'] ?? null;
                $reason = $param; // کل پارامتر به عنوان دلیل
            } else {
                return null;
            }
        }

        if ($userId === null || $userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'username' => $username,
            'reason' => $reason,
        ];
    }

    /**
     * ارسال پیام خطا
     */
    private function sendError(int $chatId, string $message): array
    {
        $this->telegram->sendMessage($chatId, $message);
        return ['success' => false, 'message' => $message];
    }
}