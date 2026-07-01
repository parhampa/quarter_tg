<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\LockManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;
use QuarterTg\Modules\ModuleInterface;
use Throwable;

/**
 * کلاس اصلی ربات
 * مسئولیت: دریافت آپدیت از Webhook، هدایت به ماژول‌های مناسب و مدیریت جریان کلی
 */
class Bot
{
    private array $config;
    private array $update;
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    private TelegramApi $telegram;
    private UserManager $userManager;
    private AdminManager $adminManager;
    private LockManager $lockManager;
    private WarnManager $warnManager;
    private AuthorizationManager $authManager;
    private ModuleManager $moduleManager;
    private array $allowedLocks = [];

    /**
     * با استفاده از Property Promotion (PHP 8.0+) وابستگی‌ها را تزریق میکنیم
     */
    public function __construct(
        array $config,
        array $update,
        Database $db,
        Cache $cache,
        Logger $logger,
        TelegramApi $telegram,
        UserManager $userManager,
        AdminManager $adminManager,
        LockManager $lockManager,
        WarnManager $warnManager,
        AuthorizationManager $authManager,
        ModuleManager $moduleManager
    ) {
        $this->config = $config;
        $this->update = $update;
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->userManager = $userManager;
        $this->adminManager = $adminManager;
        $this->lockManager = $lockManager;
        $this->warnManager = $warnManager;
        $this->authManager = $authManager;
        $this->moduleManager = $moduleManager;
        
        // بارگذاری لیست لاک‌های مجاز از کانفیگ
        $this->allowedLocks = $config['locks'] ?? [
            'links', 'tags', 'hashtags', 'commands', 'arabic', 'english', 'persian',
            'spam', 'sticker', 'video', 'audio', 'document', 'voice', 'photo', 'gif'
        ];
    }

    // ============================================================
    // متد عمومی برای پردازش درخواست
    // ============================================================

    /**
     * نقطه ورود اصلی برای پردازش آپدیت دریافتی از Webhook
     */
    public function handleRequest(): void
    {
        try {
            // تشخیص نوع آپدیت و فراخوانی متد مناسب
            if (isset($this->update['message'])) {
                $this->handleMessage($this->update['message']);
            } elseif (isset($this->update['callback_query'])) {
                $this->handleCallbackQuery($this->update['callback_query']);
            } else {
                // سایر آپدیت‌ها (edited_message, channel_post, ...) را نادیده میگیریم یا لاگ میکنیم
                $this->logger->debug('Unhandled update type received.', ['update' => $this->update]);
            }
        } catch (Throwable $e) {
            $this->logger->critical('Critical error in Bot::handleRequest', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // در صورت خطای بحرانی، میتوانیم یک پیام به ادمین اصلی بفرستیم
            $this->notifyOwner('Critical error: ' . $e->getMessage());
        }
    }

    // ============================================================
    // پردازش پیام‌های معمولی (Message)
    // ============================================================

    private function handleMessage(array $message): void
    {
        $chatId = (int)($message['chat']['id'] ?? 0);
        $userId = (int)($message['from']['id'] ?? 0);
        $text = trim($message['text'] ?? '');
        $messageId = (int)($message['message_id'] ?? 0);

        // اگر چت یا کاربر معتبر نباشد، خارج میشویم
        if ($chatId === 0 || $userId === 0) {
            $this->logger->warning('Invalid message structure: missing chat or user id.', ['message' => $message]);
            return;
        }

        // 1. اعمال قفل‌ها (Locks) برای پیام‌های غیردستوری
        $isCommand = $this->isCommand($text);
        if (!$isCommand) {
            $this->applyLocks($chatId, $userId, $message);
        }

        // 2. اگر پیام جدید کاربر باشد، ثبت یا به‌روزرسانی میکنیم
        $this->userManager->registerOrUpdate($userId, $message['from'] ?? []);

        // 3. بررسی دستورات (Commands)
        if ($isCommand) {
            $this->handleCommand($chatId, $userId, $text, $message);
            return;
        }

        // 4. بررسی ریپلی به پیام‌های خاص (مثل پاسخ به Warn)
        if (isset($message['reply_to_message'])) {
            $this->handleReply($chatId, $userId, $message);
        }

        // 5. رویدادهای ویژه (مثل عضو جدید)
        if (isset($message['new_chat_members']) || isset($message['left_chat_member'])) {
            $this->handleMemberUpdate($chatId, $message);
        }
    }

    // ============================================================
    // پردازش Callback Query (دکمه‌های شیشه‌ای)
    // ============================================================

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $data = $callbackQuery['data'] ?? '';
        $chatId = (int)($callbackQuery['message']['chat']['id'] ?? 0);
        $userId = (int)($callbackQuery['from']['id'] ?? 0);
        $messageId = (int)($callbackQuery['message']['message_id'] ?? 0);

        if (empty($data) || $chatId === 0 || $userId === 0) {
            $this->logger->warning('Invalid callback_query structure.', ['callback' => $callbackQuery]);
            return;
        }

        // اینجا میتوانید Callback Handler را پیادهسازی کنید
        // مثلاً مدیریت دکمه‌های تأیید برای بن/میت و غیره
        $this->logger->info('Callback query received', [
            'user' => $userId,
            'chat' => $chatId,
            'data' => $data
        ]);

        // ارسال پاسخ به تلگرام برای جلوگیری از loading
        $this->telegram->answerCallbackQuery($callbackQuery['id'], 'Received!');
    }

    // ============================================================
    // متدهای کمکی برای پردازش
    // ============================================================

    /**
     * تشخیص دستور بودن یک متن
     */
    private function isCommand(string $text): bool
    {
        if (empty($text)) {
            return false;
        }
        // پشتیبانی از دستورات با / یا . (معمولاً / است)
        return strpos($text, '/') === 0 || strpos($text, '.') === 0;
    }

    /**
     * تشخیص دستور فارسی (با / یا . و کاراکترهای فارسی)
     */
    private function isPersianCommand(string $text): bool
    {
        if (empty($text)) {
            return false;
        }
        // کاراکترهای فارسی: از \x{0600} تا \x{06FF}
        return preg_match('/^[\/\.][\x{0600}-\x{06FF}\w]+/u', $text) === 1;
    }

    /**
     * اعمال قفل‌ها روی پیام
     */
    private function applyLocks(int $chatId, int $userId, array $message): void
    {
        $text = $message['text'] ?? '';
        $locks = $this->lockManager->getLocks($chatId);

        foreach ($locks as $lockType) {
            // بررسی اینکه آیا این نوع لاک در لیست مجاز است
            if (!in_array($lockType, $this->allowedLocks, true)) {
                continue;
            }

            // متدهای بررسی هر لاک را در کلاس LockManager یا Helper قرار دهید
            $isLocked = $this->checkLock($lockType, $text, $message);
            if ($isLocked) {
                // لاک فعال است - اقدام مناسب را انجام دهید
                $this->handleLockViolation($chatId, $userId, $lockType);
                break; // فقط اولین لاک نقضشده را پردازش میکنیم
            }
        }
    }

    /**
     * بررسی یک نوع لاک خاص روی پیام
     */
    private function checkLock(string $lockType, string $text, array $message): bool
    {
        switch ($lockType) {
            case 'links':
                return $this->hasLink($text);
            case 'tags':
                return $this->hasTag($text);
            case 'hashtags':
                return $this->hasHashtag($text);
            case 'commands':
                return $this->isCommand($text);
            case 'arabic':
                return $this->hasArabic($text);
            case 'english':
                return $this->hasEnglish($text);
            case 'persian':
                return $this->hasPersian($text);
            case 'spam':
                return $this->isSpam($text);
            case 'sticker':
                return isset($message['sticker']);
            case 'video':
                return isset($message['video']);
            case 'audio':
                return isset($message['audio']);
            case 'document':
                return isset($message['document']);
            case 'voice':
                return isset($message['voice']);
            case 'photo':
                return isset($message['photo']);
            case 'gif':
                return isset($message['animation']);
            default:
                return false;
        }
    }

    /**
     * وقتی یک لاک نقض شود (مثلاً کاربر لینک فرستاده)
     */
    private function handleLockViolation(int $chatId, int $userId, string $lockType): void
    {
        $this->logger->warning('Lock violation', [
            'chat' => $chatId,
            'user' => $userId,
            'lock' => $lockType
        ]);

        // حذف پیام خاطی
        $this->telegram->deleteMessage($chatId, $this->update['message']['message_id'] ?? 0);

        // ارسال اخطار (Warn) به کاربر
        $this->warnManager->addWarn($chatId, $userId, $lockType . ' violation');
        
        // ارسال پیام هشدار به گروه (اختیاری)
        $this->telegram->sendMessage(
            $chatId,
            "⚠️ کاربر {$userId} قوانین گروه را نقض کرد ({$lockType}).",
            ['reply_to_message_id' => $this->update['message']['message_id'] ?? null]
        );
    }

    /**
     * پردازش دستورات
     */
    private function handleCommand(int $chatId, int $userId, string $text, array $message): void
    {
        // حذف اسلش اول و جدا کردن نام دستور و پارامترها
        $command = substr($text, 1);
        $parts = explode(' ', $command, 2);
        $commandName = $parts[0];
        $param = $parts[1] ?? '';

        // بررسی اینکه آیا کاربر مجاز به اجرای این دستور است؟
        if (!$this->authManager->canExecuteCommand($chatId, $userId, $commandName)) {
            $this->telegram->sendMessage($chatId, "⛔ شما دسترسی لازم برای این دستور را ندارید.");
            return;
        }

        // یافتن ماژول مربوط به این دستور
        $module = $this->moduleManager->findModuleForCommand($commandName);
        if ($module === null) {
            $this->logger->debug("No module found for command: {$commandName}");
            return;
        }

        try {
            // اجرای ماژول با پارامترها و اطلاعات پیام
            $module->execute($chatId, $userId, $param, $message);
        } catch (Throwable $e) {
            $this->logger->error("Error executing module {$commandName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->telegram->sendMessage($chatId, "❌ خطا در اجرای دستور. لطفاً با پشتیبانی تماس بگیرید.");
        }
    }

    /**
     * پردازش ریپلی به پیام‌ها (مثل پاسخ به Warn)
     */
    private function handleReply(int $chatId, int $userId, array $message): void
    {
        // مثال: اگر کاربر ریپلی به پیام Warn کرده باشد، میتوانیم تعداد warn را کاهش دهیم
        // اینجا فقط یک نمونه ساده
        $this->logger->debug('Reply received', ['chat' => $chatId, 'user' => $userId]);
    }

    /**
     * پردازش رویدادهای عضو جدید یا خروج عضو
     */
    private function handleMemberUpdate(int $chatId, array $message): void
    {
        if (isset($message['new_chat_members'])) {
            foreach ($message['new_chat_members'] as $member) {
                $this->userManager->registerOrUpdate((int)$member['id'], $member);
                // خوش‌آمدگویی
                $this->telegram->sendMessage($chatId, "👋 خوش آمدید @{$member['username'] ?? 'کاربر'}!");
            }
        }

        if (isset($message['left_chat_member'])) {
            $this->logger->info('Member left', [
                'chat' => $chatId,
                'user' => $message['left_chat_member']['id'] ?? 'unknown'
            ]);
        }
    }

    /**
     * ارسال پیام به ادمین اصلی (Owner) در صورت بروز خطای بحرانی
     */
    private function notifyOwner(string $message): void
    {
        $ownerId = (int)($this->config['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $this->telegram->sendMessage($ownerId, "🚨 {$message}");
        }
    }

    // ============================================================
    // متدهای کمکی برای بررسی محتوای پیام (قابل انتقال به Helper)
    // ============================================================

    private function hasLink(string $text): bool
    {
        return (bool)preg_match('/https?:\/\/[^\s]+/', $text);
    }

    private function hasTag(string $text): bool
    {
        return (bool)preg_match('/@[\w_]+/', $text);
    }

    private function hasHashtag(string $text): bool
    {
        return (bool)preg_match('/#[\w\x{0600}-\x{06FF}]+/u', $text);
    }

    private function hasArabic(string $text): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}]+/u', $text);
    }

    private function hasEnglish(string $text): bool
    {
        return (bool)preg_match('/[A-Za-z]+/', $text);
    }

    private function hasPersian(string $text): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}]+/u', $text);
    }

    private function isSpam(string $text): bool
    {
        // بررسی تعداد کلمات تکراری یا لینک‌های زیاد
        $words = explode(' ', $text);
        if (count($words) > 50) {
            return true;
        }
        // اگر بیش از 5 لینک داشته باشد
        if (preg_match_all('/https?:\/\/[^\s]+/', $text, $matches) > 5) {
            return true;
        }
        return false;
    }

    // ============================================================
    // متد Getter برای Logger (در صورت نیاز در index.php)
    // ============================================================

    public function getLogger(): Logger
    {
        return $this->logger;
    }
}