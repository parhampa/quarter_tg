<?php

declare(strict_types=1);

/**
 * Bootstrap فایل برای تست‌های PHPUnit
 */

// بارگذاری اتولودر
require_once __DIR__ . '/../vendor/autoload.php';

// بارگذاری متغیرهای محیطی
if (file_exists(__DIR__ . '/../.env.test')) {
    $lines = file(__DIR__ . '/../.env.test', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) {
            continue;
        }
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

// تنظیم منطقه زمانی
date_default_timezone_set('Asia/Tehran');

// ایجاد پوشه‌های مورد نیاز تست
$dirs = [
    __DIR__ . '/../cache_test',
    __DIR__ . '/../logs',
    __DIR__ . '/../coverage',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// اطمینان از وجود دیتابیس تست
try {
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $pdo->exec('CREATE DATABASE IF NOT EXISTS test_db');
    $pdo->exec('USE test_db');
} catch (PDOException $e) {
    echo "Warning: Could not create test database: " . $e->getMessage() . "\n";
}