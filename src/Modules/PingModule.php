<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use Throwable;

/**
 * ماژول بررسی وضعیت ربات
 * 
 * دستورات:
 * - /ping – بررسی وضعیت ربات و نمایش اطلاعات
 * - /status – نمایش اطلاعات کامل ربات (مشابه /ping)
 */
class PingModule implements ModuleInterface
{
    public const COMMANDS = ['ping', 'status'];

    private TelegramApi $telegram;
    private Database $database;
    private Cache $cache;
    private Logger $logger;
    private float $startTime;

    public function __construct(
        TelegramApi $telegram,
        Database $database,
        Cache $cache,
        Logger $logger,
        float $startTime = null
    ) {
        $this->telegram = $telegram;
        $this->database = $database;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->startTime = $startTime ?? microtime(true);
    }

    /**
     * اجرای ماژول
     */
    public function execute(int $chatId, int $userId, string $param, array $message): mixed
    {
        // تشخیص دستور (از پیام اصلی)
        $text = $message['text'] ?? '';
        if (empty($text)) {
            return null;
        }

        // استخراج نام دستور (بدون /)
        $command = substr(trim($text), 1);
        $parts = explode(' ', $command, 2);
        $commandName = strtolower($parts[0]);
        $param = $parts[1] ?? '';

        // پردازش دستورات مختلف
        return match ($commandName) {
            'ping', 'status' => $this->handlePing($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش وضعیت ربات
     */
    private function handlePing(int $chatId, int $userId, array $message): array
    {
        try {
            // محاسبه زمان پاسخ
            $currentTime = microtime(true);
            $responseTime = round(($currentTime - $this->startTime) * 1000, 2); // میلی‌ثانیه

            // دریافت اطلاعات دیتابیس
            $dbStatus = $this->checkDatabase();
            
            // دریافت اطلاعات کش
            $cacheStatus = $this->checkCache();

            // دریافت آمار ربات (از دیتابیس یا کش)
            $stats = $this->getBotStats();

            // تولید پیام وضعیت
            $messageText = $this->generateStatusMessage(
                $responseTime,
                $dbStatus,
                $cacheStatus,
                $stats,
                $userId
            );

            // ارسال پیام
            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            // لاگ
            $this->logger->debug('Ping command executed.', [
                'chat' => $chatId,
                'user' => $userId,
                'response_time' => $responseTime,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Ping command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در بررسی وضعیت ربات.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * بررسی وضعیت دیتابیس
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // یک کوئری ساده برای تست اتصال
            $result = $this->database->queryValue('SELECT 1');
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => '✅',
                'connected' => true,
                'query_time' => $queryTime,
                'query_count' => $this->database->getQueryCount(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => '❌',
                'connected' => false,
                'error' => $e->getMessage(),
                'query_count' => 0,
            ];
        }
    }

    /**
     * بررسی وضعیت کش
     */
    private function checkCache(): array
    {
        try {
            // تست نوشتن و خواندن در کش
            $testKey = '_ping_test_' . time();
            $testValue = 'pong_' . time();
            
            $writeSuccess = $this->cache->set($testKey, $testValue, 10);
            if (!$writeSuccess) {
                return [
                    'status' => '⚠️',
                    'working' => false,
                    'message' => 'نوشتن در کش با مشکل مواجه شد',
                    'file_count' => 0,
                    'size' => 0,
                ];
            }

            $readValue = $this->cache->get($testKey);
            $this->cache->delete($testKey);

            $stats = $this->cache->getStats();

            return [
                'status' => $readValue === $testValue ? '✅' : '⚠️',
                'working' => $readValue === $testValue,
                'file_count' => $stats['file_count'] ?? 0,
                'size' => $stats['total_size'] ?? 0,
            ];
        } catch (Throwable $e) {
            return [
                'status' => '❌',
                'working' => false,
                'error' => $e->getMessage(),
                'file_count' => 0,
                'size' => 0,
            ];
        }
    }

    /**
     * دریافت آمار کلی ربات
     */
    private function getBotStats(): array
    {
        try {
            // تعداد کاربران
            $totalUsers = $this->database->queryValue('SELECT COUNT(*) FROM users');
            
            // تعداد گروه‌ها (اگر جدول groups وجود دارد)
            $totalGroups = $this->database->queryValue('SELECT COUNT(*) FROM groups');
            
            // تعداد ادمین‌ها
            $totalAdmins = $this->database->queryValue('SELECT COUNT(*) FROM admins WHERE is_active = 1');

            return [
                'total_users' => (int)($totalUsers ?: 0),
                'total_groups' => (int)($totalGroups ?: 0),
                'total_admins' => (int)($totalAdmins ?: 0),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ];
        } catch (Throwable $e) {
            return [
                'total_users' => 0,
                'total_groups' => 0,
                'total_admins' => 0,
                'memory_usage' => 0,
                'peak_memory' => 0,
            ];
        }
    }

    /**
     * تولید پیام وضعیت
     */
    private function generateStatusMessage(
        float $responseTime,
        array $dbStatus,
        array $cacheStatus,
        array $stats,
        int $userId
    ): string {
        $messageText = "🏓 **Pong!**\n";
        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // زمان پاسخ
        $messageText .= "⏱️ **زمان پاسخ:** {$responseTime} ms\n";
        $messageText .= "🕐 **زمان سرور:** " . date('Y-m-d H:i:s') . "\n\n";

        // وضعیت دیتابیس
        $messageText .= "🗄️ **دیتابیس:** {$dbStatus['status']} ";
        if ($dbStatus['connected']) {
            $messageText .= "(زمان کوئری: {$dbStatus['query_time']} ms, تعداد: {$dbStatus['query_count']})\n";
        } else {
            $messageText .= "(خطا: {$dbStatus['error']})\n";
        }

        // وضعیت کش
        $messageText .= "💾 **کش:** {$cacheStatus['status']} ";
        if ($cacheStatus['working']) {
            $messageText .= "(فایل‌ها: {$cacheStatus['file_count']}, حجم: " . 
                           $this->formatBytes($cacheStatus['size']) . ")\n";
        } else {
            $messageText .= "(خطا: {$cacheStatus['error']})\n";
        }

        $messageText .= "\n📊 **آمار کلی:**\n";
        $messageText .= "👤 کاربران: {$stats['total_users']}\n";
        $messageText .= "👥 گروه‌ها: {$stats['total_groups']}\n";
        $messageText .= "🔑 ادمین‌ها: {$stats['total_admins']}\n";
        
        // حافظه
        $messageText .= "💻 حافظه مصرفی: " . $this->formatBytes($stats['memory_usage']) . "\n";
        $messageText .= "📈 حداکثر حافظه: " . $this->formatBytes($stats['peak_memory']) . "\n";

        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $messageText .= "✅ ربات به درستی کار می‌کند.";

        return $messageText;
    }

    /**
     * تبدیل بایت به فرمت خوانا
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $value = round($bytes / pow(1024, $i), 2);
        
        return $value . ' ' . $units[$i];
    }

    /**
     * ارسال پیام خطا
     */
    private function sendError(int $chatId, string $message): array
    {
        $this->telegram->sendMessage($chatId, $message);
        return ['success' => false, 'message' => $message];
    }
}