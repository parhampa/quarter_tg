<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class UnlockMessageModule
{
    private $lockManager;

    public function __construct()
    {
        global $lockManager;
        $this->lockManager = $lockManager;
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

        $this->lockManager->setLock($chatId, 'messages', false);

        $response = $lang === 'fa'
            ? "🔓 قفل پیام‌های متنی غیرفعال شد. همه کاربران می‌توانند پیام ارسال کنند."
            : "🔓 Text message lock disabled. All users can send messages.";
        $api->sendMessage($chatId, $response, $msgId);
    }
}