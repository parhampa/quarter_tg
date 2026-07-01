<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\LockManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;
use Throwable;

/**
 * ماژول نمایش آمار ربات
 * 
 * دستورات:
 * - /stats – نمایش آمار کامل ربات (فقط ادمین‌ها و مالک)
 * - /botstats – نمایش آمار کامل ربات (مشابه /stats)
 */
class StatsModule implements ModuleInterface
{
    public const COMMANDS = ['stats', 'botstats'];

    private TelegramApi $telegram;
    private Database $database;
    private Cache $cache;
    private UserManager $userManager;
    private AdminManager $adminManager;
    private WarnManager $warnManager;
    private LockManager $lockManager;
    private AuthorizationManager $authManager;
    private Logger $logger;
    private float $startTime;

    public function __construct(
        TelegramApi $telegram,
        Database $database,
        Cache $cache,
        UserManager $userManager,
        AdminManager $adminManager,
        WarnManager $warnManager,
        LockManager $lockManager,
        AuthorizationManager $authManager,
        Logger $logger,
        float $startTime = null
    ) {
        $this->telegram = $telegram;
        $this->database = $database;
        $this->cache = $cache;
        $this->userManager = $userManager;
        $this->adminManager = $adminManager;
        $this->warnManager = $warnManager;
        $this->lockManager = $lockManager;
        $this->authManager = $authManager;
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
            'stats', 'botstats' => $this->handleStats($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش آمار ربات
     */
    private function handleStats(int $chatId, int $userId, array $message): array
    {
        // بررسی دسترسی: فقط ادمین‌ها و مالک میتوانند آمار را ببینند
        if (!$this->authManager->isAdmin($chatId, $userId) && !$this->authManager->isOwner($userId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها و مالک ربات میتوانند آمار را مشاهده کنند.');
        }

        try {
            // دریافت آمار از منابع مختلف
            $userStats = $this->getUserStats();
            $groupStats = $this->getGroupStats();
            $adminStats = $this->getAdminStats();
            $warnStats = $this->getWarnStats();
            $lockStats = $this->getLockStats();
            $systemStats = $this->getSystemStats();

            // تولید پیام آمار
            $messageText = $this->generateStatsMessage(
                $userStats,
                $groupStats,
                $adminStats,
                $warnStats,
                $lockStats,
                $systemStats
            );

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);
            
            $this->logger->info('Stats command executed.', [
                'chat' => $chatId,
                'user' => $userId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Stats command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت آمار ربات.');
        }
    }

    // ============================================================
    // متدهای دریافت آمار
    // ============================================================

    /**
     * دریافت آمار کاربران
     */
    private function getUserStats(): array
    {
        try {
            $totalUsers = $this->database->queryValue('SELECT COUNT(*) FROM users');
            $totalBots = $this->database->queryValue('SELECT COUNT(*) FROM users WHERE is_bot = 1');
            $activeUsers = $this->database->queryValue(
                'SELECT COUNT(DISTINCT user_id) FROM group_members WHERE is_active = 1'
            );
            $recentUsers = $this->database->queryValue(
                'SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );

            return [
                'total' => (int)($totalUsers ?: 0),
                'bots' => (int)($totalBots ?: 0),
                'active' => (int)($activeUsers ?: 0),
                'recent_24h' => (int)($recentUsers ?: 0),
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get user stats.', ['error' => $e->getMessage()]);
            return ['total' => 0, 'bots' => 0, 'active' => 0, 'recent_24h' => 0];
        }
    }

    /**
     * دریافت آمار گروه‌ها
     */
    private function getGroupStats(): array
    {
        try {
            $totalGroups = $this->database->queryValue('SELECT COUNT(*) FROM groups');
            $activeGroups = $this->database->queryValue(
                'SELECT COUNT(DISTINCT group_id) FROM group_members WHERE is_active = 1'
            );
            
            // تعداد کل اعضا در همه گروه‌ها
            $totalMembers = $this->database->queryValue('SELECT COUNT(*) FROM group_members WHERE is_active = 1');

            return [
                'total' => (int)($totalGroups ?: 0),
                'active' => (int)($activeGroups ?: 0),
                'total_members' => (int)($totalMembers ?: 0),
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get group stats.', ['error' => $e->getMessage()]);
            return ['total' => 0, 'active' => 0, 'total_members' => 0];
        }
    }

    /**
     * دریافت آمار ادمین‌ها
     */
    private function getAdminStats(): array
    {
        try {
            $totalAdmins = $this->database->queryValue('SELECT COUNT(*) FROM admins WHERE is_active = 1');
            $superAdmins = $this->database->queryValue(
                'SELECT COUNT(*) FROM admins WHERE level = "super_admin" AND is_active = 1'
            );
            $groupsWithAdmins = $this->database->queryValue(
                'SELECT COUNT(DISTINCT group_id) FROM admins WHERE is_active = 1'
            );

            return [
                'total' => (int)($totalAdmins ?: 0),
                'super' => (int)($superAdmins ?: 0),
                'groups' => (int)($groupsWithAdmins ?: 0),
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get admin stats.', ['error' => $e->getMessage()]);
            return ['total' => 0, 'super' => 0, 'groups' => 0];
        }
    }

    /**
     * دریافت آمار اخطارها
     */
    private function getWarnStats(): array
    {
        try {
            $totalWarns = $this->database->queryValue('SELECT COUNT(*) FROM warns WHERE expires_at > NOW()');
            $totalWarnsAll = $this->database->queryValue('SELECT COUNT(*) FROM warns');
            $topUsers = $this->database->query(
                'SELECT user_id, COUNT(*) as count 
                 FROM warns 
                 WHERE expires_at > NOW() 
                 GROUP BY user_id 
                 ORDER BY count DESC 
                 LIMIT 5'
            );

            return [
                'active' => (int)($totalWarns ?: 0),
                'total' => (int)($totalWarnsAll ?: 0),
                'top_users' => $topUsers ?: [],
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get warn stats.', ['error' => $e->getMessage()]);
            return ['active' => 0, 'total' => 0, 'top_users' => []];
        }
    }

    /**
     * دریافت آمار قفل‌ها
     */
    private function getLockStats(): array
    {
        try {
            $totalLocks = $this->database->queryValue('SELECT COUNT(*) FROM group_locks WHERE is_active = 1');
            $groupsWithLocks = $this->database->queryValue(
                'SELECT COUNT(DISTINCT group_id) FROM group_locks WHERE is_active = 1'
            );
            
            // پرکاربردترین قفل‌ها
            $topLocks = $this->database->query(
                'SELECT lock_type, COUNT(*) as count 
                 FROM group_locks 
                 WHERE is_active = 1 
                 GROUP BY lock_type 
                 ORDER BY count DESC 
                 LIMIT 5'
            );

            return [
                'total' => (int)($totalLocks ?: 0),
                'groups' => (int)($groupsWithLocks ?: 0),
                'top_locks' => $topLocks ?: [],
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get lock stats.', ['error' => $e->getMessage()]);
            return ['total' => 0, 'groups' => 0, 'top_locks' => []];
        }
    }

    /**
     * دریافت آمار سیستم
     */
    private function getSystemStats(): array
    {
        // زمان اجرا
        $uptime = time() - (int)($this->startTime);
        
        // اطلاعات دیتابیس
        $queryCount = $this->database->getQueryCount();
        
        // اطلاعات کش
        $cacheStats = $this->cache->getStats();
        
        // اطلاعات سرور
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : null;

        return [
            'uptime' => $uptime,
            'query_count' => $queryCount,
            'cache_files' => $cacheStats['file_count'] ?? 0,
            'cache_size' => $cacheStats['total_size'] ?? 0,
            'memory_usage' => $memoryUsage,
            'peak_memory' => $peakMemory,
            'load_average' => $loadAverage,
            'php_version' => PHP_VERSION,
        ];
    }

    // ============================================================
    // تولید پیام
    // ============================================================

    /**
     * تولید پیام آمار
     */
    private function generateStatsMessage(
        array $userStats,
        array $groupStats,
        array $adminStats,
        array $warnStats,
        array $lockStats,
        array $systemStats
    ): string {
        $messageText = "📊 **آمار کامل ربات**\n";
        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        // آمار کاربران
        $messageText .= "👤 **آمار کاربران**\n";
        $messageText .= "  • تعداد کل: {$userStats['total']}\n";
        $messageText .= "  • ربات‌ها: {$userStats['bots']}\n";
        $messageText .= "  • کاربران فعال: {$userStats['active']}\n";
        $messageText .= "  • جدید (۲۴ ساعت): {$userStats['recent_24h']}\n\n";

        // آمار گروه‌ها
        $messageText .= "👥 **آمار گروه‌ها**\n";
        $messageText .= "  • تعداد کل: {$groupStats['total']}\n";
        $messageText .= "  • گروه‌های فعال: {$groupStats['active']}\n";
        $messageText .= "  • کل اعضا: {$groupStats['total_members']}\n\n";

        // آمار ادمین‌ها
        $messageText .= "🔑 **آمار ادمین‌ها**\n";
        $messageText .= "  • تعداد کل: {$adminStats['total']}\n";
        $messageText .= "  • ادمین‌های ارشد: {$adminStats['super']}\n";
        $messageText .= "  • گروه‌های دارای ادمین: {$adminStats['groups']}\n\n";

        // آمار اخطارها
        $messageText .= "⚠️ **آمار اخطارها**\n";
        $messageText .= "  • اخطارهای فعال: {$warnStats['active']}\n";
        $messageText .= "  • کل اخطارها: {$warnStats['total']}\n";
        
        if (!empty($warnStats['top_users'])) {
            $messageText .= "  • کاربران با بیشترین اخطار:\n";
            foreach ($warnStats['top_users'] as $index => $warn) {
                $num = $index + 1;
                $messageText .= "    {$num}. ID: {$warn['user_id']} ({$warn['count']} اخطار)\n";
            }
        }
        $messageText .= "\n";

        // آمار قفل‌ها
        $messageText .= "🔒 **آمار قفل‌ها**\n";
        $messageText .= "  • قفل‌های فعال: {$lockStats['total']}\n";
        $messageText .= "  • گروه‌های دارای قفل: {$lockStats['groups']}\n";
        
        if (!empty($lockStats['top_locks'])) {
            $messageText .= "  • پرکاربردترین قفل‌ها:\n";
            foreach ($lockStats['top_locks'] as $index => $lock) {
                $num = $index + 1;
                $lockName = $this->getLockName($lock['lock_type']);
                $messageText .= "    {$num}. {$lockName} ({$lock['count']} بار)\n";
            }
        }
        $messageText .= "\n";

        // آمار سیستم
        $messageText .= "💻 **آمار سیستم**\n";
        $messageText .= "  • زمان اجرا: " . $this->formatUptime($systemStats['uptime']) . "\n";
        $messageText .= "  • کوئری‌های دیتابیس: {$systemStats['query_count']}\n";
        $messageText .= "  • فایل‌های کش: {$systemStats['cache_files']}\n";
        $messageText .= "  • حجم کش: " . $this->formatBytes($systemStats['cache_size']) . "\n";
        $messageText .= "  • حافظه مصرفی: " . $this->formatBytes($systemStats['memory_usage']) . "\n";
        $messageText .= "  • حداکثر حافظه: " . $this->formatBytes($systemStats['peak_memory']) . "\n";
        $messageText .= "  • PHP: {$systemStats['php_version']}\n";

        if ($systemStats['load_average'] !== null) {
            $messageText .= "  • بار سرور: " . implode(', ', $systemStats['load_average']) . "\n";
        }

        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $messageText .= "🔄 آخرین به‌روزرسانی: " . date('Y-m-d H:i:s');

        return $messageText;
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * دریافت نام فارسی قفل
     */
    private function getLockName(string $lockType): string
    {
        $names = [
            'links' => 'لینک',
            'tags' => 'منشن',
            'hashtags' => 'هشتگ',
            'commands' => 'دستورات',
            'arabic' => 'متن عربی',
            'english' => 'متن انگلیسی',
            'persian' => 'متن فارسی',
            'spam' => 'اسپم',
            'sticker' => 'استیکر',
            'video' => 'ویدیو',
            'audio' => 'صدا',
            'document' => 'فایل',
            'voice' => 'ویس',
            'photo' => 'عکس',
            'gif' => 'GIF',
        ];
        return $names[$lockType] ?? $lockType;
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
     * تبدیل زمان اجرا به فرمت خوانا
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days} روز";
        }
        if ($hours > 0) {
            $parts[] = "{$hours} ساعت";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes} دقیقه";
        }
        if ($secs > 0 && $days === 0) {
            $parts[] = "{$secs} ثانیه";
        }

        return implode(' ', $parts) ?: 'کمتر از یک ثانیه';
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