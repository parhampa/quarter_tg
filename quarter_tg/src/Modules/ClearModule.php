<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class ClearModule
{
    private $commandLogger;

    public function __construct()
    {
        global $commandLogger;
        $this->commandLogger = $commandLogger;
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

        // --- 24‑hour cooldown check ---
        $lastTime = $this->commandLogger->getLastClearTime($chatId);
        $now = time() * 1000; // milliseconds
        $cooldown = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

        if ($lastTime !== null && ($now - $lastTime) < $cooldown) {
            $remaining = ($cooldown - ($now - $lastTime)) / 1000; // seconds
            $hours = floor($remaining / 3600);
            $minutes = floor(($remaining % 3600) / 60);
            $seconds = floor($remaining % 60);

            $msg = $lang === 'fa'
                ? "⏳ این دستور فقط هر ۲۴ ساعت یک بار قابل استفاده است.\n"
                : "⏳ This command can only be used once every 24 hours.\n";
            $msg .= $lang === 'fa'
                ? "زمان باقی‌مانده: {$hours} ساعت {$minutes} دقیقه {$seconds} ثانیه"
                : "Remaining time: {$hours}h {$minutes}m {$seconds}s";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }
        // --- End cooldown check ---

        // Send processing message
        $processingMsg = $lang === 'fa'
            ? "🔄 در حال پاکسازی ۵۰۰۰ پیام آخر گروه..."
            : "🔄 Clearing last 5000 messages...";
        $statusMsg = $api->sendMessage($chatId, $processingMsg, $msgId);
        if (!$statusMsg || !isset($statusMsg['result']['message_id'])) {
            $api->sendMessage($chatId, "❌ Failed to send status message.", $msgId);
            return;
        }
        $statusMsgId = $statusMsg['result']['message_id'];

        $deletedCount = 0;
        $limit = 5000;
        $offset = 0;
        $maxIterations = 50;

        for ($i = 0; $i < $maxIterations; $i++) {
            // Telegram Bot API does NOT have getChatHistory.
            // This is a mock implementation. In reality, you need to use
            // a userbot or a different approach. For now, we simulate.
            // We'll delete the status and command messages as a demo.
            break;
        }

        // Delete status and command messages
        $api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $statusMsgId]);
        $api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);

        $finalMsg = $lang === 'fa'
            ? "⚠️ پاکسازی انبوه توسط API تلگرام پشتیبانی نمی‌شود.\nبرای حذف پیام‌ها، روی هر پیام ریپلای کنید و دستور <code>حذف</code> را بزنید."
            : "⚠️ Bulk clearing is not supported by Telegram Bot API.\nTo delete messages, reply to each message with <code>/del</code>.";
        $api->sendMessage($chatId, $finalMsg);
    }
}