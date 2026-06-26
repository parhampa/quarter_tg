<?php

return [
    // توکن ربات تلگرام
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    // تنظیمات دیتابیس
    'db' => [
        'host' => 'localhost',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'database' => 'quarter_tg',
        'charset' => 'utf8mb4',
    ],

    // وب‌هوک سکرت (برای امنیت بیشتر)
    'webhook_secret' => 'your_secret_string',

    // مسیرهای لاگ و کش (توسط bootstrap تعریف می‌شوند)
    'logs_dir' => __DIR__ . '/../logs',
    'cache_dir' => __DIR__ . '/../cache',

    // ============================================================
    // نقشه دستورات به ماژول‌ها (command_map)
    // ============================================================
    'command_map' => [
        // ---------- دستورات انگلیسی ----------
        '/start'       => 'StartModule',
        '/help'        => 'HelpModule',
        'help'         => 'HelpModule',
        '/addadmin'    => 'AddAdminModule',
        '/remadmin'    => 'RemoveAdminModule',
        '/listadmin'   => 'ListAdminsModule',
        '/pin'         => 'PinModule',
        '/rempin'      => 'UnpinModule',
        '/id'          => 'GetIdModule',
        '/del'         => 'DeleteModule',
        '/clear'       => 'ClearModule',
        '/ban'         => 'BanModule',
        '/unban'       => 'UnbanModule',
        '/listbans'    => 'ListBansModule',
        '/lockmsg'     => 'LockMsgModule',
        '/dislockmsg'  => 'UnlockMsgModule',
        '/locksticker' => 'LockStickerModule',
        '/dislocksticker' => 'UnlockStickerModule',
        '/lockpic'     => 'LockPhotoModule',
        '/dislockpic'  => 'UnlockPhotoModule',
        '/lockfilm'    => 'LockVideoModule',
        '/dislockfilm' => 'UnlockVideoModule',
        '/lockgif'     => 'LockGifModule',
        '/dislockgif'  => 'UnlockGifModule',
        '/lockvoice'   => 'LockVoiceModule',
        '/remlockvoice'=> 'UnlockVoiceModule',
        '/lockvm'      => 'LockVideoNoteModule',
        '/remlockvm'   => 'UnlockVideoNoteModule',
        '/sayhello'    => 'SayHelloModule',
        '/remsayhello' => 'RemoveSayHelloModule',

        // ---------- دستورات Mute/Unmute ----------
        '/mute'        => 'MuteModule',
        '/unmute'      => 'UnmuteModule',

        // ---------- دستورات Warning/RemoveWarning ----------
        '/warning'     => 'WarningModule',
        '/remwarning'  => 'RemoveWarningModule',

        // ---------- دستورات فارسی ----------
        'ست ادمین'     => 'AddAdminModule',
        'حذف ادمین'    => 'RemoveAdminModule',
        'لیست ادمین‌ها' => 'ListAdminsModule',
        'پین'          => 'PinModule',
        'حذف پین'      => 'UnpinModule',
        'آیدی'         => 'GetIdModule',
        'حذف'          => 'DeleteModule',
        'پاکسازی'      => 'ClearModule',
        'بن'           => 'BanModule',
        'آن‌بن'        => 'UnbanModule',
        'لیست بن‌ها'   => 'ListBansModule',
        'قفل پیام'     => 'LockMsgModule',
        'رفع قفل پیام' => 'UnlockMsgModule',
        'قفل استیکر'   => 'LockStickerModule',
        'رفع قفل استیکر' => 'UnlockStickerModule',
        'قفل عکس'      => 'LockPhotoModule',
        'رفع قفل عکس'  => 'UnlockPhotoModule',
        'قفل فیلم'     => 'LockVideoModule',
        'رفع قفل فیلم' => 'UnlockVideoModule',
        'قفل گیف'      => 'LockGifModule',
        'رفع قفل گیف'  => 'UnlockGifModule',
        'قفل ویس'      => 'LockVoiceModule',
        'رفع قفل ویس'  => 'UnlockVoiceModule',
        'قفل ویدئو مسیج' => 'LockVideoNoteModule',
        'رفع قفل ویدئو مسیج' => 'UnlockVideoNoteModule',
        'خوش آمد بگو'  => 'SayHelloModule',
        'خوش آمد نگو'  => 'RemoveSayHelloModule',
        'راهنما'       => 'HelpModule',

        // ---------- دستورات فارسی Mute/Unmute ----------
        'سکوت'         => 'MuteModule',
        'حذف سکوت'     => 'UnmuteModule',

        // ---------- دستورات فارسی Warning/RemoveWarning ----------
        'اخطار'        => 'WarningModule',
        'حذف اخطار'    => 'RemoveWarningModule',
    ],
];