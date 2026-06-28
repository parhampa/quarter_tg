<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    */
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
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
    */
    'webhook' => [
        'url'    => 'https://your-domain.com/quarter_tg/index.php',
        'secret' => 'your-secret-key-here',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl'     => 300, // seconds
        'path'    => __DIR__ . '/../cache/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'path'    => __DIR__ . '/../logs/bot.log',
        'level'   => 'info',
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner ID (Super Admin)
    |--------------------------------------------------------------------------
    */
    'owner_id' => 123456789,

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    */
    'default_language' => 'fa',

    /*
    |--------------------------------------------------------------------------
    | Command Mapping
    |--------------------------------------------------------------------------
    */
    'command_map' => [
        // ========================
        // مدیریت ادمین‌ها
        // ========================
        '/addadmin'    => 'AddAdminModule',
        'ست ادمین'     => 'AddAdminModule',
        '/remadmin'    => 'RemoveAdminModule',
        'حذف ادمین'    => 'RemoveAdminModule',
        '/listadmin'   => 'ListAdminsModule',
        'لیست ادمین‌ها' => 'ListAdminsModule',

        // ========================
        // مدیریت کاربران (بن، سکوت، اخطار)
        // ========================
        '/ban'         => 'BanModule',
        'بن'           => 'BanModule',
        '/unban'       => 'UnbanModule',
        'آن‌بن'        => 'UnbanModule',
        '/listbans'    => 'ListBansModule',
        'لیست بن‌ها'    => 'ListBansModule',

        '/mute'        => 'MuteModule',
        'سکوت'         => 'MuteModule',
        '/unmute'      => 'UnmuteModule',
        'حذف سکوت'     => 'UnmuteModule',

        '/warning'     => 'WarningModule',
        'اخطار'        => 'WarningModule',
        '/remwarning'  => 'RemoveWarningModule',
        'حذف اخطار'    => 'RemoveWarningModule',

        // ========================
        // مدیریت پیام‌ها
        // ========================
        '/pin'         => 'PinModule',
        'پین'          => 'PinModule',
        '/rempin'      => 'UnpinModule',
        'حذف پین'      => 'UnpinModule',
        '/del'         => 'DeleteModule',
        'حذف'          => 'DeleteModule',
        '/clear'       => 'ClearModule',
        'پاکسازی'      => 'ClearModule',
        '/id'          => 'GetIdModule',
        'آیدی'         => 'GetIdModule',

        // ========================
        // قفل‌ها و رفع قفل
        // ========================
        '/lockmsg'     => 'LockTextModule',
        'قفل پیام'     => 'LockTextModule',
        '/dislockmsg'  => 'RemLockTextModule',
        'رفع قفل پیام' => 'RemLockTextModule',

        '/lockpic'     => 'LockPhotoModule',
        'قفل عکس'      => 'LockPhotoModule',
        '/dislockpic'  => 'RemLockPhotoModule',
        'رفع قفل عکس'  => 'RemLockPhotoModule',

        '/lockfilm'    => 'LockVideoModule',
        'قفل فیلم'     => 'LockVideoModule',
        '/dislockfilm' => 'RemLockVideoModule',
        'رفع قفل فیلم' => 'RemLockVideoModule',

        '/lockgif'     => 'LockGifModule',
        'قفل گیف'      => 'LockGifModule',
        '/dislockgif'  => 'RemLockGifModule',
        'رفع قفل گیف'  => 'RemLockGifModule',

        '/locksticker' => 'LockStickerModule',
        'قفل استیکر'   => 'LockStickerModule',
        '/dislocksticker' => 'RemLockStickerModule',
        'رفع قفل استیکر' => 'RemLockStickerModule',

        '/lockvoice'   => 'LockVoiceModule',
        'قفل ویس'      => 'LockVoiceModule',
        '/remlockvoice' => 'RemLockVoiceModule',
        'رفع قفل ویس'   => 'RemLockVoiceModule',

        '/lockvm'      => 'LockVideoNoteModule',
        'قفل ویدئو مسیج' => 'LockVideoNoteModule',
        '/remlockvm'   => 'RemLockVideoNoteModule',
        'رفع قفل ویدئو مسیج' => 'RemLockVideoNoteModule',

        // ========================
        // قفل/رفع قفل لینک
        // ========================
        '/locklink'    => 'LockLinkModule',
        'قفل لینک'     => 'LockLinkModule',
        '/remlocklink' => 'RemLockLinkModule',
        'رفع قفل لینک'  => 'RemLockLinkModule',

        // ========================
        // قفل/رفع قفل تگ
        // ========================
        '/locktag'     => 'LockTagModule',
        'قفل تگ'       => 'LockTagModule',
        '/remlocktag'  => 'RemLockTagModule',
        'رفع قفل تگ'   => 'RemLockTagModule',

        // ========================
        // قفل/رفع قفل هشتگ (جدید)
        // ========================
        '/lockhashtag' => 'LockHashtagModule',
        'قفل هشتگ'     => 'LockHashtagModule',
        '/remlockhashtag' => 'RemLockHashtagModule',
        'رفع قفل هشتگ'  => 'RemLockHashtagModule',

        // ========================
        // سایر
        // ========================
        '/sayhello'    => 'WelcomeModule',
        'خوش آمد بگو'  => 'WelcomeModule',
        '/remsayhello' => 'RemWelcomeModule',
        'خوش آمد نگو'  => 'RemWelcomeModule',

        '/help'        => 'HelpModule',
        'راهنما'       => 'HelpModule',
    ],
];