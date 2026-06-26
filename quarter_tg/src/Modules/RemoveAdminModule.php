<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class RemoveAdminModule
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
                'en' => "❌ Please provide a username/ID or reply to a user's message.\nExample: <code>/remadmin @username</code>",
                'fa' => "❌ لطفاً یک نام کاربری/شناسه وارد کنید یا به پیام کاربر ریپلای کنید.\nمثال: <code>حذف ادمین @username</code>",
            ],
            'cannot_remove_self' => [
                'en' => "❌ You cannot remove yourself from admins.",
                'fa' => "❌ نمی‌توانید خودتان را از ادمینی حذف کنید.",
            ],
            'not_admin' => [
                'en' => "⚠️ User is not an admin for this group.",
                'fa' => "⚠️ کاربر ادمین این گروه نیست.",
            ],
            'failed' => [
                'en' => "❌ Failed to remove user. Please try again.",
                'fa' => "❌ حذف کاربر با شکست مواجه شد. لطفاً دوباره تلاش کنید.",
            ],
            'success' => [
                'en' => "✅ User <b>{user}</b> has been removed from admins for this group.",
                'fa' => "✅ کاربر <b>{user}</b> از ادمینی این گروه حذف شد.",
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

        $currentUserId = $message['from']['id'] ?? null;
        if ($targetUserId == $currentUserId) {
            $api->sendMessage($chatId, $messages['cannot_remove_self'][$lang], $msgId);
            return;
        }

        if (!$this->adminManager->isAdminOfGroup($targetUserId, $chatId)) {
            $api->sendMessage($chatId, $messages['not_admin'][$lang], $msgId);
            return;
        }

        $success = $this->adminManager->removeAdmin($targetUserId, $chatId);
        if ($success) {
            $response = str_replace('{user}', $targetUserId, $messages['success'][$lang]);
            $api->sendMessage($chatId, $response, $msgId);
        } else {
            $api->sendMessage($chatId, $messages['failed'][$lang], $msgId);
        }
    }
}