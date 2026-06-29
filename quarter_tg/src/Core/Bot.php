<?php

namespace QuarterTg\Core;

use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Helpers\LanguageHelper;

/**
 * کلاس اصلی ربات
 * تمام منطق پردازش پیام‌ها، قفل‌ها، دستورات و رویدادها در اینجا یکپارچه شده است
 * با پشتیبانی کامل از قفل هشتگ و سیستم ترجمه
 */
class Bot
{
    private $telegramApi;
    private $moduleManager;
    private $lockManager;
    private $muteManager;
    private $warningManager;
    private $authorizationManager;
    private $adminManager;
    private $welcomeManager;
    private $messageLogger;
    private $commandLogger;
    private $database;
    private $cache;
    private $logger;
    private $langHelper;
    private $config;

    public function __construct($config, $database, $cache, $logger)
    {
        $this->config = $config;
        $this->database = $database;
        $this->cache = $cache;
        $this->logger = $logger;

        // ایجاد LanguageHelper با زبان پیش‌فرض از config
        $this->langHelper = new LanguageHelper($config['default_language'] ?? 'fa');

        $this->telegramApi = new TelegramApi($config['bot_token']);
        $this->lockManager = new LockManager($database, $cache);
        $this->muteManager = new MuteManager($database, $cache);
        $this->muteManager->setTelegram($this->telegramApi);
        $this->muteManager->setLogger($this->logger);
        
        $this->warningManager = new WarningManager($database, $cache);
        $this->warningManager->setTelegram($this->telegramApi);
        $this->warningManager->setLogger($this->logger);
        
        $this->authorizationManager = new AuthorizationManager($database, $cache, $config['owner_id']);
        $this->adminManager = new AdminManager($database, $cache);
        $this->adminManager->setLogger($this->logger);
        
        $this->welcomeManager = new WelcomeManager($database, $cache);
        $this->welcomeManager->setTelegram($this->telegramApi);
        $this->welcomeManager->setLogger($this->logger);
        
        $this->messageLogger = new MessageLogger($database);
        $this->messageLogger->setLogger($this->logger);
        
        $this->commandLogger = new CommandLogger($database);
        $this->commandLogger->setLogger($this->logger);

        // ModuleManager با وابستگی‌های کامل
        $this->moduleManager = new ModuleManager(
            $config['command_map'],
            [
                'telegram' => $this->telegramApi,
                'db' => $this->database,
                'logger' => $this->logger,
                'langHelper' => $this->langHelper,
                'lockManager' => $this->lockManager,
                'muteManager' => $this->muteManager,
                'warningManager' => $this->warningManager,
                'authManager' => $this->authorizationManager,
                'adminManager' => $this->adminManager,
                'welcomeManager' => $this->welcomeManager,
                'messageLogger' => $this->messageLogger,
                'commandLogger' => $this->commandLogger,
                'cache' => $this->cache,
            ]
        );
    }

    /**
     * پردازش درخواست دریافتی از تلگرام
     */
    public function handleRequest($update)
    {
        // پردازش callback_query در صورت وجود
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }

        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'] ?? null;

        // لاگ کردن پیام
        $this->messageLogger->log($message);

        // تشخیص زبان برای پیام‌های پاسخ
        $text = $message['text'] ?? '';
        $lang = $this->langHelper->detectLanguageFromCommand($text);

        // بررسی سکوت کاربر
        if ($this->muteManager->isMuted($chatId, $userId)) {
            $this->telegramApi->deleteMessage($chatId, $message['message_id']);
            return;
        }

        // بررسی قفل‌های گروه
        if ($this->checkLocks($message, $lang)) {
            return; // پیام قفل شده و حذف گردید
        }

        // پردازش دستورات (Command)
        if (strpos($text, '/') === 0 || $this->isPersianCommand($text)) {
            $this->processCommand($message, $lang);
            return;
        }

        // خوش‌آمدگویی به عضو جدید
        if (isset($message['new_chat_members'])) {
            $this->welcomeManager->handleNewMember($message);
        }
    }

    /**
     * بررسی قفل‌های فعال گروه
     */
    private function checkLocks($message, string $lang = 'fa'): bool
    {
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];
        $userId = $message['from']['id'] ?? null;

        // مدیران از قفل‌ها مستثنی هستند
        if ($this->authorizationManager->isAdmin($chatId, $userId)) {
            return false;
        }

        $locked = false;
        $lockType = '';

        // قفل متن
        if (isset($message['text']) && $this->lockManager->isLocked($chatId, 'text')) {
            $locked = true;
            $lockType = 'text';
        }

        // قفل عکس
        elseif (isset($message['photo']) && $this->lockManager->isLocked($chatId, 'photo')) {
            $locked = true;
            $lockType = 'photo';
        }

        // قفل فیلم
        elseif (isset($message['video']) && $this->lockManager->isLocked($chatId, 'video')) {
            $locked = true;
            $lockType = 'video';
        }

        // قفل GIF
        elseif (isset($message['animation']) && $this->lockManager->isLocked($chatId, 'gif')) {
            $locked = true;
            $lockType = 'gif';
        }

        // قفل استیکر
        elseif (isset($message['sticker']) && $this->lockManager->isLocked($chatId, 'sticker')) {
            $locked = true;
            $lockType = 'sticker';
        }

        // قفل ویس
        elseif (isset($message['voice']) && $this->lockManager->isLocked($chatId, 'voice')) {
            $locked = true;
            $lockType = 'voice';
        }

        // قفل ویدئو مسیج
        elseif (isset($message['video_note']) && $this->lockManager->isLocked($chatId, 'video_note')) {
            $locked = true;
            $lockType = 'video_note';
        }

        // قفل لینک
        if (!$locked && isset($message['text']) && $this->lockManager->isLocked($chatId, 'link')) {
            $text = $message['text'] ?? '';
            if ($this->containsLink($text)) {
                $locked = true;
                $lockType = 'link';
            }
        }

        // قفل تگ
        if (!$locked && isset($message['text']) && $this->lockManager->isLocked($chatId, 'tag')) {
            $text = $message['text'] ?? '';
            if ($this->containsTag($text)) {
                $locked = true;
                $lockType = 'tag';
            }
        }

        // ✅ قفل هشتگ (جدید)
        if (!$locked && isset($message['text']) && $this->lockManager->isLocked($chatId, 'hashtag')) {
            $text = $message['text'] ?? '';
            if ($this->containsHashtag($text)) {
                $locked = true;
                $lockType = 'hashtag';
            }
        }

        if ($locked) {
            // حذف پیام
            $this->telegramApi->deleteMessage($chatId, $messageId);
            
            // ارسال پیام هشدار
            $typeNames = [
                'text' => $this->langHelper->t('text', [], $lang),
                'photo' => $this->langHelper->t('photo', [], $lang),
                'video' => $this->langHelper->t('video', [], $lang),
                'gif' => $this->langHelper->t('gif', [], $lang),
                'sticker' => $this->langHelper->t('sticker', [], $lang),
                'voice' => $this->langHelper->t('voice', [], $lang),
                'video_note' => $this->langHelper->t('video_note', [], $lang),
                'link' => $this->langHelper->t('link', [], $lang),
                'tag' => $this->langHelper->t('tag', [], $lang),
                'hashtag' => $this->langHelper->t('hashtag', [], $lang),
            ];
            
            $typeName = $typeNames[$lockType] ?? $lockType;
            $warningText = $this->langHelper->t('content_locked', ['{type}' => $typeName], $lang);
            
            $this->telegramApi->sendMessage($chatId, $warningText, $messageId);
            $this->logger->info("Locked message ($lockType) deleted in chat $chatId from user $userId");
            return true;
        }

        return false;
    }

    /**
     * بررسی وجود لینک در متن
     */
    private function containsLink($text): bool
    {
        if (empty($text)) {
            return false;
        }
        $pattern = '/(https?:\/\/[^\s]+|t\.me\/[^\s]+|telegram\.me\/[^\s]+)/i';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * بررسی وجود تگ (منشن) در متن
     */
    private function containsTag($text): bool
    {
        if (empty($text)) {
            return false;
        }
        $pattern = '/@[a-zA-Z0-9_]{5,32}/';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * بررسی وجود هشتگ در متن (پشتیبانی از فارسی)
     */
    private function containsHashtag($text): bool
    {
        if (empty($text)) {
            return false;
        }
        $pattern = '/#[\w\x{0600}-\x{06FF}]+/u';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * تشخیص دستور فارسی
     */
    private function isPersianCommand($text): bool
    {
        $persianCommands = array_keys($this->config['command_map']);
        foreach ($persianCommands as $cmd) {
            if (strpos($cmd, '/') !== 0 && strpos($text, $cmd) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * پردازش دستور دریافتی
     */
    private function processCommand($message, string $lang = 'fa'): void
    {
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];

        // استخراج نام دستور و پارامترها
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $params = $parts[1] ?? '';

        // بررسی مجوز دستور
        if (!$this->authorizationManager->canExecute($chatId, $userId, $command)) {
            $this->telegramApi->sendMessage(
                $chatId,
                $this->langHelper->t('no_permission', [], $lang)
            );
            return;
        }

        // لاگ دستور
        $this->commandLogger->log($chatId, $userId, $command, $params);

        // اجرای ماژول
        $this->moduleManager->runModule($command, ['message' => $message], $chatId, $userId, $params);
    }

    /**
     * پردازش Callback Query (برای دکمه‌های اینلاین)
     */
    private function handleCallback($callback): void
    {
        $callbackId = $callback['id'];
        $chatId = $callback['message']['chat']['id'] ?? 0;
        $userId = $callback['from']['id'] ?? 0;
        $data = $callback['data'] ?? '';

        // پردازش داده‌های callback
        $this->logger->debug("Callback received: $data from user $userId");

        // پاسخ به callback
        $this->telegramApi->answerCallbackQuery([
            'callback_query_id' => $callbackId,
            'text' => $this->langHelper->t('operation_success', [], 'fa'),
        ]);
    }

    // ========================
    // متدهای Getter برای دسترسی سایر کلاس‌ها
    // ========================

    public function getTelegramApi()
    {
        return $this->telegramApi;
    }

    public function getLockManager()
    {
        return $this->lockManager;
    }

    public function getMuteManager()
    {
        return $this->muteManager;
    }

    public function getWarningManager()
    {
        return $this->warningManager;
    }

    public function getAuthorizationManager()
    {
        return $this->authorizationManager;
    }

    public function getAdminManager()
    {
        return $this->adminManager;
    }

    public function getWelcomeManager()
    {
        return $this->welcomeManager;
    }

    public function getMessageLogger()
    {
        return $this->messageLogger;
    }

    public function getCommandLogger()
    {
        return $this->commandLogger;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getLangHelper()
    {
        return $this->langHelper;
    }

    public function getConfig()
    {
        return $this->config;
    }
}