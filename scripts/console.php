#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * کنسول مدیریت ربات
 * 
 * نحوه استفاده:
 * php scripts/console.php [command] [options]
 * 
 * دستورات:
 *   cache:clear       پاک کردن کش
 *   cache:stats       نمایش آمار کش
 *   db:migrate        اجرای مهاجرت‌های دیتابیس
 *   db:seed           درج داده‌های اولیه
 *   webhook:set       تنظیم Webhook
 *   webhook:delete    حذف Webhook
 *   webhook:info      نمایش اطلاعات Webhook
 *   stats:show        نمایش آمار کلی ربات
 *   user:list         نمایش لیست کاربران
 *   group:list        نمایش لیست گروه‌ها
 *   help              نمایش راهنما
 */

use QuarterTg\Core\Application;
use QuarterTg\Core\Config;
use QuarterTg\Helpers\FormatHelper;

// بارگذاری اتولودر
require_once __DIR__ . '/../vendor/autoload.php';

// بارگذاری متغیرهای محیطی
loadEnv();

// دریافت آرگومان‌های خط فرمان
$command = $argv[1] ?? 'help';
$options = parseOptions(array_slice($argv, 2));

try {
    $app = new Application();
    $console = new Console($app);
    $console->run($command, $options);
} catch (Throwable $e) {
    echo "❌ خطا: " . $e->getMessage() . "\n";
    echo "📄 " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// ============================================================
// کلاس Console
// ============================================================

class Console
{
    private Application $app;
    private Config $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app->getConfig();
    }

    public function run(string $command, array $options): void
    {
        echo "\n";

        switch ($command) {
            case 'help':
                $this->showHelp();
                break;

            case 'cache:clear':
                $this->clearCache();
                break;

            case 'cache:stats':
                $this->showCacheStats();
                break;

            case 'db:migrate':
                $this->runMigrations($options);
                break;

            case 'db:seed':
                $this->runSeeders($options);
                break;

            case 'webhook:set':
                $this->setWebhook($options);
                break;

            case 'webhook:delete':
                $this->deleteWebhook();
                break;

            case 'webhook:info':
                $this->showWebhookInfo();
                break;

            case 'stats:show':
                $this->showStats();
                break;

            case 'user:list':
                $this->listUsers($options);
                break;

            case 'group:list':
                $this->listGroups($options);
                break;

            default:
                echo "❌ دستور نامعتبر: {$command}\n";
                echo "برای مشاهده راهنما: php scripts/console.php help\n";
                break;
        }

        echo "\n";
    }

    // ============================================================
    // دستورات کش
    // ============================================================

    private function clearCache(): void
    {
        echo "🔄 در حال پاک کردن کش...\n";
        $cache = $this->app->get(\QuarterTg\Core\Cache::class);
        $cache->clear();
        echo "✅ کش با موفقیت پاک شد.\n";
    }

    private function showCacheStats(): void
    {
        echo "📊 آمار کش:\n";
        $cache = $this->app->get(\QuarterTg\Core\Cache::class);
        $stats = $cache->getStats();
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📁 تعداد فایل‌ها: " . FormatHelper::numberFormat($stats['file_count']) . "\n";
        echo "💾 حجم کش: " . FormatHelper::formatSize($stats['total_size']) . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }

    // ============================================================
    // دستورات دیتابیس
    // ============================================================

    private function runMigrations(array $options): void
    {
        $fresh = isset($options['fresh']);
        echo "🔄 در حال اجرای مهاجرت‌ها...\n";

        if ($fresh) {
            echo "⚠️  حالت Fresh: جداول حذف و بازسازی خواهند شد.\n";
        }

        $db = $this->app->get(\QuarterTg\Core\Database::class);
        
        $sqlFile = __DIR__ . '/../database/migrations/initial_schema.sql';
        if (!file_exists($sqlFile)) {
            echo "❌ فایل مهاجرت یافت نشد: {$sqlFile}\n";
            exit(1);
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            echo "❌ خطا در خواندن فایل مهاجرت.\n";
            exit(1);
        }

        try {
            $db->execute($sql);
            echo "✅ مهاجرت‌ها با موفقیت اجرا شدند.\n";
        } catch (Throwable $e) {
            echo "❌ خطا در اجرای مهاجرت‌ها: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function runSeeders(array $options): void
    {
        echo "🔄 در حال درج داده‌های اولیه...\n";

        $db = $this->app->get(\QuarterTg\Core\Database::class);
        
        $sqlFile = __DIR__ . '/../database/seeders/initial_data.sql';
        if (!file_exists($sqlFile)) {
            echo "❌ فایل سیدر یافت نشد: {$sqlFile}\n";
            exit(1);
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            echo "❌ خطا در خواندن فایل سیدر.\n";
            exit(1);
        }

        try {
            $db->execute($sql);
            echo "✅ داده‌های اولیه با موفقیت درج شدند.\n";
        } catch (Throwable $e) {
            echo "❌ خطا در درج داده‌ها: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    // ============================================================
    // دستورات Webhook
    // ============================================================

    private function setWebhook(array $options): void
    {
        $url = $options['url'] ?? null;
        if (empty($url)) {
            echo "❌ لطفاً آدرس Webhook را با --url مشخص کنید.\n";
            echo "مثال: php scripts/console.php webhook:set --url=https://example.com/webhook.php\n";
            exit(1);
        }

        echo "🔄 در حال تنظیم Webhook...\n";
        echo "📌 آدرس: {$url}\n";

        $token = $this->config->get('bot_token', '');
        if (empty($token)) {
            echo "❌ BOT_TOKEN در .env تنظیم نشده است.\n";
            exit(1);
        }

        $apiUrl = 'https://api.telegram.org/bot' . $token;
        $params = [
            'url' => $url,
            'max_connections' => 40,
            'drop_pending_updates' => true,
        ];

        $secret = $this->config->get('webhook.secret', '');
        if (!empty($secret)) {
            $params['secret_token'] = $secret;
            echo "🔐 Webhook Secret تنظیم شد.\n";
        }

        $response = $this->callTelegramApi($apiUrl . '/setWebhook', $params);
        if ($response === false) {
            echo "❌ خطا در تنظیم Webhook.\n";
            exit(1);
        }

        if (isset($response['ok']) && $response['ok'] === true) {
            echo "✅ Webhook با موفقیت تنظیم شد!\n";
        } else {
            $error = $response['description'] ?? 'خطای ناشناخته';
            echo "❌ خطا: {$error}\n";
            exit(1);
        }
    }

    private function deleteWebhook(): void
    {
        echo "🔄 در حال حذف Webhook...\n";
        
        $token = $this->config->get('bot_token', '');
        if (empty($token)) {
            echo "❌ BOT_TOKEN در .env تنظیم نشده است.\n";
            exit(1);
        }

        $apiUrl = 'https://api.telegram.org/bot' . $token;
        $response = $this->callTelegramApi($apiUrl . '/deleteWebhook', ['drop_pending_updates' => true]);

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

    private function showWebhookInfo(): void
    {
        echo "📊 دریافت اطلاعات Webhook...\n";

        $token = $this->config->get('bot_token', '');
        if (empty($token)) {
            echo "❌ BOT_TOKEN در .env تنظیم نشده است.\n";
            exit(1);
        }

        $apiUrl = 'https://api.telegram.org/bot' . $token;
        $response = $this->callTelegramApi($apiUrl . '/getWebhookInfo', []);

        if ($response === false) {
            echo "❌ خطا در دریافت اطلاعات Webhook.\n";
            return;
        }

        if (isset($response['ok']) && $response['ok'] === true) {
            $info = $response['result'] ?? [];
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📋 اطلاعات Webhook فعلی\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            echo "📌 آدرس: " . ($info['url'] ?? 'تنظیم نشده') . "\n";
            echo "🔐 Secret Token: " . ($info['secret_token'] ?? 'تنظیم نشده') . "\n";
            echo "📊 وضعیت: " . (!empty($info['url']) ? 'فعال ✅' : 'غیرفعال ❌') . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        } else {
            $error = $response['description'] ?? 'خطای ناشناخته';
            echo "❌ خطا: {$error}\n";
        }
    }

    // ============================================================
    // دستورات آمار
    // ============================================================

    private function showStats(): void
    {
        echo "📊 آمار کلی ربات\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $db = $this->app->get(\QuarterTg\Core\Database::class);

        $totalUsers = $db->queryValue('SELECT COUNT(*) FROM users') ?: 0;
        $totalGroups = $db->queryValue('SELECT COUNT(*) FROM groups') ?: 0;
        $totalAdmins = $db->queryValue('SELECT COUNT(*) FROM admins WHERE is_active = 1') ?: 0;
        $totalWarns = $db->queryValue('SELECT COUNT(*) FROM warns WHERE expires_at > NOW()') ?: 0;
        $totalLocks = $db->queryValue('SELECT COUNT(*) FROM group_locks WHERE is_active = 1') ?: 0;

        echo "👤 کاربران: " . FormatHelper::numberFormat($totalUsers) . "\n";
        echo "👥 گروه‌ها: " . FormatHelper::numberFormat($totalGroups) . "\n";
        echo "🔑 ادمین‌ها: " . FormatHelper::numberFormat($totalAdmins) . "\n";
        echo "⚠️  اخطارهای فعال: " . FormatHelper::numberFormat($totalWarns) . "\n";
        echo "🔒 قفل‌های فعال: " . FormatHelper::numberFormat($totalLocks) . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }

    // ============================================================
    // دستورات لیست
    // ============================================================

    private function listUsers(array $options): void
    {
        $limit = (int)($options['limit'] ?? 20);
        $offset = (int)($options['offset'] ?? 0);

        echo "👤 لیست کاربران (محدودیت: {$limit})\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $db = $this->app->get(\QuarterTg\Core\Database::class);
        $users = $db->query(
            'SELECT user_id, first_name, last_name, username, created_at 
             FROM users 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        if (empty($users)) {
            echo "ℹ️ هیچ کاربری یافت نشد.\n";
            return;
        }

        foreach ($users as $user) {
            $name = $user['first_name'] . ($user['last_name'] ? ' ' . $user['last_name'] : '');
            $username = $user['username'] ? '@' . $user['username'] : 'ندارد';
            $date = date('Y-m-d', strtotime($user['created_at']));
            echo "🆔 {$user['user_id']} | {$name} | {$username} | {$date}\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 تعداد نمایش: " . count($users) . "\n";
    }

    private function listGroups(array $options): void
    {
        $limit = (int)($options['limit'] ?? 20);
        $offset = (int)($options['offset'] ?? 0);

        echo "👥 لیست گروه‌ها (محدودیت: {$limit})\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

        $db = $this->app->get(\QuarterTg\Core\Database::class);
        $groups = $db->query(
            'SELECT group_id, title, username, type, created_at 
             FROM groups 
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        if (empty($groups)) {
            echo "ℹ️ هیچ گروهی یافت نشد.\n";
            return;
        }

        foreach ($groups as $group) {
            $title = $group['title'] ?: 'بدون نام';
            $username = $group['username'] ? '@' . $group['username'] : 'ندارد';
            $date = date('Y-m-d', strtotime($group['created_at']));
            echo "🆔 {$group['group_id']} | {$title} | {$username} | {$group['type']} | {$date}\n";
        }

        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 تعداد نمایش: " . count($groups) . "\n";
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    private function callTelegramApi(string $url, array $params): array|false
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'QuarterTG Console/1.0');

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

    private function showHelp(): void
    {
        echo "🔧 **کنسول مدیریت ربات**\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        echo "نحوه استفاده:\n";
        echo "  php scripts/console.php [command] [options]\n\n";
        echo "دستورات:\n";
        echo "  cache:clear              پاک کردن کش\n";
        echo "  cache:stats              نمایش آمار کش\n";
        echo "  db:migrate [--fresh]     اجرای مهاجرت‌های دیتابیس\n";
        echo "  db:seed                  درج داده‌های اولیه\n";
        echo "  webhook:set --url=URL    تنظیم Webhook\n";
        echo "  webhook:delete           حذف Webhook\n";
        echo "  webhook:info             نمایش اطلاعات Webhook\n";
        echo "  stats:show               نمایش آمار کلی ربات\n";
        echo "  user:list [--limit=N]    نمایش لیست کاربران\n";
        echo "  group:list [--limit=N]   نمایش لیست گروه‌ها\n";
        echo "  help                     نمایش این راهنما\n\n";
        echo "مثال‌ها:\n";
        echo "  php scripts/console.php cache:clear\n";
        echo "  php scripts/console.php webhook:set --url=https://example.com/webhook.php\n";
        echo "  php scripts/console.php user:list --limit=50\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

// ============================================================
// توابع کمکی
// ============================================================

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

function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if (strpos($arg, '--') === 0) {
            $parts = explode('=', substr($arg, 2), 2);
            if (count($parts) === 2) {
                $options[$parts[0]] = $parts[1];
            } else {
                $options[$parts[0]] = true;
            }
        }
    }
    return $options;
}