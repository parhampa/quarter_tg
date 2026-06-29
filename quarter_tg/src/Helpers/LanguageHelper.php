<?php

namespace QuarterTg\Helpers;

/**
 * کلاس مدیریت زبان و ترجمه پیام‌ها
 * پشتیبانی از دو زبان فارسی و انگلیسی با قابلیت توسعه‌پذیری
 */
class LanguageHelper
{
    private $defaultLanguage = 'fa';
    private $translations = [];

    /**
     * @param string $defaultLanguage زبان پیش‌فرض ('fa' یا 'en')
     */
    public function __construct(string $defaultLanguage = 'fa')
    {
        $this->defaultLanguage = $defaultLanguage;
        $this->loadTranslations();
    }

    /**
     * بارگذاری ترجمه‌ها از فایل‌های زبان (در صورت وجود)
     * در غیر این صورت از آرایه‌های داخلی استفاده می‌کند
     */
    private function loadTranslations(): void
    {
        // تلاش برای بارگذاری از فایل‌های خارجی
        $langDir = __DIR__ . '/../../lang/';
        
        $languages = ['fa', 'en'];
        foreach ($languages as $lang) {
            $file = $langDir . $lang . '.php';
            if (file_exists($file)) {
                $this->translations[$lang] = require $file;
            } else {
                // استفاده از ترجمه‌های داخلی
                $this->translations[$lang] = $this->getDefaultTranslations($lang);
            }
        }
    }

    /**
     * دریافت ترجمه‌های پیش‌فرض
     */
    private function getDefaultTranslations(string $lang): array
    {
        $translations = [
            'fa' => [
                // پیام‌های عمومی
                'admin_only' => '⛔ فقط ادمین‌ها می‌توانند از این دستور استفاده کنند.',
                'no_permission' => '⛔ شما دسترسی به این دستور را ندارید.',
                'unknown_command' => '❌ دستور ناشناخته. برای راهنمایی /help را وارد کنید.',
                'user_not_found' => '❌ کاربر مورد نظر یافت نشد.',
                'invalid_input' => '❌ ورودی نامعتبر. لطفاً دوباره تلاش کنید.',
                'operation_success' => '✅ عملیات با موفقیت انجام شد.',
                'operation_failed' => '❌ عملیات با خطا مواجه شد. لطفاً دوباره تلاش کنید.',
                
                // پیام‌های قفل‌ها
                'lock_enabled' => '🔒 قفل {type} با موفقیت فعال شد.',
                'lock_disabled' => '🔓 قفل {type} با موفقیت غیرفعال شد.',
                'lock_already_enabled' => '⚠️ قفل {type} از قبل فعال است.',
                'lock_already_disabled' => '⚠️ قفل {type} از قبل غیرفعال است.',
                'content_locked' => '⛔ ارسال {type} در این گروه ممنوع است.',
                
                // پیام‌های مدیریت کاربران
                'user_banned' => '✅ کاربر {user} با موفقیت بن شد.',
                'user_unbanned' => '✅ آن‌بن کاربر {user} با موفقیت انجام شد.',
                'user_already_banned' => '⚠️ کاربر {user} قبلاً بن شده است.',
                'user_not_banned' => '⚠️ کاربر {user} در لیست بن‌ها وجود ندارد.',
                'cannot_ban_admin' => '❌ شما نمی‌توانید یک ادمین را بن کنید.',
                'cannot_ban_owner' => '❌ شما نمی‌توانید مالک اصلی را بن کنید.',
                'cannot_ban_self' => '❌ شما نمی‌توانید خودتان را بن کنید.',
                
                // پیام‌های میوت
                'user_muted' => '✅ کاربر {user} با موفقیت سکوت شد.',
                'user_unmuted' => '✅ رفع سکوت کاربر {user} با موفقیت انجام شد.',
                'user_already_muted' => '⚠️ کاربر {user} قبلاً سکوت شده است.',
                'user_not_muted' => '⚠️ کاربر {user} در لیست سکوت‌ها وجود ندارد.',
                'duration_info' => '⏱️ مدت زمان: {duration}',
                'permanent_mute' => '⏱️ مدت زمان: <b>دائمی</b>',
                'mute_reason' => '📝 دلیل: {reason}',
                
                // پیام‌های اخطار
                'warning_given' => '⚠️ اخطار به کاربر {user}\n📊 تعداد اخطارها: <b>{count}</b>',
                'warning_reason' => '📝 دلیل: {reason}',
                'auto_ban_warning' => '❌ کاربر پس از {count} اخطار به‌طور خودکار <b>بن</b> شد.',
                'remaining_warnings' => '⏳ تا بن خودکار: <b>{remaining}</b> اخطار باقی‌مانده',
                'no_warnings' => '⚠️ کاربر {user} هیچ اخطاری ندارد.',
                'warnings_removed' => '✅ تمام اخطارهای کاربر {user} (تعداد: {count}) با موفقیت حذف شد.',
                'warning_count_exceeded' => '⚠️ کاربر به حداکثر اخطار رسیده است.',
                
                // پیام‌های مدیریت پیام‌ها
                'pin_success' => '📌 پیام با موفقیت پین شد.',
                'pin_silent' => '🔇 حالت بی‌صدا فعال است.',
                'unpin_success' => '🔓 پین با موفقیت حذف شد.',
                'unpin_all_success' => '🔓 تمام پین‌های گروه با موفقیت حذف شدند.',
                'pin_not_found' => '❌ پیام مورد نظر یافت نشد. ممکن است حذف شده باشد.',
                'pin_no_permission' => '❌ ربات دسترسی کافی برای پین کردن پیام ندارد.',
                'pin_admin_required' => '❌ ربات باید ادمین گروه باشد تا بتواند پیام را پین کند.',
                'delete_success' => '✅ پیام با موفقیت حذف شد.',
                'delete_failed' => '❌ حذف پیام با خطا مواجه شد.',
                'message_not_found' => '❌ پیام مورد نظر یافت نشد. ممکن است قبلاً حذف شده باشد.',
                'clear_start' => '🔄 در حال پاکسازی <b>{count}</b> پیام آخر گروه...\n⏳ لطفاً منتظر بمانید.',
                'clear_success' => '✅ پاکسازی با موفقیت انجام شد.\n📊 تعداد پیام‌های حذف‌شده: <b>{deleted}</b>',
                'clear_warning' => '⚠️ تنها {deleted} پیام قابل حذف بود (ممکن است برخی پیام‌ها قدیمی یا غیرقابل حذف باشند).',
                'clear_cooldown' => '⏳ لطفاً صبر کنید.\nمدت زمان باقی‌مانده تا پاکسازی بعدی: <b>{remaining}</b>',
                'clear_limit_exceeded' => '⚠️ حداکثر تعداد قابل پاکسازی {max} پیام است.',
                
                // پیام‌های ادمین‌ها
                'admin_added' => '✅ ادمین {user} با موفقیت اضافه شد.',
                'admin_removed' => '✅ ادمین {user} با موفقیت حذف شد.',
                'admin_already_exists' => '⚠️ کاربر {user} قبلاً ادمین است.',
                'admin_not_exists' => '⚠️ کاربر {user} ادمین نیست.',
                'admin_promoted' => '✅ ساب‌ادمین {user} به ادمین اصلی ارتقا یافت.',
                'admin_demoted' => '✅ ادمین {user} به ساب‌ادمین تنزل یافت.',
                
                // پیام‌های خوش‌آمدگویی
                'welcome_enabled' => '✅ پیام خوش‌آمدگویی با موفقیت فعال شد.',
                'welcome_disabled' => '❌ پیام خوش‌آمدگویی با موفقیت غیرفعال شد.',
                'welcome_set' => '✅ پیام خوش‌آمدگویی با موفقیت تنظیم شد.',
                'welcome_already_enabled' => '✅ پیام خوش‌آمدگویی در حال حاضر فعال است.',
                'welcome_current' => '📝 پیام فعلی:\n{message}',
                'welcome_how_to_change' => 'برای تغییر پیام: `/sayhello پیام جدید`\nبرای غیرفعال‌سازی: `/remsayhello`',
                'welcome_no_message' => '⚠️ پیام خوش‌آمدگویی فعال است اما پیامی تنظیم نشده است.',
                'welcome_disabled_status' => '❌ پیام خوش‌آمدگویی غیرفعال است.\nبرای فعال‌سازی و تنظیم پیام: `/sayhello متن پیام`',
                
                // پیام‌های راهنما
                'help_title' => '📋 <b>راهنمای ربات مدیریت گروه</b>',
                'help_admin_management' => '🔹 <b>مدیریت ادمین‌ها</b>',
                'help_user_management' => '🔹 <b>مدیریت کاربران</b>',
                'help_message_management' => '🔹 <b>مدیریت پیام‌ها</b>',
                'help_content_locks' => '🔹 <b>قفل‌های محتوا</b>',
                'help_other' => '🔹 <b>سایر</b>',
                'help_note' => '📌 <b>نکته</b>: دستورات فارسی نیز پشتیبانی می‌شوند.',
                
                // پیام‌های آیدی
                'id_yourself' => '🆔 آیدی شما: <code>{id}</code>',
                'id_group' => '📋 <b>اطلاعات گروه</b>\n━━━━━━━━━━━━━━━━━━━━\n📌 نام: <b>{title}</b>\n🆔 آیدی: <code>{id}</code>',
                'id_user' => '🆔 <b>آیدی کاربر</b>\n━━━━━━━━━━━━━━━━━━━━\n👤 نام: <b>{name}</b>\n🆔 آیدی: <code>{id}</code>',
                'id_username' => '🔗 یوزرنیم: @{username}',
                'id_status' => '📋 وضعیت: {status}',
            ],
            'en' => [
                // General messages
                'admin_only' => '⛔ Only admins can use this command.',
                'no_permission' => '⛔ You do not have permission to use this command.',
                'unknown_command' => '❌ Unknown command. Type /help for assistance.',
                'user_not_found' => '❌ User not found.',
                'invalid_input' => '❌ Invalid input. Please try again.',
                'operation_success' => '✅ Operation completed successfully.',
                'operation_failed' => '❌ Operation failed. Please try again.',
                
                // Lock messages
                'lock_enabled' => '🔒 {type} lock enabled successfully.',
                'lock_disabled' => '🔓 {type} lock disabled successfully.',
                'lock_already_enabled' => '⚠️ {type} lock is already enabled.',
                'lock_already_disabled' => '⚠️ {type} lock is already disabled.',
                'content_locked' => '⛔ Sending {type} is not allowed in this group.',
                
                // User management
                'user_banned' => '✅ User {user} banned successfully.',
                'user_unbanned' => '✅ User {user} unbanned successfully.',
                'user_already_banned' => '⚠️ User {user} is already banned.',
                'user_not_banned' => '⚠️ User {user} is not banned.',
                'cannot_ban_admin' => '❌ You cannot ban an admin.',
                'cannot_ban_owner' => '❌ You cannot ban the owner.',
                'cannot_ban_self' => '❌ You cannot ban yourself.',
                
                // Mute messages
                'user_muted' => '✅ User {user} muted successfully.',
                'user_unmuted' => '✅ User {user} unmuted successfully.',
                'user_already_muted' => '⚠️ User {user} is already muted.',
                'user_not_muted' => '⚠️ User {user} is not muted.',
                'duration_info' => '⏱️ Duration: {duration}',
                'permanent_mute' => '⏱️ Duration: <b>Permanent</b>',
                'mute_reason' => '📝 Reason: {reason}',
                
                // Warning messages
                'warning_given' => '⚠️ Warning given to {user}\n📊 Warnings count: <b>{count}</b>',
                'warning_reason' => '📝 Reason: {reason}',
                'auto_ban_warning' => '❌ User automatically <b>banned</b> after {count} warnings.',
                'remaining_warnings' => '⏳ Remaining warnings until auto-ban: <b>{remaining}</b>',
                'no_warnings' => '⚠️ User {user} has no warnings.',
                'warnings_removed' => '✅ All warnings for {user} ({count}) removed successfully.',
                'warning_count_exceeded' => '⚠️ User has reached maximum warnings.',
                
                // Message management
                'pin_success' => '📌 Message pinned successfully.',
                'pin_silent' => '🔇 Silent mode is enabled.',
                'unpin_success' => '🔓 Pin removed successfully.',
                'unpin_all_success' => '🔓 All pins removed successfully.',
                'pin_not_found' => '❌ Message not found. It may have been deleted.',
                'pin_no_permission' => '❌ Bot does not have permission to pin messages.',
                'pin_admin_required' => '❌ Bot must be an admin to pin messages.',
                'delete_success' => '✅ Message deleted successfully.',
                'delete_failed' => '❌ Failed to delete message.',
                'message_not_found' => '❌ Message not found. It may have been deleted.',
                'clear_start' => '🔄 Clearing <b>{count}</b> recent messages...\n⏳ Please wait.',
                'clear_success' => '✅ Clear completed successfully.\n📊 Messages deleted: <b>{deleted}</b>',
                'clear_warning' => '⚠️ Only {deleted} messages could be deleted (some may be old or undeletable).',
                'clear_cooldown' => '⏳ Please wait.\nTime remaining until next clear: <b>{remaining}</b>',
                'clear_limit_exceeded' => '⚠️ Maximum clear limit is {max} messages.',
                
                // Admin management
                'admin_added' => '✅ Admin {user} added successfully.',
                'admin_removed' => '✅ Admin {user} removed successfully.',
                'admin_already_exists' => '⚠️ User {user} is already an admin.',
                'admin_not_exists' => '⚠️ User {user} is not an admin.',
                'admin_promoted' => '✅ Sub-admin {user} promoted to admin.',
                'admin_demoted' => '✅ Admin {user} demoted to sub-admin.',
                
                // Welcome messages
                'welcome_enabled' => '✅ Welcome message enabled successfully.',
                'welcome_disabled' => '❌ Welcome message disabled successfully.',
                'welcome_set' => '✅ Welcome message set successfully.',
                'welcome_already_enabled' => '✅ Welcome message is already enabled.',
                'welcome_current' => '📝 Current message:\n{message}',
                'welcome_how_to_change' => 'To change: `/sayhello new message`\nTo disable: `/remsayhello`',
                'welcome_no_message' => '⚠️ Welcome message is enabled but no message is set.',
                'welcome_disabled_status' => '❌ Welcome message is disabled.\nTo enable: `/sayhello message text`',
                
                // Help messages
                'help_title' => '📋 <b>Group Management Bot Help</b>',
                'help_admin_management' => '🔹 <b>Admin Management</b>',
                'help_user_management' => '🔹 <b>User Management</b>',
                'help_message_management' => '🔹 <b>Message Management</b>',
                'help_content_locks' => '🔹 <b>Content Locks</b>',
                'help_other' => '🔹 <b>Other</b>',
                'help_note' => '📌 <b>Note</b>: Persian commands are also supported.',
                
                // ID messages
                'id_yourself' => '🆔 Your ID: <code>{id}</code>',
                'id_group' => '📋 <b>Group Information</b>\n━━━━━━━━━━━━━━━━━━━━\n📌 Name: <b>{title}</b>\n🆔 ID: <code>{id}</code>',
                'id_user' => '🆔 <b>User ID</b>\n━━━━━━━━━━━━━━━━━━━━\n👤 Name: <b>{name}</b>\n🆔 ID: <code>{id}</code>',
                'id_username' => '🔗 Username: @{username}',
                'id_status' => '📋 Status: {status}',
            ],
        ];

        return $translations[$lang] ?? $translations['fa'];
    }

    /**
     * ترجمه یک کلید به زبان مشخص
     * @param string $key کلید ترجمه
     * @param array $params پارامترهای جایگزینی (مثلاً ['{user}' => '@username'])
     * @param string|null $lang زبان مورد نظر (اگر null باشد، از زبان پیش‌فرض استفاده می‌کند)
     * @return string
     */
    public function translate(string $key, array $params = [], ?string $lang = null): string
    {
        $lang = $lang ?? $this->defaultLanguage;
        
        // اگر زبان در ترجمه‌ها وجود نداشته باشد، از فارسی استفاده کن
        if (!isset($this->translations[$lang])) {
            $lang = 'fa';
        }

        // دریافت متن ترجمه شده
        $text = $this->translations[$lang][$key] ?? $key;

        // جایگزینی پارامترها
        foreach ($params as $search => $replace) {
            $text = str_replace($search, $replace, $text);
        }

        return $text;
    }

    /**
     * ترجمه با جایگزینی پارامترها (شورت‌کات)
     * @param string $key کلید ترجمه
     * @param array $params پارامترها
     * @param string|null $lang زبان
     * @return string
     */
    public function t(string $key, array $params = [], ?string $lang = null): string
    {
        return $this->translate($key, $params, $lang);
    }

    /**
     * تنظیم زبان پیش‌فرض
     */
    public function setDefaultLanguage(string $lang): void
    {
        if (isset($this->translations[$lang])) {
            $this->defaultLanguage = $lang;
        }
    }

    /**
     * دریافت زبان پیش‌فرض
     */
    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * دریافت ترجمه‌های یک زبان خاص
     */
    public function getTranslations(string $lang): array
    {
        return $this->translations[$lang] ?? [];
    }

    /**
     * افزودن ترجمه جدید به صورت پویا
     */
    public function addTranslation(string $lang, string $key, string $value): void
    {
        if (!isset($this->translations[$lang])) {
            $this->translations[$lang] = [];
        }
        $this->translations[$lang][$key] = $value;
    }

    /**
     * بارگذاری ترجمه‌ها از یک فایل خارجی
     */
    public function loadTranslationFile(string $lang, string $filePath): bool
    {
        if (file_exists($filePath)) {
            $translations = require $filePath;
            if (is_array($translations)) {
                $this->translations[$lang] = array_merge(
                    $this->translations[$lang] ?? [],
                    $translations
                );
                return true;
            }
        }
        return false;
    }

    /**
     * تشخیص زبان از متن دستور
     * @param string $text متن دستور
     * @return string 'fa' یا 'en'
     */
    public function detectLanguageFromCommand(string $text): string
    {
        // اگر دستور با / شروع شود و شامل help باشد => انگلیسی
        if (strpos($text, '/') === 0 && stripos($text, 'help') !== false) {
            return 'en';
        }
        
        // کلمات کلیدی فارسی
        $persianKeywords = ['راهنما', 'قفل', 'رفع', 'ادمین', 'بن', 'سکوت', 'اخطار', 'پاکسازی', 'پین', 'آیدی'];
        foreach ($persianKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'fa';
            }
        }

        return $this->defaultLanguage;
    }

    /**
     * دریافت پیام خطای مناسب برای یک استثنا
     */
    public function getExceptionMessage(\Throwable $e, ?string $lang = null): string
    {
        $lang = $lang ?? $this->defaultLanguage;
        $message = $e->getMessage();

        // ترجمه خطاهای رایج
        $errorMap = [
            'message_not_found' => $this->translate('message_not_found', [], $lang),
            'not enough rights' => $this->translate('pin_no_permission', [], $lang),
            'CHAT_ADMIN_REQUIRED' => $this->translate('pin_admin_required', [], $lang),
            'message can\'t be deleted' => $this->translate('delete_failed', [], $lang),
        ];

        foreach ($errorMap as $search => $replacement) {
            if (stripos($message, $search) !== false) {
                return $replacement;
            }
        }

        return $this->translate('operation_failed', [], $lang) . ' ' . $message;
    }

    /**
     * متد استاتیک برای استفاده آسان
     */
    public static function __callStatic($method, $args)
    {
        // متدهای استاتیک برای استفاده سریع
        if ($method === 't') {
            $instance = new self();
            return $instance->translate($args[0] ?? '', $args[1] ?? [], $args[2] ?? null);
        }
        throw new \BadMethodCallException("Static method $method does not exist");
    }
}