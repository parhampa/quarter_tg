<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\LockManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول مدیریت قفل‌های گروه
 * 
 * دستورات:
 * - /locks – نمایش لیست قفل‌های فعال و پشتیبانی‌شده
 * - /lock [lock_type1] [lock_type2] ... – فعال کردن یک یا چند قفل
 * - /unlock [lock_type1] [lock_type2] ... – غیرفعال کردن یک یا چند قفل
 * - /lockall – فعال کردن همه قفل‌ها
 * - /unlockall – غیرفعال کردن همه قفل‌ها
 */
class LockModule implements ModuleInterface
{
    public const COMMANDS = ['locks', 'lock', 'unlock', 'lockall', 'unlockall'];

    private TelegramApi $telegram;
    private AuthorizationManager $authManager;
    private LockManager $lockManager;
    private UserManager $userManager;
    private Logger $logger;

    public function __construct(
        TelegramApi $telegram,
        AuthorizationManager $authManager,
        LockManager $lockManager,
        UserManager $userManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->authManager = $authManager;
        $this->lockManager = $lockManager;
        $this->userManager = $userManager;
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
            'locks' => $this->handleLocks($chatId, $userId, $param, $message),
            'lock' => $this->handleLock($chatId, $userId, $param, $message),
            'unlock' => $this->handleUnlock($chatId, $userId, $param, $message),
            'lockall' => $this->handleLockAll($chatId, $userId, $message),
            'unlockall' => $this->handleUnlockAll($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش لیست قفل‌ها
     */
    private function handleLocks(int $chatId, int $userId, string $param, array $message): array
    {
        try {
            // دریافت قفل‌های فعال
            $activeLocks = $this->lockManager->getLocks($chatId);
            
            // دریافت همه قفل‌های پشتیبانی‌شده
            $allLocks = $this->lockManager->getSupportedLockTypes();

            // ترجمه نام قفل‌ها به فارسی (برای نمایش بهتر)
            $lockNames = $this->getLockNames();

            $messageText = "🔒 **مدیریت قفل‌های گروه**\n\n";
            $messageText .= "📋 **قفل‌های فعال:**\n";
            
            if (empty($activeLocks)) {
                $messageText .= "✅ هیچ قفلی فعال نیست.\n\n";
            } else {
                foreach ($activeLocks as $lock) {
                    $name = $lockNames[$lock] ?? $lock;
                    $messageText .= "✅ {$name}\n";
                }
                $messageText .= "\n";
            }

            $messageText .= "📋 **قفل‌های پشتیبانی‌شده:**\n";
            foreach ($allLocks as $lock) {
                $name = $lockNames[$lock] ?? $lock;
                $status = in_array($lock, $activeLocks, true) ? '✅' : '❌';
                $messageText .= "{$status} {$name}\n";
            }

            $messageText .= "\n💡 **راهنما:**\n";
            $messageText .= "/lock [نوع] - فعال کردن قفل\n";
            $messageText .= "/unlock [نوع] - غیرفعال کردن قفل\n";
            $messageText .= "/lockall - فعال کردن همه قفل‌ها\n";
            $messageText .= "/unlockall - غیرفعال کردن همه قفل‌ها";

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Locks command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت لیست قفل‌ها.');
        }
    }

    /**
     * فعال کردن یک یا چند قفل
     */
    private function handleLock(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قفل‌ها را مدیریت کنند.');
        }

        if (empty($param)) {
            return $this->sendError($chatId, '❌ لطفاً نوع قفل را مشخص کنید.\nمثال: /lock links spam');
        }

        // پارامترها را به آرایه تبدیل کنیم
        $lockTypes = preg_split('/\s+/', trim($param));
        $validLocks = [];
        $invalidLocks = [];

        // اعتبارسنجی قفل‌ها
        $allLocks = $this->lockManager->getSupportedLockTypes();
        foreach ($lockTypes as $lock) {
            $lock = strtolower(trim($lock));
            if (in_array($lock, $allLocks, true)) {
                $validLocks[] = $lock;
            } else {
                $invalidLocks[] = $lock;
            }
        }

        if (empty($validLocks)) {
            $invalidText = implode(', ', $invalidLocks);
            return $this->sendError($chatId, "❌ نوع قفل نامعتبر: {$invalidText}\n" .
                "لیست قفل‌های پشتیبانی‌شده را با /locks مشاهده کنید.");
        }

        try {
            $successCount = 0;
            $failedLocks = [];

            foreach ($validLocks as $lock) {
                $result = $this->lockManager->setLock($chatId, $lock);
                if ($result) {
                    $successCount++;
                } else {
                    $failedLocks[] = $lock;
                }
            }

            // ترجمه نام قفل‌ها
            $lockNames = $this->getLockNames();
            $validNames = array_map(fn($l) => $lockNames[$l] ?? $l, $validLocks);
            $failedNames = array_map(fn($l) => $lockNames[$l] ?? $l, $failedLocks);

            $messageText = "✅ قفل‌های زیر با موفقیت فعال شدند:\n";
            $messageText .= implode("\n", array_map(fn($l) => "🔒 {$l}", $validNames));

            if (!empty($failedLocks)) {
                $messageText .= "\n\n⚠️ قفل‌های زیر فعال نشدند:\n";
                $messageText .= implode("\n", array_map(fn($l) => "❌ {$l}", $failedNames));
            }

            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('Lock command executed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'locks' => $validLocks,
                'failed' => $failedLocks,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Lock command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در فعال کردن قفل‌ها.');
        }
    }

    /**
     * غیرفعال کردن یک یا چند قفل
     */
    private function handleUnlock(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قفل‌ها را مدیریت کنند.');
        }

        if (empty($param)) {
            return $this->sendError($chatId, '❌ لطفاً نوع قفل را مشخص کنید.\nمثال: /unlock links spam');
        }

        // پارامترها را به آرایه تبدیل کنیم
        $lockTypes = preg_split('/\s+/', trim($param));
        $validLocks = [];
        $invalidLocks = [];

        // اعتبارسنجی قفل‌ها
        $allLocks = $this->lockManager->getSupportedLockTypes();
        foreach ($lockTypes as $lock) {
            $lock = strtolower(trim($lock));
            if (in_array($lock, $allLocks, true)) {
                $validLocks[] = $lock;
            } else {
                $invalidLocks[] = $lock;
            }
        }

        if (empty($validLocks)) {
            $invalidText = implode(', ', $invalidLocks);
            return $this->sendError($chatId, "❌ نوع قفل نامعتبر: {$invalidText}\n" .
                "لیست قفل‌های پشتیبانی‌شده را با /locks مشاهده کنید.");
        }

        try {
            $successCount = 0;
            $failedLocks = [];

            foreach ($validLocks as $lock) {
                $result = $this->lockManager->removeLock($chatId, $lock);
                if ($result) {
                    $successCount++;
                } else {
                    $failedLocks[] = $lock;
                }
            }

            // ترجمه نام قفل‌ها
            $lockNames = $this->getLockNames();
            $validNames = array_map(fn($l) => $lockNames[$l] ?? $l, $validLocks);
            $failedNames = array_map(fn($l) => $lockNames[$l] ?? $l, $failedLocks);

            $messageText = "✅ قفل‌های زیر با موفقیت غیرفعال شدند:\n";
            $messageText .= implode("\n", array_map(fn($l) => "🔓 {$l}", $validNames));

            if (!empty($failedLocks)) {
                $messageText .= "\n\n⚠️ قفل‌های زیر غیرفعال نشدند:\n";
                $messageText .= implode("\n", array_map(fn($l) => "❌ {$l}", $failedNames));
            }

            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('Unlock command executed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'locks' => $validLocks,
                'failed' => $failedLocks,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unlock command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در غیرفعال کردن قفل‌ها.');
        }
    }

    /**
     * فعال کردن همه قفل‌ها
     */
    private function handleLockAll(int $chatId, int $adminId, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قفل‌ها را مدیریت کنند.');
        }

        try {
            $allLocks = $this->lockManager->getSupportedLockTypes();
            $result = $this->lockManager->setMultipleLocks($chatId, $allLocks);

            if ($result) {
                $messageText = "✅ همه قفل‌ها با موفقیت فعال شدند.";
                $this->telegram->sendMessage($chatId, $messageText);
                $this->logger->info('Lock all command executed.', [
                    'chat' => $chatId,
                    'admin' => $adminId,
                ]);
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در فعال کردن همه قفل‌ها.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Lock all command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در فعال کردن همه قفل‌ها.');
        }
    }

    /**
     * غیرفعال کردن همه قفل‌ها
     */
    private function handleUnlockAll(int $chatId, int $adminId, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قفل‌ها را مدیریت کنند.');
        }

        try {
            $result = $this->lockManager->removeAllLocks($chatId);

            if ($result) {
                $messageText = "✅ همه قفل‌ها با موفقیت غیرفعال شدند.";
                $this->telegram->sendMessage($chatId, $messageText);
                $this->logger->info('Unlock all command executed.', [
                    'chat' => $chatId,
                    'admin' => $adminId,
                ]);
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در غیرفعال کردن همه قفل‌ها.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Unlock all command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در غیرفعال کردن همه قفل‌ها.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * دریافت نام‌های فارسی قفل‌ها
     */
    private function getLockNames(): array
    {
        return [
            'links' => 'لینک',
            'tags' => 'منشن (tag)',
            'hashtags' => 'هشتگ',
            'commands' => 'دستورات',
            'arabic' => 'متن عربی',
            'english' => 'متن انگلیسی',
            'persian' => 'متن فارسی',
            'spam' => 'اسپم',
            'sticker' => 'استیکر',
            'video' => 'ویدیو',
            'audio' => 'صدا',
            'document' => 'فایل (سند)',
            'voice' => 'ویس',
            'photo' => 'عکس',
            'gif' => 'GIF',
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