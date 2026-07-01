<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\WelcomeManager;

/**
 * ماژول غیرفعال‌سازی پیام خوش‌آمدگویی
 */
class RemWelcomeModule
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

        // غیرفعال‌سازی
        $result = $this->welcomeManager->setEnabled($chatId, false);

        if ($result) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ پیام خوش‌آمدگویی با موفقیت غیرفعال شد.",
                $message['message_id'] ?? null
            );
            $this->logger->info("Welcome message disabled in group $chatId by $userId");
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "❌ غیرفعال‌سازی پیام خوش‌آمدگویی با خطا مواجه شد.",
                $message['message_id'] ?? null
            );
        }
    }

    public static function getDescription(): string
    {
        return "غیرفعال‌سازی پیام خوش‌آمدگویی / Disable welcome message";
    }
}