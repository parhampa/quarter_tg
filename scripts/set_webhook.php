#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * اسکریپت تنظیم Webhook ربات در تلگرام
 * 
 * نحوه استفاده:
 * php scripts/set_webhook.php
 * php scripts/set_webhook.php --url=https://example.com/webhook
 * php scripts/set_webhook.php --delete (برای حذف Webhook)
 * php scripts/set_webhook.php --info (برای مشاهده اطلاعات Webhook فعلی)
 * php scripts/set_webhook.php --help (نمایش راهنما)
 */

use QuarterTg\Core\Config;

// بارگذاری اتولودر
require_once __DIR__ . '/../vendor/autoload.php';

// بارگذاری متغیرهای محیطی
loadEnv();

// بارگذاری کانفیگ
$config = Config::createFromEnv();
$token = $config->get('bot_token', '');

if (empty($token)) {
    echo "❌ خطا: BOT_TOKEN در فایل .env تنظیم نشده است.\n";
    exit(1);
}

// پردازش آرگومان‌های خط فرمان
$options = getopt('', ['url:', 'delete', 'info', 'help']);
$apiUrl = 'https://api.telegram.org/bot' . $token;

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

if (isset($options['delete'])) {
    deleteWebhook($apiUrl);
    exit(0);
}

if (isset($options['info'])) {
    getWebhookInfo($apiUrl);
    exit(0);
}

$webhookUrl = $options['url'] ?? null;
if (empty($webhookUrl)) {
    $webhookUrl = $config->get('webhook.url', '');
    if (empty($webhookUrl)) {
        echo "❌ خطا: آدرس Webhook مشخص نشده است.\n";
        echo "لطفاً با --url آدرس را مشخص کنید یا در .env تنظیم کنید.\n";
        showHelp();
        exit(1);
    }
}

setWebhook($apiUrl, $webhookUrl, $config);
exit(0);

// ============================================================
// توابع کمکی
// ============================================================

function showHelp(): void
{
    echo "🔧 **اسکریپت تنظیم Webhook ربات**\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "نحوه استفاده:\n";
    echo "  php scripts/set_webhook.php --url=https://example.com/webhook\n";
    echo "  php scripts/set_webhook.php --delete\n";
    echo "  php scripts/set_webhook.php --info\n";
    echo "  php scripts/set_webhook.php --help\n\n";
    echo "گزینه‌ها:\n";
    echo "  --url=URL     آدرس Webhook را تنظیم کنید\n";
    echo "  --delete      Webhook فعلی را حذف کنید\n";
    echo "  --info        اطلاعات Webhook فعلی را نمایش دهید\n";
    echo "  --help        این راهنما را نمایش دهید\n\n";
    echo "پیش‌نیازها:\n";
    echo "  - فایل .env با BOT_TOKEN تنظیم شده\n";
    echo "  - آدرس Webhook باید HTTPS باشد (تلگرام HTTPS را الزامی کرده)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
}

function setWebhook(string $apiUrl, string $webhookUrl, Config $config): void
{
    echo "🔄 در حال تنظیم Webhook...\n";
    echo "📌 آدرس: {$webhookUrl}\n";

    $params = [
        'url' => $webhookUrl,
        'allowed_updates' => json_encode([
            'message', 'callback_query', 'inline_query',
            'chosen_inline_result', 'edited_message',
            'channel_post', 'edited_channel_post',
            'shipping_query', 'pre_checkout_query',
            'poll', 'poll_answer', 'my_chat_member',
            'chat_member', 'chat_join_request'
        ]),
        'max_connections' => 40,
        'drop_pending_updates' => true,
    ];

    $secret = $config->get('webhook.secret', '');
    if (!empty($secret)) {
        $params['secret_token'] = $secret;
        echo "🔐 Webhook Secret تنظیم شد.\n";
    }

    $response = sendRequest($apiUrl . '/setWebhook', $params);
    if ($response === false) {
        echo "❌ خطا در تنظیم Webhook.\n";
        exit(1);
    }

    if (isset($response['ok']) && $response['ok'] === true) {
        echo "✅ Webhook با موفقیت تنظیم شد!\n";
        echo "📌 آدرس: {$webhookUrl}\n";
        echo "📊 وضعیت: " . ($response['result'] ? 'فعال' : 'غیرفعال') . "\n";
    } else {
        $error = $response['description'] ?? 'خطای ناشناخته';
        echo "❌ خطا: {$error}\n";
        exit(1);
    }

    echo "\n";
    getWebhookInfo($apiUrl);
}

function getWebhookInfo(string $apiUrl): void
{
    echo "📊 دریافت اطلاعات Webhook...\n";
    $response = sendRequest($apiUrl . '/getWebhookInfo', []);

    if ($response === false) {
        echo "❌ خطا در دریافت اطلاعات Webhook.\n";
        return;
    }

    if (isset($response['ok']) && $response['ok'] === true) {
        $info = $response['result'] ?? [];
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📋 **اطلاعات Webhook فعلی**\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "📌 آدرس: " . ($info['url'] ?? 'تنظیم نشده') . "\n";
        echo "🔐 Secret Token: " . ($info['secret_token'] ?? 'تنظیم نشده') . "\n";
        echo "📊 وضعیت: " . (!empty($info['url']) ? 'فعال ✅' : 'غیرفعال ❌') . "\n";
        echo "⏳ درخواست‌های معوق: " . ($info['pending_update_count'] ?? 0) . "\n";
        echo "📦 آخرین خطا: " . ($info['last_error_message'] ?? 'ندارد') . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    } else {
        $error = $response['description'] ?? 'خطای ناشناخته';
        echo "❌ خطا: {$error}\n";
    }
}

function deleteWebhook(string $apiUrl): void
{
    echo "🔄 در حال حذف Webhook...\n";
    $response = sendRequest($apiUrl . '/deleteWebhook', ['drop_pending_updates' => true]);

    if ($response === false) {
        echo "❌ خطا در حذف Webhook.\n";
        exit(1);
    }

    if (isset($response['ok']) && $response['ok'] === true) {
        echo "✅ Webhook با موفقیت حذف شد!\n";
    } else {
        $error = $response['description'] ?? 'خطای ناشناخته';
        echo "❌ خطا: {$error}\n";
        exit(1);
    }
}

function sendRequest(string $url, array $params = []): array|false
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'QuarterTG Webhook Setter/1.0');

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        echo "❌ خطای cURL: {$error}\n";
        return false;
    }

    if ($httpCode >= 400) {
        echo "❌ خطای HTTP: {$httpCode}\n";
        return false;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ خطا در解析 JSON: " . json_last_error_msg() . "\n";
        return false;
    }

    return $decoded;
}

function loadEnv(): void
{
    if (!file_exists(__DIR__ . '/../.env')) {
        return;
    }

    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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