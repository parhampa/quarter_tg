<?php
namespace Modules;

use Helpers\TelegramApi;

class OwnerModule
{
    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $api->sendMessage($chatId, "👑 Owner-only command executed.", $msgId);
    }
}