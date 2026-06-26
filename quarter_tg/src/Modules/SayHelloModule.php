<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class SayHelloModule
{
    private $welcomeManager;

    public function __construct()
    {
        global $welcomeManager;
        $this->welcomeManager = $welcomeManager;
    }

    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];
        $msgId = $message['message_id'];
        $chatType = $message['chat']['type'] ?? '';

        if ($chatType !== 'group' && $chatType !== 'supergroup') {
            $api->sendMessage($chatId, "❌ This command can only be used in groups.", $msgId);
            return;
        }

        $lang = LanguageHelper::getLanguageFromCommand($command);
        if ($lang === 'en' && LanguageHelper::isPersianText($message['text'] ?? '')) {
            $lang = 'fa';
        }

        $this->welcomeManager->enableWelcome($chatId, $lang);

        $response = $lang === 'fa'
            ? "✅ پیام خوش‌آمدگویی برای این گروه فعال شد."
            : "✅ Welcome message has been enabled for this group.";
        $api->sendMessage($chatId, $response, $msgId);
    }
}