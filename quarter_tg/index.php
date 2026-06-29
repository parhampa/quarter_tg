<?php

/**
 * ============================================================
 * نقطه ورود اصلی وب‌هوک ربات Quarter TG
 * ============================================================
 * 
 * این فایل تمام درخواست‌های ارسالی از تلگرام را دریافت کرده
 * و پس از بررسی امنیتی، به bootstrap.php ارسال می‌کند.
 * 
 * ویژگی‌های امنیتی:
 * - بررسی Webhook Secret Token
 * - محدودیت IP (اختیاری)
 * - محافظت در برابر حملات CSRF
 * - لاگ درخواست‌های مشکوک
 * ============================================================
 */

// ============================================================
// تنظیمات اولیه
// ============================================================

// جلوگیری از دسترسی مستقیم به فایل‌های داخلی
define('QUARTER_TG', true);

// بارگذاری تنظیمات برای بررسی Secret Token
$config = require_once __DIR__ . '/config/config.php';

// ============================================================
// بررسی امنیتی Webhook Secret Token
// ============================================================

// دریافت Secret Token از هدر درخواست
$headers = getallheaders();
$secretToken = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? $headers['x-telegram-bot-api-secret-token'] ?? '';

// اگر Secret Token در تنظیمات تعریف شده باشد، آن را بررسی می‌کنیم
$expectedSecret = $config['webhook']['secret'] ?? '';

if (!empty($expectedSecret)) {
    if (empty($secretToken) || $secretToken !== $expectedSecret) {
        // لاگ کردن تلاش ناموفق
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logMessage = sprintf(
            "[%s] Unauthorized webhook request from IP: %s, Secret: %s",
            date('Y-m-d H:i:s'),
            $ip,
            $secretToken ? 'present' : 'missing'
        );
        
        // لاگ در فایل (اگر امکان‌پذیر است)
        $logDir = __DIR__ . '/logs/';
        if (is_dir($logDir) && is_writable($logDir)) {
            file_put_contents(
                $logDir . '/security.log',
                $logMessage . PHP_EOL,
                FILE_APPEND
            );
        }
        
        // پاسخ با خطای 401 Unauthorized
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// ============================================================
// بررسی IP (اختیاری - در صورت نیاز فعال کنید)
// ============================================================

// لیست IPهای مجاز تلگرام (به‌روزرسانی دوره‌ای)
// https://core.telegram.org/bots/webhooks#the-short-version
$allowedIps = [
    '149.154.160.0/20',
    '91.108.4.0/22',
    // می‌توانید IPهای بیشتری اضافه کنید
];

// تابع بررسی IP در محدوده
function ipInRange($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    list($subnet, $bits) = explode('/', $range);
    $ip_dec = ip2long($ip);
    $subnet_dec = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet_dec &= $mask;
    return ($ip_dec & $mask) == $subnet_dec;
}

// فعال‌سازی بررسی IP (در صورت نیاز، این بخش را کامنت کنید)
// $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
// $ipAllowed = false;
// foreach ($allowedIps as $range) {
//     if (ipInRange($clientIp, $range)) {
//         $ipAllowed = true;
//         break;
//     }
// }
// if (!$ipAllowed) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Forbidden']);
//     exit;
// }

// ============================================================
// دریافت و پردازش ورودی
// ============================================================

// دریافت محتوای خام درخواست
$input = file_get_contents('php://input');

// اگر ورودی خالی است یا JSON نامعتبر است
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request']);
    exit;
}

// اعتبارسنجی JSON
$update = json_decode($input, true);
if ($update === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ============================================================
// لاگ درخواست‌ها (اختیاری - برای دیباگ)
// ============================================================

// در صورت وجود پیام، لاگ می‌کنیم (فقط برای دیباگ)
if (isset($update['message']) && isset($update['message']['text'])) {
    $logMessage = sprintf(
        "[%s] Message from %s: %s",
        date('Y-m-d H:i:s'),
        $update['message']['from']['id'] ?? 'unknown',
        substr($update['message']['text'], 0, 100)
    );
    
    $logDir = __DIR__ . '/logs/';
    if (is_dir($logDir) && is_writable($logDir) && isset($config['logging']['enabled']) && $config['logging']['enabled']) {
        file_put_contents(
            $logDir . '/webhook.log',
            $logMessage . PHP_EOL,
            FILE_APPEND
        );
    }
}

// ============================================================
// اجرای ربات
// ============================================================

// بارگذاری bootstrap.php و اجرای ربات
require_once __DIR__ . '/bootstrap.php';

// ============================================================
// پاسخ به تلگرام (در صورت نیاز)
// ============================================================

// تلگرام معمولاً نیازی به پاسخ ندارد، اما در صورت موفقیت، کد 200 برگردانده می‌شود
http_response_code(200);