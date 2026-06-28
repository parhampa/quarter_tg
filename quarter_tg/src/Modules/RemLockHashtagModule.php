<?php

namespace Modules;

use Core\ModuleInterface;
use Helpers\TelegramApi;

class RemLockHashtagModule implements ModuleInterface
{
    private $telegram;
    private $lockManager;
    private $authManager;

    public function __construct($telegram, $lockManager, $authManager)
    {
        $this->telegram = $telegram;
        $this->lockManager = $lockManager;
        $this->authManager = $authManager;
    }

    public function handle($update)
    {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $reply_to = $message['message_id'];

        // بررسی ادمین بودن
        if (!$this->authManager->isAdmin($chat_id, $user_id)) {
            $this->telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => '⛔️ شما دسترسی ادمین ندارید.',
                'reply_to_message_id' => $reply_to
            ]);
            return;
        }

        // غیرفعال کردن قفل
        $this->lockManager->toggleHashtagLock($chat_id, false);

        $this->telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => '🔓 قفل هشتگ (هشتگ) غیرفعال شد. کاربران می‌توانند هشتگ ارسال کنند.',
            'reply_to_message_id' => $reply_to
        ]);
    }
}