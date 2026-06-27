<?php

namespace QuarterTg\Core;

use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Helpers\LanguageHelper;

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
    private $config;

    public function __construct($config, $database, $cache, $logger)
    {
        $this->config = $config;
        $this->database = $database;
        $this->cache = $cache;
        $this->logger = $logger;

        $this->telegramApi = new TelegramApi($config['bot_token']);
        $this->lockManager = new LockManager($database, $cache);
        $this->muteManager = new MuteManager($database, $cache);
        $this->warningManager = new WarningManager($database, $cache);
        $this->authorizationManager = new AuthorizationManager($database, $cache);
        $this->adminManager = new AdminManager($database, $cache);
        $this->welcomeManager = new WelcomeManager($database, $cache);
        $this->messageLogger = new MessageLogger($database);
        $this->commandLogger = new CommandLogger($database);
        $this->moduleManager = new ModuleManager($config['command_map'], $this);
    }

    /**
     * پردازش پیام دریافتی از تلگرام
     */
    public function handleRequest($update)
    {
        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'] ?? null;

        // لاگ کردن پیام
        $this->messageLogger->log($message);

        // بررسی سکوت کاربر
        if ($this->muteManager->isMuted($chatId, $userId)) {
            $this->telegramApi->deleteMessage($chatId, $message['message_id']);
            return;
        }

        // بررسی قفل‌های گروه
        if ($this->checkLocks($message)) {
            return; // پیام قفل شده و حذف گردید
        }

        // پردازش دستورات (Command)
        $text = $message['text'] ?? '';
        if (strpos($text, '/') === 0 || $this->isPersianCommand($text)) {
            $this->processCommand($message);
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
    private function checkLocks($message)
    {
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];
        $userId = $message['from']['id'] ?? null;

        // مدیران از قفل‌ها مستثنی هستند
        if ($this->authorizationManager->isAdmin($chatId, $userId)) {
            return false;
        }

        $locked = false;

        // قفل متن
        if (isset($message['text']) && $this->lockManager->isLocked($chatId, 'text')) {
            $locked = true;
        }

        // قفل عکس
        elseif (isset($message['photo']) && $this->lockManager->isLocked($chatId, 'photo')) {
            $locked = true;
        }

        // قفل فیلم
        elseif (isset($message['video']) && $this->lockManager->isLocked($chatId, 'video')) {
            $locked = true;
        }

        // قفل GIF
        elseif (isset($message['animation']) && $this->lockManager->isLocked($chatId, 'gif')) {
            $locked = true;
        }

        // قفل استیکر
        elseif (isset($message['sticker']) && $this->lockManager->isLocked($chatId, 'sticker')) {
            $locked = true;
        }

        // قفل ویس
        elseif (isset($message['voice']) && $this->lockManager->isLocked($chatId, 'voice')) {
            $locked = true;
        }

        // قفل ویدئو مسیج
        elseif (isset($message['video_note']) && $this->lockManager->isLocked($chatId, 'video_note')) {
            $locked = true;
        }

        // ✅ قفل لینک (جدید)
        if (!$locked && isset($message['text']) && $this->lockManager->isLocked($chatId, 'link')) {
            $text = $message['text'] ?? '';
            if ($this->containsLink($text)) {
                $locked = true;
            }
        }

        if ($locked) {
            // حذف پیام
            $this->telegramApi->deleteMessage($chatId, $messageId);
            // (اختیاری) اطلاع به کاربر
            $this->telegramApi->sendMessage(
                $chatId,
                "⛔ ارسال این نوع محتوا در گروه ممنوع است.",
                null,
                $messageId
            );
            $this->logger->info("Locked message deleted in chat $chatId from user $userId");
            return true;
        }

        return false;
    }

    /**
     * بررسی وجود لینک در متن
     */
    private function containsLink($text)
    {
        if (empty($text)) {
            return false;
        }
        $pattern = '/(https?:\/\/[^\s]+|t\.me\/[^\s]+|telegram\.me\/[^\s]+)/i';
        return preg_match($pattern, $text) === 1;
    }

    /**
     * تشخیص دستور فارسی
     */
    private function isPersianCommand($text)
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
    private function processCommand($message)
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
                "⛔ شما دسترسی به این دستور را ندارید."
            );
            return;
        }

        // لاگ دستور
        $this->commandLogger->log($chatId, $userId, $command, $params);

        // اجرای ماژول
        $moduleName = $this->moduleManager->getModuleName($command);
        if ($moduleName) {
            $module = $this->moduleManager->loadModule($moduleName);
            if ($module) {
                $module->execute($message, $params);
            }
        } else {
            $this->telegramApi->sendMessage(
                $chatId,
                "❌ دستور ناشناخته. برای راهنمایی /help را وارد کنید."
            );
        }
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

    public function getConfig()
    {
        return $this->config;
    }
}