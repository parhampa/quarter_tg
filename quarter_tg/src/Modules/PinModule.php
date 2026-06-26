<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class PinModule
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
                ? "❌ لطفاً به پیامی که می‌خواهید پین کنید ریپلای کنید."
                : "❌ Please reply to the message you want to pin.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        $replyMsgId = $replyToMessage['message_id'];

        $result = $api->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $replyMsgId,
            'disable_notification' => false,
        ]);

        if ($result && $result['ok']) {
            $msg = $lang === 'fa'
                ? "📌 پیام با موفقیت پین شد."
                : "📌 Message pinned successfully.";
            $api->sendMessage($chatId, $msg, $msgId);
        } else {
            $error = $result['description'] ?? 'Unknown error';
            $msg = $lang === 'fa'
                ? "❌ پین کردن پیام با خطا مواجه شد: {$error}"
                : "❌ Failed to pin message: {$error}";
            $api->sendMessage($chatId, $msg, $msgId);
        }
    }
}