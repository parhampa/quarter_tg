<?php

namespace QuarterTg\Modules;

use QuarterTg\Core\ModuleManager;

class RemLockLinkModule extends ModuleManager
{
    public function execute($message, $params)
    {
        $chatId = $message['chat']['id'];
        $this->lockManager->setLock($chatId, 'link', false);
        $this->telegramApi->sendMessage(
            $chatId,
            "🔓 قفل لینک برداشته شد.\nکاربران می‌توانند لینک ارسال کنند."
        );
    }
}