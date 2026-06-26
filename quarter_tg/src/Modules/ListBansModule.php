<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class ListBansModule
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

        // دریافت لیست بن‌ها از دیتابیس برای این گروه
        $sql = "SELECT user_id, username, first_name, last_name, banned_by_name, banned_at
                FROM bot_bans
                WHERE group_id = {$chatId}
                ORDER BY banned_at DESC";
        $rows = $this->db->fetchAll($sql);

        if (empty($rows)) {
            $msg = $lang === 'fa'
                ? "📋 هیچ کاربر بن‌شده‌ای برای این گروه وجود ندارد."
                : "📋 No banned users for this group.";
            $api->sendMessage($chatId, $msg, $msgId);
            return;
        }

        // ساخت لیست
        $list = $lang === 'fa'
            ? "📋 <b>لیست کاربران بن‌شده در این گروه:</b>\n\n"
            : "📋 <b>Banned users in this group:</b>\n\n";

        $count = 1;
        foreach ($rows as $row) {
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            $username = $row['username'] ? "@{$row['username']}" : '(بدون یوزرنیم)';
            $bannedBy = $row['banned_by_name'] ?: $row['banned_by'];
            $date = date('Y-m-d H:i:s', $row['banned_at'] / 1000);

            $list .= "{$count}. <b>{$name}</b> ({$username})\n";
            $list .= "   🚫 توسط: {$bannedBy}\n";
            $list .= "   📅 تاریخ: {$date}\n\n";
            $count++;
        }

        $api->sendMessage($chatId, $list, $msgId, 'HTML');
    }
}