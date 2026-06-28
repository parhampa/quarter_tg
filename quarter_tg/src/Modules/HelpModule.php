<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول نمایش راهنمای ربات
 * پشتیبانی از دو زبان فارسی و انگلیسی
 * فقط ادمین‌ها می‌توانند راهنما را مشاهده کنند
 */
class HelpModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
    }

    /**
     * اجرای ماژول
     * @param array $message پیام دریافتی
     * @param string $params پارامترهای دستور
     * @param int $chatId آیدی گروه
     * @param int $userId آیدی کاربر
     */
    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        // اگر chatId و userId ارسال نشده باشند، از message استخراج می‌کنیم
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // فقط ادمین‌ها می‌توانند راهنما را ببینند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه دسترسی به راهنما را ندارید.\n⛔ You don't have permission to view help.",
                $message['message_id'] ?? null
            );
            return;
        }

        // تشخیص زبان بر اساس متن دستور
        $text = $message['text'] ?? '';
        $isEnglish = (stripos($text, '/help') === 0 || stripos($text, 'help') !== false);

        if ($isEnglish) {
            $helpText = $this->getEnglishHelp();
        } else {
            $helpText = $this->getPersianHelp();
        }

        $this->telegram->sendMessage(
            $chatId,
            $helpText,
            $message['message_id'] ?? null,
            'Markdown'
        );

        // لاگ
        $this->logger->info("Help shown to user $userId in group $chatId", [
            'language' => $isEnglish ? 'english' : 'persian',
        ]);
    }

    /**
     * دریافت متن راهنما به فارسی
     */
    private function getPersianHelp(): string
    {
        return "📋 **راهنمای ربات مدیریت گروه**\n\n"
            . "🔹 **مدیریت ادمین‌ها**\n"
            . "`/addadmin` @username - افزودن ادمین جدید\n"
            . "`/remadmin` @username - حذف ادمین\n"
            . "`/listadmin` - نمایش لیست ادمین‌ها\n\n"
            . "🔹 **مدیریت کاربران**\n"
            . "`/ban` @username - بن کردن کاربر\n"
            . "`/unban` @username - رفع بن کاربر\n"
            . "`/listbans` - نمایش لیست کاربران بن‌شده\n"
            . "`/mute` @username - ساکت کردن کاربر (فقط مدیران)\n"
            . "`/unmute` @username - برداشتن سکوت کاربر\n"
            . "`/warning` @username - ثبت اخطار (پس از ۳ اخطار بن خودکار)\n"
            . "`/remwarning` @username - حذف تمام اخطارهای کاربر\n\n"
            . "🔹 **مدیریت پیام‌ها**\n"
            . "`/pin` (ریپلای) - پین کردن پیام\n"
            . "`/rempin` - حذف پین\n"
            . "`/del` (ریپلای) - حذف یک پیام\n"
            . "`/clear` - پاکسازی ۵۰۰۰ پیام آخر\n"
            . "`/id` - دریافت آیدی عددی کاربر یا گروه\n\n"
            . "🔹 **قفل‌های محتوا**\n"
            . "`/lockmsg` / `/dislockmsg` - قفل/رفع قفل متن\n"
            . "`/lockpic` / `/dislockpic` - قفل/رفع قفل عکس\n"
            . "`/lockfilm` / `/dislockfilm` - قفل/رفع قفل ویدیو\n"
            . "`/lockgif` / `/dislockgif` - قفل/رفع قفل GIF\n"
            . "`/locksticker` / `/dislocksticker` - قفل/رفع قفل استیکر\n"
            . "`/lockvoice` / `/remlockvoice` - قفل/رفع قفل ویس\n"
            . "`/lockvm` / `/remlockvm` - قفل/رفع قفل ویدئو مسیج\n"
            . "`/locklink` / `/remlocklink` - قفل/رفع قفل لینک\n"
            . "`/locktag` / `/remlocktag` - قفل/رفع قفل تگ (منشن)\n"
            . "⭐️ `/lockhashtag` / `/remlockhashtag` - قفل/رفع قفل هشتگ (جدید)\n\n"
            . "🔹 **سایر**\n"
            . "`/sayhello` / `/remsayhello` - فعال/غیرفعال‌سازی پیام خوش‌آمدگویی\n"
            . "`/help` یا `راهنما` - نمایش این راهنما";
    }

    /**
     * دریافت متن راهنما به انگلیسی
     */
    private function getEnglishHelp(): string
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

    /**
     * توضیحات ماژول (برای استفاده در ModuleManager)
     */
    public static function getDescription(): string
    {
        return "نمایش راهنمای ربات / Show bot help";
    }
}