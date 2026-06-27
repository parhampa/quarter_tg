<?php

namespace QuarterTg\Modules;

use QuarterTg\Core\ModuleManager;

class RemLockTagModule extends ModuleManager
{
    public function execute($message, $params)
    {
        $chatId = $message['chat']['id'];
        $this->lockManager->setLock($chatId, 'tag', false);
        $this->telegramApi->sendMessage(
            $chatId,
            "🔓 قفل تگ برداشته شد.\nکاربران می‌توانند تگ ارسال کنند."
        );
    }
}