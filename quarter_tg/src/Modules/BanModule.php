<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class BanModule
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
        $chatTitle = $message['chat']['title'] ?? '';

        if ($chatType !== 'group' && $chatType !== 'supergroup') {
            $api->sendMessage($chatId, "❌ This command can only be used in groups.", $msgId);
            return;
        }

        $lang = LanguageHelper::getLanguageFromCommand($command);
        if ($lang === 'en' && LanguageHelper::isPersianText($message['text'] ?? '')) {
            $lang = 'fa';
        }

        $targetUserId = null;
        $targetUsername = null;
        $targetFirstName = null;
        $targetLastName = null;

        // 1) از آرگومان (یوزرنیم یا آیدی عددی)
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
            // برای دریافت نام کاربر از API (اختیاری)
            $chatInfo = $api->request('getChat', ['chat_id' => $target]);
            if ($chatInfo && isset($chatInfo['result'])) {
                $targetFirstName = $chatInfo['result']['first_name'] ?? '';
                $targetLastName = $chatInfo['result']['last_name'] ?? '';
                $targetUsername = $chatInfo['result']['username'] ?? '';
            }
        } else {
            // 2) از ریپلای
            $targetUserId = $api->getUserIdFromReply($update);
            if ($targetUserId === null) {
                $msg = $lang === 'fa'
                    ? "❌ لطفاً به پیام کاربری که می‌خواهید بن کنید ریپلای کنید یا @username او را وارد کنید."
                    : "❌ Please reply to the user's message you want to ban or provide @username.";
                $api->sendMessage($chatId, $msg, $msgId);
                return;
            }
            // دریافت اطلاعات کاربر از ریپلای
            $replyTo = $message['reply_to_message'] ?? null;
            if ($replyTo && isset($replyTo['from'])) {
                $targetFirstName = $replyTo['from']['first_name'] ?? '';
                $targetLastName = $replyTo['from']['last_name'] ?? '';
                $targetUsername = $replyTo['from']['username'] ?? '';
            }
        }

        // جلوگیری از بن خود ربات
        $botInfo = $api->request('getMe');
        $botId = $botInfo && isset($botInfo['result']['id']) ? (int)$botInfo['result']['id'] : 0;
        if ($targetUserId == $botId) {
            $msg = $lang === 'fa'
                ? "❌ نمی‌توانید خود ربات را بن کنید!"
                : "❌ You cannot ban the bot itself!";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        // بررسی اینکه کاربر هدف عضو گروه است
        $member = $api->getChatMember($chatId, $targetUserId);
        if (!$member || $member['status'] === 'left' || $member['status'] === 'kicked') {
            $msg = $lang === 'fa'
                ? "❌ کاربر عضو این گروه نیست یا قبلاً بن شده است."
                : "❌ User is not a member of this group or already banned.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        // تکمیل اطلاعات کاربر در صورت نبودن
        if (empty($targetFirstName) && isset($member['user'])) {
            $targetFirstName = $member['user']['first_name'] ?? '';
            $targetLastName = $member['user']['last_name'] ?? '';
            $targetUsername = $member['user']['username'] ?? '';
        }

        // اجرای بن
        $result = $api->request('kickChatMember', [
            'chat_id' => $chatId,
            'user_id' => $targetUserId,
        ]);

        if ($result && $result['ok']) {
            // ---------- ذخیره در دیتابیس ----------
            $adminId = $message['from']['id'] ?? 0;
            $adminUsername = $message['from']['username'] ?? '';
            $adminFirstName = $message['from']['first_name'] ?? '';
            $adminLastName = $message['from']['last_name'] ?? '';
            $adminName = trim($adminFirstName . ' ' . $adminLastName);

            $targetName = trim($targetFirstName . ' ' . $targetLastName);

            $sql = "INSERT INTO bot_bans (
                user_id, username, first_name, last_name,
                banned_by, banned_by_username, banned_by_name,
                group_id, group_title, banned_at
            ) VALUES (
                {$targetUserId}, '{$this->db->escapeString($targetUsername)}',
                '{$this->db->escapeString($targetFirstName)}',
                '{$this->db->escapeString($targetLastName)}',
                {$adminId}, '{$this->db->escapeString($adminUsername)}',
                '{$this->db->escapeString($adminName)}',
                {$chatId}, '{$this->db->escapeString($chatTitle)}',
                " . (time() * 1000) . "
            ) ON DUPLICATE KEY UPDATE
                username = '{$this->db->escapeString($targetUsername)}',
                first_name = '{$this->db->escapeString($targetFirstName)}',
                last_name = '{$this->db->escapeString($targetLastName)}',
                banned_by = {$adminId},
                banned_by_username = '{$this->db->escapeString($adminUsername)}',
                banned_by_name = '{$this->db->escapeString($adminName)}',
                group_title = '{$this->db->escapeString($chatTitle)}',
                updated_at = CURRENT_TIMESTAMP";

            $this->db->execute($sql);
            // ---------- پایان ذخیره در دیتابیس ----------

            $mention = $targetUsername ? "@{$targetUsername}" : "<a href='tg://user?id={$targetUserId}'>{$targetName}</a>";
            $msg = $lang === 'fa'
                ? "✅ کاربر {$mention} با موفقیت از گروه بن شد."
                : "✅ User {$mention} has been banned from the group.";
            $api->sendMessage($chatId, $msg, $msgId, 'HTML');
        } else {
            $error = $result['description'] ?? 'Unknown error';
            $msg = $lang === 'fa'
                ? "❌ بن کردن کاربر با خطا مواجه شد: {$error}"
                : "❌ Failed to ban user: {$error}";
            $api->sendMessage($chatId, $msg, $msgId);
        }
    }
}