<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class ListAdminsModule
{
    private $adminManager;

    public function __construct()
    {
        global $adminManager;
        $this->adminManager = $adminManager;
    }

    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];
        $msgId = $message['message_id'];
        $chatType = $message['chat']['type'] ?? '';

        $lang = LanguageHelper::getLanguageFromCommand($command);
        if ($lang === 'en' && LanguageHelper::isPersianText($message['text'] ?? '')) {
            $lang = 'fa';
        }

        $messages = [
            'only_groups' => [
                'en' => "❌ This command can only be used in groups.",
                'fa' => "❌ این دستور فقط در گروه قابل اجراست.",
            ],
            'no_admins' => [
                'en' => "📋 No admins registered for this group.",
                'fa' => "📋 هیچ ادمینی برای این گروه ثبت نشده است.",
            ],
            'header' => [
                'en' => "📋 <b>Admins for this group:</b>\n",
                'fa' => "📋 <b>ادمین‌های این گروه:</b>\n",
            ],
        ];

        if ($chatType !== 'group' && $chatType !== 'supergroup') {
            $api->sendMessage($chatId, $messages['only_groups'][$lang], $msgId);
            return;
        }

        $admins = $this->adminManager->getAdminsByGroup($chatId);
        if (empty($admins)) {
            $api->sendMessage($chatId, $messages['no_admins'][$lang], $msgId);
            return;
        }

        $list = $messages['header'][$lang];
        $count = 1;
        foreach ($admins as $id) {
            $list .= "{$count}. <code>{$id}</code>\n";
            $count++;
        }

        $api->sendMessage($chatId, $list, $msgId);
    }
}