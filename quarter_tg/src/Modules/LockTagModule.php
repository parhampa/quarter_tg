<?php

namespace QuarterTg\Modules;

use QuarterTg\Core\ModuleManager;

class LockTagModule extends ModuleManager
{
    public function execute($message, $params)
    {
        $chatId = $message['chat']['id'];
        $this->lockManager->setLock($chatId, 'tag', true);
        $this->telegramApi->sendMessage(
            $chatId,
            "🔒 قفل تگ فعال شد.\nکاربران عادی نمی‌توانند هیچ تگی (مانند @username) ارسال کنند."
        );
    }
}