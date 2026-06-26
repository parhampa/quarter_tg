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

// مدیران
$muteManager = new Core\MuteManager($db, $telegram, $logger);
$moduleManager = new Core\ModuleManager($config['command_map']);

// ثبت ماژول‌های سفارشی (اختیاری)
// ماژول‌های جدید در command_map ثبت شده‌اند، نیازی به ثبت جداگانه نیست
// اما اگر می‌خواهید وابستگی‌های خاصی تزریق کنید، می‌توانید از setter استفاده کنید

// ساخت ربات
$bot = new Core\Bot($db, $telegram, $logger, $moduleManager, $muteManager, $config);

// پردازش درخواست دریافتی
$update = json_decode(file_get_contents('php://input'), true);
if ($update) {
    $bot->handleRequest($update);
} else {
    // اگر درخواست خالی بود، می‌توانید پیام خطا بدهید (برای دیباگ)
    http_response_code(400);
    echo 'Invalid request';
}