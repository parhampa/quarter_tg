<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class StartModule
{
    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $lang = LanguageHelper::getLanguageFromCommand($command);
        $text = $lang === 'fa'
            ? "👋 خوش آمدید! من یک ربات مدیریتی هستم. از /help برای راهنما استفاده کنید."
            : "👋 Welcome! I'm a management bot. Use /help for commands.";
        $api->sendMessage($chatId, $text, $msgId);
    }
}