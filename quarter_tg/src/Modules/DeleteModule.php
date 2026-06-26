<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class DeleteModule
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
                ? "❌ لطفاً به پیامی که می‌خواهید حذف کنید ریپلای کنید."
                : "❌ Please reply to the message you want to delete.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        $targetMsgId = $replyToMessage['message_id'];

        $result = $api->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $targetMsgId,
        ]);

        if ($result && $result['ok']) {
            $api->request('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $msgId,
            ]);
        } else {
            $error = $result['description'] ?? 'Unknown error';
            $msg = $lang === 'fa'
                ? "❌ حذف پیام با خطا مواجه شد: {$error}"
                : "❌ Failed to delete message: {$error}";
            $api->sendMessage($chatId, $msg, $msgId);
        }
    }
}