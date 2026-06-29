<?php

/**
 * فایل بوت‌استرپ برای تست‌های واحد
 * بارگذاری اتولودر و تنظیمات اولیه برای اجرای تست‌ها
 */

// بارگذاری اتولودر کامپوزر
require_once __DIR__ . '/../vendor/autoload.php';

// تنظیمات مسیرها
define('ROOT_DIR', dirname(__DIR__));
define('SRC_DIR', ROOT_DIR . '/src');
define('CONFIG_DIR', ROOT_DIR . '/config');
define('LOGS_DIR', ROOT_DIR . '/logs');
define('CACHE_DIR', ROOT_DIR . '/cache');

// تنظیمات زمان
date_default_timezone_set('Asia/Tehran');

// بارگذاری کلاس‌های تست
spl_autoload_register(function ($class) {
    $prefix = 'Tests\\';
    $base_dir = __DIR__ . '/';
    
    if (strpos($class, $prefix) === 0) {
        $relative_class = substr($class, strlen($prefix));
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

// ایجاد دایرکتوری‌های تست در صورت عدم وجود
$dirs = [
    CACHE_DIR,
    LOGS_DIR,
    __DIR__ . '/Unit',
    __DIR__ . '/Feature',
    __DIR__ . '/Core',
    __DIR__ . '/Modules',
    __DIR__ . '/Fixtures',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// بارگذاری تنظیمات تست (در صورت وجود)
$testConfigFile = CONFIG_DIR . '/config.test.php';
if (file_exists($testConfigFile)) {
    $GLOBALS['test_config'] = require $testConfigFile;
}

echo "✅ Bootstrap loaded for tests\n";