<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class AddAdminModule
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
            'user_not_found' => [
                'en' => "❌ User not found. Please provide a valid @username or numeric ID.",
                'fa' => "❌ کاربر یافت نشد. لطفاً یک @username معتبر یا شناسه عددی وارد کنید.",
            ],
            'provide_input' => [
                'en' => "❌ Please provide a username/ID or reply to a user's message.\nExample: <code>/addadmin @username</code>",
                'fa' => "❌ لطفاً یک نام کاربری/شناسه وارد کنید یا به پیام کاربر ریپلای کنید.\nمثال: <code>ست ادمین @username</code>",
            ],
            'cannot_add_bot' => [
                'en' => "❌ Cannot add the bot itself as an admin.",
                'fa' => "❌ نمی‌توان خود ربات را به عنوان ادمین اضافه کرد.",
            ],
            'not_member' => [
                'en' => "❌ User is not a member of this group.",
                'fa' => "❌ کاربر عضو این گروه نیست.",
            ],
            'already_admin' => [
                'en' => "⚠️ User is already an admin for this group.",
                'fa' => "⚠️ کاربر قبلاً به عنوان ادمین این گروه ثبت شده است.",
            ],
            'success' => [
                'en' => "✅ User <b>{user}</b> has been added as admin for this group.",
                'fa' => "✅ کاربر <b>{user}</b> به عنوان ادمین این گروه اضافه شد.",
            ],
        ];

        if ($chatType !== 'group' && $chatType !== 'supergroup') {
            $api->sendMessage($chatId, $messages['only_groups'][$lang], $msgId);
            return;
        }

        $targetUserId = null;

        if (!empty($args)) {
            $target = $args[0];
            $targetUserId = $api->resolveUserId($target);
            if ($targetUserId === null) {
                $api->sendMessage($chatId, $messages['user_not_found'][$lang], $msgId);
                return;
            }
        } else {
            $targetUserId = $api->getUserIdFromReply($update);
            if ($targetUserId === null) {
                $api->sendMessage($chatId, $messages['provide_input'][$lang], $msgId);
                return;
            }
        }

        $botUser = $api->request('getMe');
        if ($botUser && isset($botUser['result']['id']) && $botUser['result']['id'] == $targetUserId) {
            $api->sendMessage($chatId, $messages['cannot_add_bot'][$lang], $msgId);
            return;
        }

        $member = $api->getChatMember($chatId, $targetUserId);
        if (!$member || $member['status'] === 'left' || $member['status'] === 'kicked') {
            $api->sendMessage($chatId, $messages['not_member'][$lang], $msgId);
            return;
        }

        $success = $this->adminManager->addAdmin($targetUserId, $chatId);
        if ($success) {
            $response = str_replace('{user}', $targetUserId, $messages['success'][$lang]);
            $api->sendMessage($chatId, $response, $msgId);
        } else {
            $api->sendMessage($chatId, $messages['already_admin'][$lang], $msgId);
        }
    }
}