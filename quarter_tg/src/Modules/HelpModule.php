<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class HelpModule
{
    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $lang = LanguageHelper::getLanguageFromCommand($command);

        if ($lang === 'fa') {
            $helpText = "📚 دستورات موجود:\n\n";
            $helpText .= "🔹 مدیریت ادمین‌ها:\n";
            $helpText .= "   • ست ادمین @user - اضافه کردن ادمین\n";
            $helpText .= "   • حذف ادمین @user - حذف ادمین\n";
            $helpText .= "   • لیست ادمین‌ها - نمایش لیست ادمین‌ها\n\n";
            $helpText .= "🔹 پیام خوش‌آمدگویی:\n";
            $helpText .= "   • خوش آمد بگو - فعال کردن خوش‌آمدگویی\n";
            $helpText .= "   • حذف خوش آمدگویی - غیرفعال کردن خوش‌آمدگویی\n\n";
            $helpText .= "🔹 پین کردن پیام:\n";
            $helpText .= "   • پین - (با ریپلای) پین کردن پیام\n";
            $helpText .= "   • حذف پین - (با ریپلای) خارج کردن از پین\n\n";
            $helpText .= "🔹 اطلاعات کاربر:\n";
            $helpText .= "   • آیدی - (با ریپلای) نمایش آیدی کاربر\n\n";
            $helpText .= "🔹 حذف پیام:\n";
            $helpText .= "   • حذف - (با ریپلای) حذف یک پیام\n";
            $helpText .= "   • پاکسازی - (محدودیت ۲۴ ساعته) حذف ۵۰۰۰ پیام آخر\n\n";
            $helpText .= "🔹 مدیریت کاربران:\n";
            $helpText .= "   • بن - (با ریپلای یا ذکر) بن کردن کاربر\n";
            $helpText .= "   • حذف بن - (با ریپلای یا ذکر) خارج کردن از بن\n";
            $helpText .= "   • لیست بن‌ها - نمایش لیست کاربران بن‌شده\n\n";
            $helpText .= "🔹 قفل‌های گروه:\n";
            $helpText .= "   • قفل پیام - جلوگیری از ارسال پیام متنی\n";
            $helpText .= "   • حذف قفل پیام - لغو قفل\n";
            $helpText .= "   • قفل استیکر - جلوگیری از ارسال استیکر\n";
            $helpText .= "   • حذف قفل استیکر - لغو قفل\n";
            $helpText .= "   • قفل عکس - جلوگیری از ارسال عکس\n";
            $helpText .= "   • حذف قفل عکس - لغو قفل\n";
            $helpText .= "   • قفل فیلم - جلوگیری از ارسال فیلم\n";
            $helpText .= "   • حذف قفل فیلم - لغو قفل\n";
            $helpText .= "   • قفل گیف - جلوگیری از ارسال گیف\n";
            $helpText .= "   • حذف قفل گیف - لغو قفل\n";
            $helpText .= "   • قفل ویس - جلوگیری از ارسال ویس\n";
            $helpText .= "   • حذف قفل ویس - لغو قفل\n";
            $helpText .= "   • قفل ویدئو مسیج - جلوگیری از ارسال ویدئو مسیج\n";
            $helpText .= "   • حذف قفل ویدئو مسیج - لغو قفل";
        } else {
            $helpText = "📚 Available commands:\n\n";
            $helpText .= "🔹 Admin management:\n";
            $helpText .= "   • /addadmin @user - Add admin\n";
            $helpText .= "   • /remadmin @user - Remove admin\n";
            $helpText .= "   • /listadmin - List admins\n\n";
            $helpText .= "🔹 Welcome message:\n";
            $helpText .= "   • /sayhello - Enable welcome\n";
            $helpText .= "   • /remsayhello - Disable welcome\n\n";
            $helpText .= "🔹 Pin message:\n";
            $helpText .= "   • /pin - (reply) Pin a message\n";
            $helpText .= "   • /rempin - (reply) Unpin a message\n\n";
            $helpText .= "🔹 User info:\n";
            $helpText .= "   • /id - (reply) Show user ID\n\n";
            $helpText .= "🔹 Delete messages:\n";
            $helpText .= "   • /del - (reply) Delete a single message\n";
            $helpText .= "   • /clear - (24h cooldown) Clear last 5000 messages\n\n";
            $helpText .= "🔹 User management:\n";
            $helpText .= "   • /ban - (reply or mention) Ban a user\n";
            $helpText .= "   • /unban - (reply or mention) Unban a user\n";
            $helpText .= "   • /listbans - Show list of banned users\n\n";
            $helpText .= "🔹 Group locks:\n";
            $helpText .= "   • /lockmsg - Prevent text messages\n";
            $helpText .= "   • /dislockmsg - Remove lock\n";
            $helpText .= "   • /locksticker - Prevent stickers\n";
            $helpText .= "   • /dislocksticker - Remove lock\n";
            $helpText .= "   • /lockpic - Prevent photos\n";
            $helpText .= "   • /dislockpic - Remove lock\n";
            $helpText .= "   • /lockfilm - Prevent videos\n";
            $helpText .= "   • /dislockfilm - Remove lock\n";
            $helpText .= "   • /lockgif - Prevent GIFs\n";
            $helpText .= "   • /dislockgif - Remove lock\n";
            $helpText .= "   • /lockvoice - Prevent voice messages\n";
            $helpText .= "   • /remlockvoice - Remove lock\n";
            $helpText .= "   • /lockvm - Prevent video messages\n";
            $helpText .= "   • /remlockvm - Remove lock";
        }
        $api->sendMessage($chatId, $helpText, $msgId);
    }
}