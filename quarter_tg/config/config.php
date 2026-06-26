<?php
return [
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    'db' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'bot_db',
        'charset' => 'utf8mb4',
    ],

    'modules_dir' => __DIR__ . '/../src/Modules',
    'log_dir' => __DIR__ . '/../logs',
    'cache_dir' => __DIR__ . '/../cache',
    'cache_ttl' => 300,
    'enable_log' => true,
    'log_level' => 'info',
    'webhook_secret' => null,

    'command_map' => [
        // ---- Public commands ----
        'start' => [
            'module' => 'StartModule',
            'method' => 'handle',
            'authorized_only' => false,
            'allowed_in_private' => true,
            'required_role' => 'public',
        ],
        'help' => [
            'module' => 'HelpModule',
            'method' => 'handle',
            'authorized_only' => false,
            'allowed_in_private' => true,
            'required_role' => 'public',
        ],

        // ---- Admin management (English) ----
        'addadmin' => [
            'module' => 'AddAdminModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],
        'remadmin' => [
            'module' => 'RemoveAdminModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],
        'listadmin' => [
            'module' => 'ListAdminsModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],

        // ---- Admin management (Persian) ----
        'ست ادمین' => [
            'module' => 'AddAdminModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],
        'حذف ادمین' => [
            'module' => 'RemoveAdminModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],
        'لیست ادمین‌ها' => [
            'module' => 'ListAdminsModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin_manager',
        ],

        // ---- Welcome message (English) ----
        'sayhello' => [
            'module' => 'SayHelloModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'remsayhello' => [
            'module' => 'RemoveSayHelloModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Welcome message (Persian) ----
        'خوش آمد بگو' => [
            'module' => 'SayHelloModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف خوش آمدگویی' => [
            'module' => 'RemoveSayHelloModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Pin & Unpin (English) ----
        'pin' => [
            'module' => 'PinModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'rempin' => [
            'module' => 'UnpinModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'id' => [
            'module' => 'GetIdModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Pin & Unpin (Persian) ----
        'پین' => [
            'module' => 'PinModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف پین' => [
            'module' => 'UnpinModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'آیدی' => [
            'module' => 'GetIdModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Delete & Clear (English) ----
        'del' => [
            'module' => 'DeleteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'clear' => [
            'module' => 'ClearModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Delete & Clear (Persian) ----
        'حذف' => [
            'module' => 'DeleteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'پاکسازی' => [
            'module' => 'ClearModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Lock commands (English) ----
        'lockmsg' => [
            'module' => 'LockMessageModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'dislockmsg' => [
            'module' => 'UnlockMessageModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'locksticker' => [
            'module' => 'LockStickerModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'dislocksticker' => [
            'module' => 'UnlockStickerModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'lockpic' => [
            'module' => 'LockPhotoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'dislockpic' => [
            'module' => 'UnlockPhotoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'lockfilm' => [
            'module' => 'LockVideoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'dislockfilm' => [
            'module' => 'UnlockVideoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'lockgif' => [
            'module' => 'LockGifModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'dislockgif' => [
            'module' => 'UnlockGifModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'lockvoice' => [
            'module' => 'LockVoiceModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'remlockvoice' => [
            'module' => 'UnlockVoiceModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'lockvm' => [
            'module' => 'LockVideoNoteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'remlockvm' => [
            'module' => 'UnlockVideoNoteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Lock commands (Persian) ----
        'قفل پیام' => [
            'module' => 'LockMessageModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل پیام' => [
            'module' => 'UnlockMessageModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل استیکر' => [
            'module' => 'LockStickerModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل استیکر' => [
            'module' => 'UnlockStickerModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل عکس' => [
            'module' => 'LockPhotoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل عکس' => [
            'module' => 'UnlockPhotoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل فیلم' => [
            'module' => 'LockVideoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل فیلم' => [
            'module' => 'UnlockVideoModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل گیف' => [
            'module' => 'LockGifModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل گیف' => [
            'module' => 'UnlockGifModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل ویس' => [
            'module' => 'LockVoiceModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل ویس' => [
            'module' => 'UnlockVoiceModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'قفل ویدئو مسیج' => [
            'module' => 'LockVideoNoteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف قفل ویدئو مسیج' => [
            'module' => 'UnlockVideoNoteModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Ban & Unban (English) ----
        'ban' => [
            'module' => 'BanModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'unban' => [
            'module' => 'UnbanModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'listbans' => [
            'module' => 'ListBansModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Ban & Unban (Persian) ----
        'بن' => [
            'module' => 'BanModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'حذف بن' => [
            'module' => 'UnbanModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'لیست بن‌ها' => [
            'module' => 'ListBansModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],

        // ---- Other sample commands ----
        'stats' => [
            'module' => 'StatsModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ],
        'admincmd' => [
            'module' => 'AdminCmdModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'admin',
        ],
        'owneronly' => [
            'module' => 'OwnerModule',
            'method' => 'handle',
            'authorized_only' => true,
            'allowed_in_private' => true,
            'required_role' => 'owner',
        ],
    ],

    'module_defaults' => [
        'authorized_only' => true,
        'allowed_in_private' => false,
        'required_role' => 'group_admin',
    ],
];