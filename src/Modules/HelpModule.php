<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\ModuleManager;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول راهنمای ربات
 * 
 * دستورات:
 * - /help – نمایش راهنمای کامل ربات
 * - /start – پیام خوش‌آمدگویی و راهنمای اولیه
 * - /commands – نمایش لیست دستورات (مشابه /help)
 */
class HelpModule implements ModuleInterface
{
    public const COMMANDS = ['help', 'start', 'commands'];

    private TelegramApi $telegram;
    private ModuleManager $moduleManager;
    private AuthorizationManager $authManager;
    private UserManager $userManager;
    private Logger $logger;
    private array $config;

    public function __construct(
        TelegramApi $telegram,
        ModuleManager $moduleManager,
        AuthorizationManager $authManager,
        UserManager $userManager,
        Logger $logger,
        array $config = []
    ) {
        $this->telegram = $telegram;
        $this->moduleManager = $moduleManager;
        $this->authManager = $authManager;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->config = $config;
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

        // دریافت زبان کاربر (از دیتابیس یا پیشفرض فارسی)
        $language = $this->getUserLanguage($userId);

        // پردازش دستورات مختلف
        return match ($commandName) {
            'help', 'commands' => $this->handleHelp($chatId, $userId, $language, $message),
            'start' => $this->handleStart($chatId, $userId, $language, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش راهنمای کامل
     */
    private function handleHelp(int $chatId, int $userId, string $language, array $message): array
    {
        try {
            // دریافت لیست دستورات از ModuleManager
            $commandMap = $this->moduleManager->getCommandMap();
            
            // دسته‌بندی دستورات
            $categories = $this->categorizeCommands($commandMap, $chatId, $userId);

            // تولید پیام راهنما
            $messageText = $this->generateHelpMessage($categories, $language, $chatId, $userId);

            // ارسال پیام
            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Help command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, $this->getTranslation('error', $language));
        }
    }

    /**
     * پیام خوش‌آمدگویی (دستور /start)
     */
    private function handleStart(int $chatId, int $userId, string $language, array $message): array
    {
        try {
            // دریافت اطلاعات کاربر
            $user = $this->userManager->getUser($userId);
            $firstName = $user['first_name'] ?? 'کاربر عزیز';

            // پیام خوش‌آمدگویی
            $welcome = $this->getTranslation('welcome', $language);
            $helpTip = $this->getTranslation('help_tip', $language);
            
            $messageText = "👋 **{$welcome}** {$firstName}!\n\n";
            $messageText .= $helpTip . "\n\n";
            $messageText .= "📌 برای مشاهده راهنمای کامل از دستور /help استفاده کنید.";

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            // ثبت در لاگ
            $this->logger->info('Start command executed.', ['chat' => $chatId, 'user' => $userId]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Start command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, $this->getTranslation('error', $language));
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * دریافت زبان کاربر (از دیتابیس یا پیشفرض)
     */
    private function getUserLanguage(int $userId): string
    {
        // در آینده میتوان از دیتابیس زبان کاربر را دریافت کرد
        // فعلاً پیشفرض فارسی
        return 'fa';
    }

    /**
     * دسته‌بندی دستورات بر اساس نوع
     */
    private function categorizeCommands(array $commandMap, int $chatId, int $userId): array
    {
        $categories = [
            'public' => [],
            'admin' => [],
            'moderator' => [],
            'owner' => [],
        ];

        foreach ($commandMap as $command => $className) {
            // تعیین دسته‌بندی هر دستور
            $category = $this->getCommandCategory($command, $className);
            
            // بررسی اینکه کاربر دسترسی به این دستور را دارد یا خیر
            if ($this->authManager->canExecuteCommand($chatId, $userId, $command)) {
                $categories[$category][] = $command;
            }
        }

        // حذف دسته‌های خالی
        return array_filter($categories);
    }

    /**
     * تعیین دسته‌بندی یک دستور
     */
    private function getCommandCategory(string $command, string $className): string
    {
        // دستورات عمومی
        $publicCommands = ['help', 'start', 'commands', 'ping', 'info', 'profile'];
        if (in_array($command, $publicCommands, true)) {
            return 'public';
        }

        // دستورات مدیریتی سطح بالا (فقط ادمین)
        $adminCommands = ['ban', 'unban', 'lock', 'unlock', 'lockall', 'unlockall', 
                          'setadmin', 'removeadmin', 'settings', 'clear'];
        if (in_array($command, $adminCommands, true)) {
            return 'admin';
        }

        // دستورات مودریتور
        $moderatorCommands = ['warn', 'unwarn', 'clearwarns', 'mute', 'unmute', 'kick', 'del', 'delete'];
        if (in_array($command, $moderatorCommands, true)) {
            return 'moderator';
        }

        // دستورات مالک (فقط مالک ربات)
        $ownerCommands = ['botstats', 'broadcast', 'setowner'];
        if (in_array($command, $ownerCommands, true)) {
            return 'owner';
        }

        // پیشفرض: عمومی
        return 'public';
    }

    /**
     * تولید پیام راهنما
     */
    private function generateHelpMessage(array $categories, string $language, int $chatId, int $userId): string
    {
        $translations = $this->getCategoryTranslations($language);
        $messageText = "🤖 **راهنمای ربات مدیریت گروه**\n";
        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        foreach ($categories as $category => $commands) {
            if (empty($commands)) {
                continue;
            }

            $categoryName = $translations[$category] ?? $category;
            $messageText .= "**{$categoryName}:**\n";
            
            foreach ($commands as $command) {
                // دریافت توضیح دستور (در آینده میتوان از دیتابیس یا کانفیگ خواند)
                $description = $this->getCommandDescription($command, $language);
                $messageText .= "  /{$command} – {$description}\n";
            }
            $messageText .= "\n";
        }

        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $messageText .= "💡 برای اجرای دستورات، آنها را در پیام بنویسید.\n";
        $messageText .= "📌 مثال: /help";

        return $messageText;
    }

    /**
     * دریافت ترجمه نام دسته‌بندی‌ها
     */
    private function getCategoryTranslations(string $language): array
    {
        if ($language === 'en') {
            return [
                'public' => '📋 Public Commands',
                'admin' => '🔒 Admin Commands',
                'moderator' => '🛡️ Moderator Commands',
                'owner' => '👑 Owner Commands',
            ];
        }

        // فارسی (پیشفرض)
        return [
            'public' => '📋 دستورات عمومی',
            'admin' => '🔒 دستورات ادمین',
            'moderator' => '🛡️ دستورات مودریتور',
            'owner' => '👑 دستورات مالک',
        ];
    }

    /**
     * دریافت توضیح یک دستور
     */
    private function getCommandDescription(string $command, string $language): string
    {
        $descriptions = $this->getCommandDescriptions($language);
        return $descriptions[$command] ?? 'بدون توضیح';
    }

    /**
     * دریافت لیست توضیحات دستورات
     */
    private function getCommandDescriptions(string $language): array
    {
        if ($language === 'en') {
            return [
                'help' => 'Show help',
                'start' => 'Welcome message',
                'commands' => 'Show commands list',
                'ping' => 'Check bot status',
                'info' => 'Get group/user info',
                'profile' => 'Show your profile',
                'ban' => 'Ban a user',
                'unban' => 'Unban a user',
                'kick' => 'Kick a user',
                'mute' => 'Mute a user',
                'unmute' => 'Unmute a user',
                'warn' => 'Warn a user',
                'unwarn' => 'Remove a warn',
                'clearwarns' => 'Clear all warns',
                'warns' => 'Show user warns',
                'lock' => 'Enable a lock',
                'unlock' => 'Disable a lock',
                'lockall' => 'Enable all locks',
                'unlockall' => 'Disable all locks',
                'locks' => 'Show active locks',
                'setadmin' => 'Add an admin',
                'removeadmin' => 'Remove an admin',
                'settings' => 'Group settings',
                'clear' => 'Delete messages',
                'del' => 'Delete a message',
                'delete' => 'Delete a message',
            ];
        }

        // فارسی (پیشفرض)
        return [
            'help' => 'نمایش راهنما',
            'start' => 'پیام خوش‌آمدگویی',
            'commands' => 'نمایش لیست دستورات',
            'ping' => 'بررسی وضعیت ربات',
            'info' => 'اطلاعات گروه/کاربر',
            'profile' => 'نمایش پروفایل شما',
            'ban' => 'بن کردن کاربر',
            'unban' => 'آنبن کردن کاربر',
            'kick' => 'کیک کردن کاربر',
            'mute' => 'میوت کردن کاربر',
            'unmute' => 'آنمیوت کردن کاربر',
            'warn' => 'اخطار به کاربر',
            'unwarn' => 'کاهش یک اخطار',
            'clearwarns' => 'پاک کردن همه اخطارها',
            'warns' => 'مشاهده اخطارهای کاربر',
            'lock' => 'فعال کردن قفل',
            'unlock' => 'غیرفعال کردن قفل',
            'lockall' => 'فعال کردن همه قفل‌ها',
            'unlockall' => 'غیرفعال کردن همه قفل‌ها',
            'locks' => 'نمایش قفل‌های فعال',
            'setadmin' => 'افزودن ادمین',
            'removeadmin' => 'حذف ادمین',
            'settings' => 'تنظیمات گروه',
            'clear' => 'پاک کردن پیام‌ها',
            'del' => 'حذف یک پیام',
            'delete' => 'حذف یک پیام',
        ];
    }

    /**
     * دریافت ترجمه متن‌های عمومی
     */
    private function getTranslation(string $key, string $language): string
    {
        $translations = [
            'fa' => [
                'welcome' => 'خوش آمدید',
                'help_tip' => 'برای مشاهده لیست دستورات از /help استفاده کنید.',
                'error' => '❌ خطا در نمایش راهنما. لطفاً دوباره تلاش کنید.',
            ],
            'en' => [
                'welcome' => 'Welcome',
                'help_tip' => 'Use /help to see the list of commands.',
                'error' => '❌ Error showing help. Please try again.',
            ],
        ];

        return $translations[$language][$key] ?? $translations['fa'][$key] ?? $key;
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