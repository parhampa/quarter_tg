<?php
namespace Modules;

use Helpers\TelegramApi;

class StatsModule
{
    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $api->sendMessage($chatId, "📊 Group statistics module (sample).", $msgId);
    }
}