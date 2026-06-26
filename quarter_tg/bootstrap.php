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

// کلاس‌های اتولود (ساده)
spl_autoload_register(function ($class) {
    $prefix = 'Core\\';
    $base_dir = SRC_DIR . '/Core/';
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require $file;
        return;
    }
    $prefix = 'Modules\\';
    $base_dir = SRC_DIR . '/Modules/';
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require $file;
        return;
    }
    $prefix = 'Helpers\\';
    $base_dir = SRC_DIR . '/Helpers/';
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require $file;
        return;
    }
});

// ایجاد وابستگی‌ها
$db = new Core\Database($config['db']);
$telegram = new Helpers\TelegramApi($config['bot_token']);
$logger = new Core\Logger(LOGS_DIR . '/bot.log');

// ==================== مدیران جدید ====================
$muteManager = new Core\MuteManager($db, $telegram, $logger);
$warningManager = new Core\WarningManager($db, $telegram, $logger);

// ==================== ModuleManager ====================
$moduleManager = new Core\ModuleManager($config['command_map']);

// ==================== ثبت ماژول‌ها با وابستگی‌ها ====================
// ماژول‌های Help
$moduleManager->registerModule('HelpModule', new Modules\HelpModule($telegram, $db, $logger));

// ماژول‌های Mute
$moduleManager->registerModule('MuteModule', new Modules\MuteModule($muteManager, $telegram, $db, $logger));
$moduleManager->registerModule('UnmuteModule', new Modules\UnmuteModule($muteManager, $telegram, $db, $logger));

// ماژول‌های Warning
$moduleManager->registerModule('WarningModule', new Modules\WarningModule($warningManager, $telegram, $db, $logger));
$moduleManager->registerModule('RemoveWarningModule', new Modules\RemoveWarningModule($warningManager, $telegram, $db, $logger));

// در صورت وجود سایر ماژول‌ها، می‌توانید آن‌ها را نیز ثبت کنید
// اما اگر ماژول‌ها وابستگی خاصی ندارند، از command_map استفاده می‌شود

// ==================== ساخت ربات ====================
$bot = new Core\Bot(
    $db,
    $telegram,
    $logger,
    $moduleManager,
    $muteManager,
    $warningManager,
    $config
);

// ==================== پردازش درخواست ====================
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    $bot->handleRequest($update);
} else {
    http_response_code(400);
    echo 'Invalid request';
}