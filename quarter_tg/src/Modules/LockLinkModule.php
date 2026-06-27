<?php

namespace QuarterTg\Modules;

use QuarterTg\Core\ModuleManager;

class LockLinkModule extends ModuleManager
{
    public function execute($message, $params)
    {
        $chatId = $message['chat']['id'];
        $this->lockManager->setLock($chatId, 'link', true);
        $this->telegramApi->sendMessage(
            $chatId,
            "🔒 قفل لینک فعال شد.\nکاربران عادی نمی‌توانند هیچ لینکی ارسال کنند."
        );
    }
}