<?php

declare(strict_types=1);

/**
 * فایل کانفیگ اصلی ربات quarter_tg
 * تمام مقادیر حساس از متغیرهای محیطی (Environment Variables) خوانده می‌شوند.
 * برای تنظیم، یک فایل .env در ریشه پروژه ایجاد کنید (نمونه آن در .env.example موجود است).
 */

// ============================================================
// 1. خواندن متغیرهای محیطی (با پشتیبانی از getenv و $_ENV)
// ============================================================
function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? null;
    }
    if ($value === null || $value === false) {
        return $default;
    }
    // تبدیل مقادیر boolean
    if (strtolower($value) === 'true') {
        return true;
    }
    if (strtolower($value) === 'false') {
        return false;
    }
    return $value;
}

// ============================================================
// 2. تنظیمات اجباری (بدون این مقادیر، ربات کار نخواهد کرد)
// ============================================================
$botToken = env('BOT_TOKEN');
if (empty($botToken)) {
    throw new \RuntimeException('BOT_TOKEN not set in environment variables. Please create .env file.');
}

$dbHost = env('DB_HOST', 'localhost');
$dbName = env('DB_NAME', 'quarter_tg');
$dbUsername = env('DB_USERNAME', 'root');
$dbPassword = env('DB_PASSWORD', '');
if (empty($dbPassword)) {
    // در محیط تولید، حتماً رمز عبور غیرخالی تنظیم شود
    // اما در محیط توسعه می‌توان خالی گذاشت (با اخطار)
    error_log('Warning: DB_PASSWORD is empty. This is not safe for production.');
}

// ============================================================
// 3. تنظیمات اختیاری با پیشفرض‌های ایمن
// ============================================================
return [
    // ---------- توکن ربات (اجباری) ----------
    'bot_token' => $botToken,

    // ---------- اطلاعات دیتابیس ----------
    'database' => [
        'host'     => $dbHost,
        'name'     => $dbName,
        'username' => $dbUsername,
        'password' => $dbPassword,
        'charset'  => env('DB_CHARSET', 'utf8mb4'),
    ],

    // ---------- تنظیمات کش (فایل‌بنیاد) ----------
    'cache' => [
        'path' => env('CACHE_PATH', __DIR__ . '/../cache'),
        'ttl'  => (int) env('CACHE_TTL', 3600), // 1 ساعت
    ],

    // ---------- تنظیمات لاگ ----------
    'log' => [
        'path'     => env('LOG_PATH', __DIR__ . '/../logs/app.log'),
        'level'    => env('LOG_LEVEL', 'info'), // debug, info, warning, error, critical
        'max_size' => (int) env('LOG_MAX_SIZE', 10485760), // 10 مگابایت
    ],

    // ---------- تنظیمات ماژول‌ها ----------
    'modules' => [
        'path'      => env('MODULES_PATH', __DIR__ . '/../src/Modules'),
        'namespace' => env('MODULES_NAMESPACE', 'QuarterTg\\Modules\\'),
    ],

    // ---------- شناسه ادمین اصلی (Owner) ----------
    'owner_id' => (int) env('OWNER_ID', 0), // باید در .env تنظیم شود

    // ---------- تنظیمات امنیتی Webhook ----------
    'webhook' => [
        'secret'       => env('WEBHOOK_SECRET', ''), // اختیاری، اما توصیه می‌شود
        'allowed_ips'  => env('ALLOWED_IPS', ''),    // لیست IPهای مجاز، با کاما جدا شوند
    ],

    // ---------- سایر تنظیمات ----------
    'timezone' => env('TIMEZONE', 'Asia/Tehran'),
    'locale'   => env('LOCALE', 'fa'),
];