<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    | توکن ربات خود را از @BotFather دریافت کنید
    */
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    | تنظیمات اتصال به دیتابیس MySQL/MariaDB
    */
    'database' => [
        'host'     => 'localhost',
        'name'     => 'quarter_tg_db',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    | آدرس وب‌هوک و توکن مخفی برای امنیت
    */
    'webhook' => [
        'url'    => 'https://your-domain.com/quarter_tg/index.php',
        'secret' => 'your-secret-key-here', // توکن مخفی برای تأیید درخواست‌ها
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    | تنظیمات کش فایل‌بنیاد برای کاهش بار دیتابیس
    */
    'cache' => [
        'enabled' => true,
        'ttl'     => 300, // زمان انقضای کش بر حسب ثانیه (پیش‌فرض: ۵ دقیقه)
        'path'    => __DIR__ . '/../cache/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | تنظیمات سیستم لاگ‌گیری
    */
    'logging' => [
        'enabled' => true,
        'path'    => __DIR__ . '/../logs/bot.log',
        'level'   => 'info', // debug, info, warning, error
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner ID (Super Admin)
    |--------------------------------------------------------------------------
    | آیدی عددی تلگرام مالک اصلی ربات (فقط یک نفر)
    | این شخص دسترسی کامل به همه چیز دارد
    */
    'owner_id' => 123456789,

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    | زبان پیش‌فرض ربات (fa = فارسی, en = انگلیسی)
    */
    'default_language' => 'fa',

    /*
    |--------------------------------------------------------------------------
    | Command Mapping
    |--------------------------------------------------------------------------
    | نگاشت دستورات (انگلیسی و فارسی) به کلاس‌های ماژول
    | هر دستور باید به یک کلاس در دایرکتوری Modules/ اشاره کند
    */
    'command_map' => [
        // ========================
        // مدیریت ادمین‌ها
        // ========================
        '/addadmin'    => 'Modules\\AddAdminModule',
        'ست ادمین'     => 'Modules\\AddAdminModule',
        '/remadmin'    => 'Modules\\RemoveAdminModule',
        'حذف ادمین'    => 'Modules\\RemoveAdminModule',
        '/listadmin'   => 'Modules\\ListAdminsModule',
        'لیست ادمین‌ها' => 'Modules\\ListAdminsModule',

        // ========================
        // مدیریت کاربران (بن، سکوت، اخطار)
        // ========================
        '/ban'         => 'Modules\\BanModule',
        'بن'           => 'Modules\\BanModule',
        '/unban'       => 'Modules\\UnbanModule',
        'آن‌بن'        => 'Modules\\UnbanModule',
        '/listbans'    => 'Modules\\ListBansModule',
        'لیست بن‌ها'    => 'Modules\\ListBansModule',

        '/mute'        => 'Modules\\MuteModule',
        'سکوت'         => 'Modules\\MuteModule',
        '/unmute'      => 'Modules\\UnmuteModule',
        'حذف سکوت'     => 'Modules\\UnmuteModule',

        '/warning'     => 'Modules\\WarningModule',
        'اخطار'        => 'Modules\\WarningModule',
        '/remwarning'  => 'Modules\\RemoveWarningModule',
        'حذف اخطار'    => 'Modules\\RemoveWarningModule',

        // ========================
        // مدیریت پیام‌ها
        // ========================
        '/pin'         => 'Modules\\PinModule',
        'پین'          => 'Modules\\PinModule',
        '/rempin'      => 'Modules\\UnpinModule',
        'حذف پین'      => 'Modules\\UnpinModule',
        '/del'         => 'Modules\\DeleteModule',
        'حذف'          => 'Modules\\DeleteModule',
        '/clear'       => 'Modules\\ClearModule',
        'پاکسازی'      => 'Modules\\ClearModule',
        '/id'          => 'Modules\\GetIdModule',
        'آیدی'         => 'Modules\\GetIdModule',

        // ========================
        // قفل‌ها و رفع قفل - پیام متنی
        // ========================
        '/lockmsg'     => 'Modules\\LockTextModule',
        'قفل پیام'     => 'Modules\\LockTextModule',
        '/dislockmsg'  => 'Modules\\RemLockTextModule',
        'رفع قفل پیام' => 'Modules\\RemLockTextModule',

        // ========================
        // قفل‌ها و رفع قفل - عکس
        // ========================
        '/lockpic'     => 'Modules\\LockPhotoModule',
        'قفل عکس'      => 'Modules\\LockPhotoModule',
        '/dislockpic'  => 'Modules\\RemLockPhotoModule',
        'رفع قفل عکس'  => 'Modules\\RemLockPhotoModule',

        // ========================
        // قفل‌ها و رفع قفل - فیلم
        // ========================
        '/lockfilm'    => 'Modules\\LockVideoModule',
        'قفل فیلم'     => 'Modules\\LockVideoModule',
        '/dislockfilm' => 'Modules\\RemLockVideoModule',
        'رفع قفل فیلم' => 'Modules\\RemLockVideoModule',

        // ========================
        // قفل‌ها و رفع قفل - گیف
        // ========================
        '/lockgif'     => 'Modules\\LockGifModule',
        'قفل گیف'      => 'Modules\\LockGifModule',
        '/dislockgif'  => 'Modules\\RemLockGifModule',
        'رفع قفل گیف'  => 'Modules\\RemLockGifModule',

        // ========================
        // قفل‌ها و رفع قفل - استیکر
        // ========================
        '/locksticker' => 'Modules\\LockStickerModule',
        'قفل استیکر'   => 'Modules\\LockStickerModule',
        '/dislocksticker' => 'Modules\\RemLockStickerModule',
        'رفع قفل استیکر' => 'Modules\\RemLockStickerModule',

        // ========================
        // قفل‌ها و رفع قفل - ویس
        // ========================
        '/lockvoice'   => 'Modules\\LockVoiceModule',
        'قفل ویس'      => 'Modules\\LockVoiceModule',
        '/remlockvoice' => 'Modules\\RemLockVoiceModule',
        'رفع قفل ویس'   => 'Modules\\RemLockVoiceModule',

        // ========================
        // قفل‌ها و رفع قفل - ویدئو مسیج
        // ========================
        '/lockvm'      => 'Modules\\LockVideoNoteModule',
        'قفل ویدئو مسیج' => 'Modules\\LockVideoNoteModule',
        '/remlockvm'   => 'Modules\\RemLockVideoNoteModule',
        'رفع قفل ویدئو مسیج' => 'Modules\\RemLockVideoNoteModule',

        // ========================
        // قفل/رفع قفل لینک
        // ========================
        '/locklink'    => 'Modules\\LockLinkModule',
        'قفل لینک'     => 'Modules\\LockLinkModule',
        '/remlocklink' => 'Modules\\RemLockLinkModule',
        'رفع قفل لینک'  => 'Modules\\RemLockLinkModule',

        // ========================
        // قفل/رفع قفل تگ (منشن)
        // ========================
        '/locktag'     => 'Modules\\LockTagModule',
        'قفل تگ'       => 'Modules\\LockTagModule',
        '/remlocktag'  => 'Modules\\RemLockTagModule',
        'رفع قفل تگ'   => 'Modules\\RemLockTagModule',

        // ========================
        // قفل/رفع قفل هشتگ (جدید)
        // ========================
        '/lockhashtag' => 'Modules\\LockHashtagModule',
        'قفل هشتگ'     => 'Modules\\LockHashtagModule',
        '/remlockhashtag' => 'Modules\\RemLockHashtagModule',
        'رفع قفل هشتگ'  => 'Modules\\RemLockHashtagModule',

        // ========================
        // پیام خوش‌آمدگویی
        // ========================
        '/sayhello'    => 'Modules\\WelcomeModule',
        'خوش آمد بگو'  => 'Modules\\WelcomeModule',
        '/remsayhello' => 'Modules\\RemWelcomeModule',
        'خوش آمد نگو'  => 'Modules\\RemWelcomeModule',

        // ========================
        // راهنما
        // ========================
        '/help'        => 'Modules\\HelpModule',
        'راهنما'       => 'Modules\\HelpModule',
    ],
];