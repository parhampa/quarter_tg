<?php

declare(strict_types=1);

// ============================================================
// 1. تنظیمات اولیه و نمایش خطاها (فقط در محیط توسعه)
// ============================================================
if (getenv('APP_ENV') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ============================================================
// 2. توابع کمکی امنیتی
// ============================================================

/**
 * بررسی اینکه آیا IP داده شده در لیست IPهای مجاز قرار دارد؟
 * پشتیبانی از IPv4 و IPv6 با CIDR (مثلاً 192.168.1.0/24)
 */
function isIpAllowed(string $clientIp, string $allowedList): bool {
    $allowedList = trim($allowedList);
    if (empty($allowedList)) {
        return true; // اگر لیست خالی باشد، همه مجاز هستند (اختیاری)
    }

    $allowedIps = array_map('trim', explode(',', $allowedList));
    foreach ($allowedIps as $allowed) {
        // اگر IP دقیقاً برابر باشد
        if ($clientIp === $allowed) {
            return true;
        }
        // بررسی CIDR (مثلاً 192.168.1.0/24)
        if (strpos($allowed, '/') !== false) {
            if (ipInCidr($clientIp, $allowed)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * بررسی اینکه آیا یک IP در محدوده CIDR قرار دارد؟
 * پشتیبانی از IPv4 و IPv6
 */
function ipInCidr(string $ip, string $cidr): bool {
    list($subnet, $mask) = explode('/', $cidr);
    $mask = (int)$mask;

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        // IPv4
        $ipBin = ip2long($ip);
        $subnetBin = ip2long($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        $maskBin = -1 << (32 - $mask);
        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        // IPv6 (ساده‌سازی شده با استفاده از inet_pton)
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        // تعداد بایت‌های کامل برای ماسک
        $bytes = $mask >> 3;
        $bits = $mask & 7;
        // مقایسه بایت‌های کامل
        for ($i = 0; $i < $bytes; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }
        // مقایسه بیت‌های باقیمانده (اگر وجود داشته باشد)
        if ($bits > 0) {
            $maskByte = ~0 << (8 - $bits);
            $ipByte = ord($ipBin[$bytes]) & $maskByte;
            $subnetByte = ord($subnetBin[$bytes]) & $maskByte;
            if ($ipByte !== $subnetByte) {
                return false;
            }
        }
        return true;
    }
    return false;
}

// ============================================================
// 3. بارگذاری bootstrap و دریافت کانفیگ
// ============================================================
$bootstrap = require_once __DIR__ . '/bootstrap.php';
if (!$bootstrap instanceof \QuarterTg\Core\Bot) {
    http_response_code(500);
    exit('Bootstrap did not return Bot instance.');
}
$bot = $bootstrap;

// دسترسی به کانفیگ از طریق متد یا خاصیت (فرض میکنیم Bot دارای getConfig() است)
// اما در bootstrap ما کانفیگ را به Bot تزریق کردیم، پس می‌توانیم از خود Bot دریافت کنیم.
// از آنجایی که Bot را اصلاح نکردیم، یک راهکار موقت: کانفیگ را دوباره از فایل config بخوانیم.
$config = require_once __DIR__ . '/config/config.php';

// ============================================================
// 4. اعتبارسنجی امنیتی Webhook
// ============================================================

// 4.1. بررسی Webhook Secret (در صورت تنظیم)
$webhookSecret = $config['webhook']['secret'] ?? '';
if (!empty($webhookSecret)) {
    $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '';
    if (!hash_equals($webhookSecret, $receivedSecret)) {
        http_response_code(403);
        exit('Forbidden: Invalid webhook secret.');
    }
}

// 4.2. بررسی IP کلاینت (در صورت تنظیم)
$allowedIps = $config['webhook']['allowed_ips'] ?? '';
if (!empty($allowedIps)) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    // در صورت وجود پروکسی، از HTTP_X_FORWARDED_FOR استفاده کنیم (با احتیاط)
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (!isIpAllowed($clientIp, $allowedIps)) {
        http_response_code(403);
        exit('Forbidden: IP not allowed.');
    }
}

// ============================================================
// 5. اجرای ربات
// ============================================================
try {
    // ورودی قبلاً در bootstrap.php خوانده شده و به Bot داده شده است.
    // اما اگر bootstrap.php ورودی را برنگرداند، باید خودمان بخوانیم.
    // در bootstrap اصلاح‌شده، ورودی خوانده شده و به Bot تزریق شده است.
    $bot->handleRequest();
} catch (\Throwable $e) {
    // لاگ کردن خطا (Logger در bootstrap موجود است)
    $logger = $bot->getLogger();
    if ($logger) {
        $logger->critical('Unhandled exception in index.php: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    http_response_code(500);
    exit('Internal Server Error.');
}

// ============================================================
// 6. پایان کار (همه چیز OK)
// ============================================================
http_response_code(200);
exit('OK');