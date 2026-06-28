<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\WelcomeManager;

/**
 * ماژول فعال‌سازی و تنظیم پیام خوش‌آمدگویی
 */
class WelcomeModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $welcomeManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        WelcomeManager $welcomeManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->welcomeManager = $welcomeManager;
    }

    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // فقط ادمین‌ها
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه تغییر پیام خوش‌آمدگویی را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // اگر پارامتر وجود دارد، به عنوان پیام خوش‌آمدگویی تنظیم می‌شود
        if (!empty($params)) {
            $welcomeMessage = trim($params);
            $result = $this->welcomeManager->setMessage($chatId, $welcomeMessage);
            if ($result) {
                $this->welcomeManager->setEnabled($chatId, true);
                $this->telegram->sendMessage(
                    $chatId,
                    "✅ پیام خوش‌آمدگویی با موفقیت تنظیم و فعال شد.\n\n📝 پیام:\n{$welcomeMessage}",
                    $message['message_id'] ?? null
                );
                $this->logger->info("Welcome message set in group $chatId by $userId");
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ تنظیم پیام خوش‌آمدگویی با خطا مواجه شد.",
                    $message['message_id'] ?? null
                );
            }
            return;
        }

        // بدون پارامتر: فقط فعال می‌کند
        $currentSettings = $this->welcomeManager->getSettings($chatId);
        
        if ($currentSettings['enabled'] && !empty($currentSettings['message'])) {
            $this->telegram->sendMessage(
                $chatId,
                "✅ پیام خوش‌آمدگویی در حال حاضر فعال است.\n"
                . "📝 پیام فعلی:\n{$currentSettings['message']}\n\n"
                . "برای تغییر پیام: `/sayhello پیام جدید`\n"
                . "برای غیرفعال‌سازی: `/remsayhello`",
                $message['message_id'] ?? null
            );
            return;
        }

        // اگر پیام وجود ندارد، اما فعال است (غیرمعمول)
        if ($currentSettings['enabled'] && empty($currentSettings['message'])) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ پیام خوش‌آمدگویی فعال است اما پیامی تنظیم نشده است.\n"
                . "برای تنظیم پیام: `/sayhello متن پیام`",
                $message['message_id'] ?? null
            );
            return;
        }

        // غیرفعال است
        $this->telegram->sendMessage(
            $chatId,
            "❌ پیام خوش‌آمدگویی غیرفعال است.\n"
            . "برای فعال‌سازی و تنظیم پیام: `/sayhello متن پیام`",
            $message['message_id'] ?? null
        );
    }

    public static function getDescription(): string
    {
        return "فعال‌سازی و تنظیم پیام خوش‌آمدگویی / Enable and set welcome message";
    }
}