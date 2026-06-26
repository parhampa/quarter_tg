<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class UnbanModule
{
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
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

        $targetUserId = null;

        if (!empty($args)) {
            $target = $args[0];
            $targetUserId = $api->resolveUserId($target);
            if ($targetUserId === null) {
                $msg = $lang === 'fa'
                    ? "❌ کاربر یافت نشد. لطفاً یک @username معتبر یا شناسه عددی وارد کنید."
                    : "❌ User not found. Please provide a valid @username or numeric ID.";
                $api->sendMessage($chatId, $msg, $msgId);
                return;
            }
        } else {
            $targetUserId = $api->getUserIdFromReply($update);
            if ($targetUserId === null) {
                $msg = $lang === 'fa'
                    ? "❌ لطفاً به پیام کاربری که می‌خواهید آن‌بن کنید ریپلای کنید یا @username او را وارد کنید."
                    : "❌ Please reply to the user's message you want to unban or provide @username.";
                $api->sendMessage($chatId, $msg, $msgId);
                return;
            }
        }

        // بررسی اینکه کاربر قبلاً در جدول بن برای این گروه وجود دارد (اختیاری)
        $checkSql = "SELECT 1 FROM bot_bans WHERE user_id = {$targetUserId} AND group_id = {$chatId}";
        $exists = $this->db->fetchOne($checkSql);

        // اجرای آن‌بن (حتی اگر در دیتابیس نباشد، تلگرام آن‌بن می‌کند)
        $result = $api->request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $targetUserId,
        ]);

        if ($result && $result['ok']) {
            // حذف از دیتابیس
            if ($exists) {
                $deleteSql = "DELETE FROM bot_bans WHERE user_id = {$targetUserId} AND group_id = {$chatId}";
                $this->db->execute($deleteSql);
            }

            $msg = $lang === 'fa'
                ? "✅ کاربر <code>{$targetUserId}</code> از حالت بن خارج شد و می‌تواند مجدداً به گروه ملحق شود."
                : "✅ User <code>{$targetUserId}</code> has been unbanned and can join the group again.";
            $api->sendMessage($chatId, $msg, $msgId, 'HTML');
        } else {
            $error = $result['description'] ?? 'Unknown error';
            $msg = $lang === 'fa'
                ? "❌ حذف بن با خطا مواجه شد: {$error}"
                : "❌ Failed to unban user: {$error}";
            $api->sendMessage($chatId, $msg, $msgId);
        }
    }
}