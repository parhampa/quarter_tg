<?php

declare(strict_types=1);

/**
 * نمونه فایل کانفیگ برای پروژه QuarterTG
 * 
 * این فایل را به config.php کپی کرده و مقادیر واقعی را جایگزین کنید.
 * یا از متغیرهای محیطی (.env) برای تنظیمات حساس استفاده کنید.
 */

return [
    // توکن ربات تلگرام (اجباری)
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    // تنظیمات دیتابیس
    'database' => [
        'host'     => 'localhost',
        'name'     => 'quarter_tg',
        'username' => 'root',
        'password' => 'your_password_here',
        'charset'  => 'utf8mb4',
    ],

    // تنظیمات کش فایل‌بنیاد
    'cache' => [
        'path' => __DIR__ . '/../cache',
        'ttl'  => 3600, // ۱ ساعت
    ],

    // تنظیمات لاگ
    'log' => [
        'path'     => __DIR__ . '/../logs/app.log',
        'level'    => 'info', // debug, info, warning, error, critical
        'max_size' => 10485760, // ۱۰ مگابایت
    ],

    // تنظیمات ماژول‌ها
    'modules' => [
        'path'      => __DIR__ . '/../src/Modules',
        'namespace' => 'QuarterTg\\Modules\\',
    ],

    // شناسه عددی کاربر مالک (اجباری)
    'owner_id' => 0,

    // تنظیمات امنیتی Webhook
    'webhook' => [
        'secret'      => 'your_webhook_secret_here', // اختیاری
        'allowed_ips' => '149.154.160.0/20,91.108.4.0/22', // IPهای تلگرام
    ],

    // منطقه زمانی و زبان
    'timezone' => 'Asia/Tehran',
    'locale'   => 'fa',

    // تنظیمات سیستم اخطار
    'warn' => [
        'max_warns'    => 3,
        'expiry_time'  => 86400, // ۲۴ ساعت
        'auto_ban'     => true,
    ],

    // تنظیمات قفل‌ها
    'locks' => [
        'links', 'tags', 'hashtags', 'commands',
        'arabic', 'english', 'persian',
        'spam', 'sticker', 'video', 'audio',
        'document', 'voice', 'photo', 'gif',
    ],
];