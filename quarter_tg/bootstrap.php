<?php

// ============================================================
// بوت‌استرپ ربات Quarter TG
// ============================================================

// تنظیمات مسیرها
define('ROOT_DIR', __DIR__);
define('SRC_DIR', ROOT_DIR . '/src');
define('CONFIG_DIR', ROOT_DIR . '/config');
define('LOGS_DIR', ROOT_DIR . '/logs');
define('CACHE_DIR', ROOT_DIR . '/cache');

// بارگذاری تنظیمات
$config = require CONFIG_DIR . '/config.php';

// ============================================================
// اتولودر PSR-4 استاندارد
// ============================================================
spl_autoload_register(function ($class) {
    // Namespace های پروژه
    $prefixes = [
        'QuarterTg\\Core\\'    => SRC_DIR . '/Core/',
        'QuarterTg\\Helpers\\' => SRC_DIR . '/Helpers/',
        'Modules\\'            => SRC_DIR . '/Modules/',
    ];

    foreach ($prefixes as $prefix => $base_dir) {
        if (strpos($class, $prefix) === 0) {
            $relative_class = substr($class, strlen($prefix));
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// ============================================================
// ایجاد وابستگی‌های پایه
// ============================================================

// دیتابیس
$database = new QuarterTg\Core\Database($config['database']);

// کش (فایل‌بنیاد)
$cache = new QuarterTg\Core\Cache(
    $config['cache']['path'],
    $config['cache']['ttl']
);

// لاگر
$logger = new QuarterTg\Core\Logger(
    $config['logging']['path'],
    $config['logging']['enabled'] ?? true
);

// Telegram API
$telegram = new QuarterTg\Helpers\TelegramApi($config['bot_token']);

// ============================================================
// ایجاد مدیران (Managers)
// ============================================================

// مدیریت قفل‌ها
$lockManager = new QuarterTg\Core\LockManager($database, $cache);

// مدیریت میوت
$muteManager = new QuarterTg\Core\MuteManager($database, $cache);
$muteManager->setTelegram($telegram);
$muteManager->setLogger($logger);

// مدیریت اخطارها
$warningManager = new QuarterTg\Core\WarningManager($database, $cache);
$warningManager->setTelegram($telegram);
$warningManager->setLogger($logger);

// مدیریت دسترسی‌ها (Authorization)
$authorizationManager = new QuarterTg\Core\AuthorizationManager(
    $database,
    $cache,
    $config['owner_id']
);

// مدیریت ادمین‌ها
$adminManager = new QuarterTg\Core\AdminManager($database, $cache);
$adminManager->setLogger($logger);

// مدیریت پیام خوش‌آمدگویی
$welcomeManager = new QuarterTg\Core\WelcomeManager($database, $cache);
$welcomeManager->setTelegram($telegram);
$welcomeManager->setLogger($logger);

// لاگ پیام‌ها
$messageLogger = new QuarterTg\Core\MessageLogger($database);
$messageLogger->setLogger($logger);

// لاگ دستورات
$commandLogger = new QuarterTg\Core\CommandLogger($database);
$commandLogger->setLogger($logger);

// ============================================================
// ModuleManager با وابستگی‌های کامل
// ============================================================

$moduleManager = new QuarterTg\Core\ModuleManager(
    $config['command_map'],
    [
        'telegram' => $telegram,
        'db' => $database,
        'logger' => $logger,
        'lockManager' => $lockManager,
        'muteManager' => $muteManager,
        'warningManager' => $warningManager,
        'authManager' => $authorizationManager,
        'adminManager' => $adminManager,
        'welcomeManager' => $welcomeManager,
        'messageLogger' => $messageLogger,
        'commandLogger' => $commandLogger,
        'cache' => $cache,
    ]
);

// ============================================================
// ساخت ربات اصلی
// ============================================================

$bot = new QuarterTg\Core\Bot(
    $config,
    $database,
    $cache,
    $logger
);

// ============================================================
// پردازش درخواست Webhook
// ============================================================

$input = file_get_contents('php://input');
if ($input) {
    $update = json_decode($input, true);
    if ($update) {
        $bot->handleRequest($update);
    } else {
        http_response_code(400);
        echo 'Invalid JSON';
    }
} else {
    http_response_code(400);
    echo 'No input received';
}