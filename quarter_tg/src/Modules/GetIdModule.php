<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class GetIdModule
{
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

        $replyToMessage = $message['reply_to_message'] ?? null;
        if (!$replyToMessage) {
            $msg = $lang === 'fa'
                ? "❌ لطفاً به پیام کاربری که می‌خواهید آیدی او را ببینید ریپلای کنید."
                : "❌ Please reply to the user's message to get their ID.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        $from = $replyToMessage['from'] ?? null;
        if (!$from) {
            $msg = $lang === 'fa'
                ? "❌ اطلاعات کاربر یافت نشد."
                : "❌ User information not found.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        $userId = $from['id'];
        $username = $from['username'] ?? '';
        $firstName = $from['first_name'] ?? '';
        $lastName = $from['last_name'] ?? '';
        $fullName = trim("$firstName $lastName");

        $response = $lang === 'fa'
            ? "🆔 آیدی کاربر:\n"
            : "🆔 User ID:\n";
        $response .= "ID: <code>{$userId}</code>\n";
        if ($username) {
            $response .= "Username: @{$username}\n";
        }
        if ($fullName) {
            $response .= "Name: {$fullName}\n";
        }

        $api->sendMessage($chatId, $response, $msgId);
    }
}