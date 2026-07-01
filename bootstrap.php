<?php

declare(strict_types=1);

use QuarterTg\Core\Bot;
use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Core\ModuleManager;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\LockManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;

// ============================================================
// 1. بارگذاری متغیرهای محیطی از فایل .env (در صورت وجود)
// ============================================================
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // رد کردن کامنت‌ها
        if (strpos($line, '#') === 0) {
            continue;
        }
        // جدا کردن کلید و مقدار
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            if (!empty($name)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// ============================================================
// 2. بارگذاری اتولودر کامپوزر (حذف اتولودر دستی اضافی)
// ============================================================
require_once __DIR__ . '/vendor/autoload.php';

// ============================================================
// 3. بارگذاری کانفیگ اصلی
// ============================================================
$config = require_once __DIR__ . '/config/config.php';

// ============================================================
// 4. راه‌اندازی Logger (با مدیریت چرخش امن‌تر)
// ============================================================
$logger = new Logger(
    $config['log']['path'] ?? __DIR__ . '/logs/app.log',
    $config['log']['level'] ?? 'info',
    (int)($config['log']['max_size'] ?? 10485760) // 10MB پیش‌فرض
);

// ============================================================
// 5. راه‌اندازی Database (اتصال با PDO و خطاگیری کامل)
// ============================================================
try {
    $database = new Database(
        $config['database']['host'] ?? 'localhost',
        $config['database']['name'] ?? 'quarter_tg',
        $config['database']['username'] ?? 'root',
        $config['database']['password'] ?? '',
        $config['database']['charset'] ?? 'utf8mb4',
        $logger
    );
} catch (\Throwable $e) {
    $logger->critical('Failed to connect to database: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection error. Check logs.');
}

// ============================================================
// 6. راه‌اندازی Cache (با پشتیبانی از قفل فایل)
// ============================================================
$cache = new Cache(
    $config['cache']['path'] ?? __DIR__ . '/cache',
    (int)($config['cache']['ttl'] ?? 3600),
    $logger
);

// ============================================================
// 7. ساخت سرویس‌های اصلی (با تزریق وابستگی)
// ============================================================
$telegram = new TelegramApi($config['bot_token'], $logger);

// ------ مدیران داده (Data Managers) ------
$userManager = new UserManager($database, $cache, $logger);
$adminManager = new AdminManager($database, $cache, $logger);
$lockManager = new LockManager($database, $cache, $logger);
$warnManager = new WarnManager($database, $cache, $logger);
$authManager = new AuthorizationManager($database, $cache, $logger, $adminManager);

// تزریق وابستگی‌های متقابل (در صورت نیاز با Setter)
// (این کار را در آینده با Constructor Injection جایگزین خواهیم کرد)
$userManager->setCache($cache);
$adminManager->setCache($cache);

// ============================================================
// 8. راه‌اندازی ModuleManager (با Reflection و DI ساده)
// ============================================================
$moduleManager = new ModuleManager(
    $config['modules']['path'] ?? __DIR__ . '/src/Modules',
    $config['modules']['namespace'] ?? 'QuarterTg\\Modules\\',
    $logger
);

// ثبت اشیاء اشتراکی (Shared Instances) برای تزریق خودکار به ماژول‌ها
$moduleManager->addSharedInstance($database);
$moduleManager->addSharedInstance($cache);
$moduleManager->addSharedInstance($logger);
$moduleManager->addSharedInstance($telegram);
$moduleManager->addSharedInstance($userManager);
$moduleManager->addSharedInstance($adminManager);
$moduleManager->addSharedInstance($lockManager);
$moduleManager->addSharedInstance($warnManager);
$moduleManager->addSharedInstance($authManager);

// ============================================================
// 9. دریافت ورودی از Webhook (فقط یک بار و با اعتبارسنجی JSON)
// ============================================================
$input = file_get_contents('php://input');
if ($input === false) {
    $logger->error('Failed to read php://input');
    http_response_code(400);
    exit('Invalid request body.');
}

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $logger->error('Invalid JSON payload: ' . json_last_error_msg());
    http_response_code(400);
    exit('Invalid JSON.');
}

// ============================================================
// 10. ساخت و اجرای ربات اصلی
// ============================================================
try {
    $bot = new Bot(
        $config,
        $update,
        $database,
        $cache,
        $logger,
        $telegram,
        $userManager,
        $adminManager,
        $lockManager,
        $warnManager,
        $authManager,
        $moduleManager
    );

    // اجرای هندلر اصلی
    $bot->handleRequest();

} catch (\Throwable $e) {
    // مدیریت خطاهای پیش‌بینی‌نشده در سطح بالا
    $logger->critical('Unhandled exception in Bot: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(500);
    exit('Internal Server Error. Check logs.');
}

// در صورت نیاز، خروجی این فایل می‌تواند شیء Bot باشد
return $bot;