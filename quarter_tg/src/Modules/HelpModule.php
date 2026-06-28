<?php

namespace Modules;

class HelpModule
{
    private $telegram;
    private $db;
    private $logger;

    public function __construct($telegram, $db, $logger)
    {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function execute($message, $params)
    {
        $chat_id = $message['chat']['id'];
        $from_id = $message['from']['id'];

        // فقط مدیران می‌توانند راهنما را ببینند
        if (!$this->isGroupAdmin($chat_id, $from_id)) {
            $this->telegram->sendMessage($chat_id, "⛔ شما اجازه دسترسی به راهنما را ندارید.\n⛔ You don't have permission to view help.");
            return;
        }

        $text = $message['text'] ?? '';
        // تشخیص زبان: اگر دستور با /help شروع شود یا شامل help باشد => انگلیسی
        $is_english = (stripos($text, '/help') === 0 || stripos($text, 'help') !== false);

        if ($is_english) {
            $help_text = $this->getEnglishHelp();
        } else {
            $help_text = $this->getPersianHelp();
        }

        $this->telegram->sendMessage($chat_id, $help_text);
    }

    private function getPersianHelp()
    {
        return "📋 **راهنمای ربات مدیریت گروه**\n\n"
            . "🔹 **مدیریت ادمین‌ها**\n"
            . "`ست ادمین` @username - افزودن ادمین جدید\n"
            . "`حذف ادمین` @username - حذف ادمین\n"
            . "`لیست ادمین‌ها` - نمایش لیست ادمین‌ها\n\n"
            . "🔹 **مدیریت کاربران**\n"
            . "`بن` @username - بن کردن کاربر\n"
            . "`آن‌بن` @username - رفع بن کاربر\n"
            . "`لیست بن‌ها` - نمایش لیست کاربران بن‌شده\n"
            . "`سکوت` @username - ساکت کردن کاربر (فقط مدیران)\n"
            . "`حذف سکوت` @username - برداشتن سکوت کاربر\n"
            . "`اخطار` @username - ثبت اخطار (پس از ۳ اخطار بن خودکار)\n"
            . "`حذف اخطار` @username - حذف تمام اخطارهای کاربر\n\n"
            . "🔹 **مدیریت پیام‌ها**\n"
            . "`پین` (ریپلای) - پین کردن پیام\n"
            . "`حذف پین` - حذف پین\n"
            . "`حذف` (ریپلای) - حذف یک پیام\n"
            . "`پاکسازی` - پاکسازی ۵۰۰۰ پیام آخر\n"
            . "`آیدی` - دریافت آیدی عددی کاربر\n\n"
            . "🔹 **قفل‌های محتوا**\n"
            . "`قفل پیام` / `رفع قفل پیام` - قفل/رفع قفل متن\n"
            . "`قفل عکس` / `رفع قفل عکس` - قفل/رفع قفل عکس\n"
            . "`قفل فیلم` / `رفع قفل فیلم` - قفل/رفع قفل ویدیو\n"
            . "`قفل گیف` / `رفع قفل گیف` - قفل/رفع قفل GIF\n"
            . "`قفل استیکر` / `رفع قفل استیکر` - قفل/رفع قفل استیکر\n"
            . "`قفل ویس` / `رفع قفل ویس` - قفل/رفع قفل ویس\n"
            . "`قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` - قفل/رفع قفل ویدئو مسیج\n"
            . "`قفل لینک` / `رفع قفل لینک` - قفل/رفع قفل لینک\n"
            . "`قفل تگ` / `رفع قفل تگ` - قفل/رفع قفل تگ (منشن)\n"
            . "⭐️ `قفل هشتگ` / `رفع قفل هشتگ` - قفل/رفع قفل هشتگ (جدید)\n\n"
            . "🔹 **سایر**\n"
            . "`خوش آمد بگو` / `خوش آمد نگو` - فعال/غیرفعال‌سازی پیام خوش‌آمدگویی\n"
            . "`راهنما` / `help` - نمایش این راهنما";
    }

    private function getEnglishHelp()
    {
        return "📋 **Group Management Bot Help**\n\n"
            . "🔹 **Admin Management**\n"
            . "`/addadmin` @username - Add a new admin\n"
            . "`/remadmin` @username - Remove an admin\n"
            . "`/listadmin` - List all admins\n\n"
            . "🔹 **User Management**\n"
            . "`/ban` @username - Ban a user\n"
            . "`/unban` @username - Unban a user\n"
            . "`/listbans` - List banned users\n"
            . "`/mute` @username - Mute a user (admins only)\n"
            . "`/unmute` @username - Unmute a user\n"
            . "`/warning` @username - Give a warning (auto-ban after 3)\n"
            . "`/remwarning` @username - Remove all warnings for a user\n\n"
            . "🔹 **Message Management**\n"
            . "`/pin` (reply) - Pin a message\n"
            . "`/rempin` - Unpin a message\n"
            . "`/del` (reply) - Delete a message\n"
            . "`/clear` - Clear last 5000 messages\n"
            . "`/id` - Get user ID\n\n"
            . "🔹 **Content Locks**\n"
            . "`/lockmsg` / `/dislockmsg` - Lock/Unlock text messages\n"
            . "`/lockpic` / `/dislockpic` - Lock/Unlock photos\n"
            . "`/lockfilm` / `/dislockfilm` - Lock/Unlock videos\n"
            . "`/lockgif` / `/dislockgif` - Lock/Unlock GIFs\n"
            . "`/locksticker` / `/dislocksticker` - Lock/Unlock stickers\n"
            . "`/lockvoice` / `/remlockvoice` - Lock/Unlock voice messages\n"
            . "`/lockvm` / `/remlockvm` - Lock/Unlock video notes\n"
            . "`/locklink` / `/remlocklink` - Lock/Unlock links\n"
            . "`/locktag` / `/remlocktag` - Lock/Unlock tags (mentions)\n"
            . "⭐️ `/lockhashtag` / `/remlockhashtag` - Lock/Unlock hashtags (new)\n\n"
            . "🔹 **Other**\n"
            . "`/sayhello` / `/remsayhello` - Enable/Disable welcome message\n"
            . "`/help` or `راهنما` - Show this help";
    }

    private function isGroupAdmin($group_id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM bot_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return true;
        }
        $stmt = $this->db->prepare("SELECT id FROM bot_sub_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}