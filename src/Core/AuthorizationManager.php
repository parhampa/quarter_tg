<?php

declare(strict_types=1);

namespace QuarterTg\Managers;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;

/**
 * مدیریت مجوزها و دسترسی کاربران
 * 
 * مسئولیتها:
 * - بررسی مالک (Owner) ربات
 * - بررسی ادمین‌های گروه
 * - بررسی مجوز اجرای دستورات برای کاربران
 * - کش کردن سطح دسترسی برای کاهش کوئری
 */
class AuthorizationManager
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    private AdminManager $adminManager;
    private int $ownerId;
    
    /** @var array کش سطح دسترسی کاربران (در طول درخواست) */
    private array $accessCache = [];
    
    /** @var array لیست دستورات عمومی (نیاز به مجوز خاص ندارند) */
    private array $publicCommands = ['start', 'help', 'ping', 'info'];

    public function __construct(
        Database $db,
        Cache $cache,
        Logger $logger,
        AdminManager $adminManager,
        int $ownerId
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->adminManager = $adminManager;
        $this->ownerId = $ownerId;
    }

    // ============================================================
    // متدهای اصلی بررسی دسترسی
    // ============================================================

    /**
     * بررسی اینکه آیا کاربر میتواند یک دستور را اجرا کند؟
     * 
     * @param int $chatId شناسه گروه
     * @param int $userId شناسه کاربر
     * @param string $commandName نام دستور (بدون /)
     * @return bool
     */
    public function canExecuteCommand(int $chatId, int $userId, string $commandName): bool
    {
        // 1. دستورات عمومی همیشه مجاز هستند
        if (in_array(strtolower($commandName), $this->publicCommands, true)) {
            return true;
        }

        // 2. مالک ربات همه دستورات را میتواند اجرا کند
        if ($this->isOwner($userId)) {
            return true;
        }

        // 3. اگر دستور نیاز به ادمین دارد
        if ($this->isAdminCommand($commandName)) {
            return $this->isAdmin($chatId, $userId);
        }

        // 4. اگر دستور نیاز به moderator دارد (سطح پایین‌تر از ادمین)
        if ($this->isModeratorCommand($commandName)) {
            return $this->isModerator($chatId, $userId);
        }

        // 5. دستورات عمومی برای همه کاربران
        return true;
    }

    /**
     * بررسی اینکه آیا کاربر ادمین گروه است؟
     */
    public function isAdmin(int $chatId, int $userId): bool
    {
        // مالک همیشه ادمین است
        if ($this->isOwner($userId)) {
            return true;
        }

        $cacheKey = "admin_{$chatId}_{$userId}";
        if (isset($this->accessCache[$cacheKey])) {
            return $this->accessCache[$cacheKey];
        }

        // تلاش برای خواندن از کش
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->accessCache[$cacheKey] = (bool)$cached;
            return (bool)$cached;
        }

        // بررسی در دیتابیس
        try {
            $result = $this->db->queryValue(
                'SELECT COUNT(*) FROM admins WHERE group_id = ? AND user_id = ? AND is_active = 1',
                [$chatId, $userId]
            );
            $isAdmin = $result > 0;
            
            // ذخیره در کش (۵ دقیقه)
            $this->cache->set($cacheKey, $isAdmin, 300);
            $this->accessCache[$cacheKey] = $isAdmin;
            
            return $isAdmin;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to check admin status.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false; // در صورت خطا، دسترسی داده نمیشود
        }
    }

    /**
     * بررسی اینکه آیا کاربر Moderator است؟ (سطح پایین‌تر از ادمین)
     * Moderator میتواند برخی دستورات محدود را اجرا کند (مثل پاک کردن پیام، اخطار)
     */
    public function isModerator(int $chatId, int $userId): bool
    {
        // ادمین‌ها و مالک moderator هم هستند
        if ($this->isAdmin($chatId, $userId)) {
            return true;
        }

        $cacheKey = "moderator_{$chatId}_{$userId}";
        if (isset($this->accessCache[$cacheKey])) {
            return $this->accessCache[$cacheKey];
        }

        // تلاش برای خواندن از کش
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->accessCache[$cacheKey] = (bool)$cached;
            return (bool)$cached;
        }

        // بررسی در دیتابیس (فرض میکنیم جدول moderators داریم)
        try {
            $result = $this->db->queryValue(
                'SELECT COUNT(*) FROM moderators WHERE group_id = ? AND user_id = ? AND is_active = 1',
                [$chatId, $userId]
            );
            $isModerator = $result > 0;
            
            $this->cache->set($cacheKey, $isModerator, 300);
            $this->accessCache[$cacheKey] = $isModerator;
            
            return $isModerator;
        } catch (\Throwable $e) {
            // اگر جدول moderators وجود نداشته باشد، فقط ادمین‌ها را در نظر میگیریم
            return $this->isAdmin($chatId, $userId);
        }
    }

    /**
     * بررسی اینکه کاربر مالک ربات است؟
     */
    public function isOwner(int $userId): bool
    {
        return $userId === $this->ownerId && $this->ownerId > 0;
    }

    /**
     * دریافت سطح دسترسی کاربر به صورت رشته
     */
    public function getRole(int $chatId, int $userId): string
    {
        if ($this->isOwner($userId)) {
            return 'owner';
        }
        if ($this->isAdmin($chatId, $userId)) {
            return 'admin';
        }
        if ($this->isModerator($chatId, $userId)) {
            return 'moderator';
        }
        return 'member';
    }

    // ============================================================
    // متدهای کمکی برای تشخیص نوع دستور
    // ============================================================

    /**
     * آیا این دستور نیاز به سطح ادمین دارد؟
     */
    private function isAdminCommand(string $commandName): bool
    {
        $adminCommands = [
            'ban', 'unban', 'mute', 'unmute', 'kick', 'promote', 'demote',
            'setadmin', 'removeadmin', 'lock', 'unlock', 'settings',
            'setwelcome', 'setrules', 'clear', 'deleteall'
        ];
        return in_array(strtolower($commandName), $adminCommands, true);
    }

    /**
     * آیا این دستور نیاز به سطح Moderator دارد؟
     */
    private function isModeratorCommand(string $commandName): bool
    {
        $moderatorCommands = [
            'warn', 'unwarn', 'warns', 'del', 'delete', 'pin', 'unpin',
            'mute', 'unmute', 'kick'
        ];
        return in_array(strtolower($commandName), $moderatorCommands, true);
    }

    // ============================================================
    // متدهای مدیریت دسترسی (برای تغییرات پویا)
    // ============================================================

    /**
     * پاک کردن کش دسترسی یک کاربر خاص
     */
    public function clearCache(int $chatId, int $userId): void
    {
        $keys = [
            "admin_{$chatId}_{$userId}",
            "moderator_{$chatId}_{$userId}",
        ];
        foreach ($keys as $key) {
            $this->cache->delete($key);
            unset($this->accessCache[$key]);
        }
        $this->logger->debug('Authorization cache cleared.', ['chat' => $chatId, 'user' => $userId]);
    }

    /**
     * پاک کردن همه کش دسترسی یک گروه
     */
    public function clearGroupCache(int $chatId): void
    {
        // این متد باید با اسکن کلیدهای کش انجام شود، اما فعلاً ساده پیادهسازی میشود
        $this->logger->info('Clear group authorization cache requested.', ['chat' => $chatId]);
        // در آینده میتوان از الگوی کش با پیشوند استفاده کرد
    }

    /**
     * اضافه کردن یک دستور به لیست دستورات عمومی
     */
    public function addPublicCommand(string $command): void
    {
        $command = strtolower(trim($command));
        if (!empty($command) && !in_array($command, $this->publicCommands, true)) {
            $this->publicCommands[] = $command;
        }
    }

    /**
     * حذف یک دستور از لیست دستورات عمومی
     */
    public function removePublicCommand(string $command): void
    {
        $command = strtolower(trim($command));
        $key = array_search($command, $this->publicCommands, true);
        if ($key !== false) {
            unset($this->publicCommands[$key]);
            $this->publicCommands = array_values($this->publicCommands);
        }
    }

    /**
     * دریافت لیست دستورات عمومی
     */
    public function getPublicCommands(): array
    {
        return $this->publicCommands;
    }

    // ============================================================
    // متدهای پیشرفته (برای مدیریت دسترسیهای دقیق‌تر)
    // ============================================================

    /**
     * بررسی دسترسی برای یک عمل خاص (غیر از دستورات)
     * مثال: آیا کاربر میتواند پیامها را حذف کند؟
     */
    public function canPerformAction(int $chatId, int $userId, string $action): bool
    {
        // لیست اقدامات و سطح دسترسی مورد نیاز
        $requiredRole = match ($action) {
            'delete_message', 'pin_message', 'unpin_message' => 'moderator',
            'ban_user', 'mute_user', 'change_settings', 'add_admin' => 'admin',
            default => 'member',
        };

        return $this->hasRole($chatId, $userId, $requiredRole);
    }

    /**
     * بررسی اینکه کاربر حداقل یک نقش خاص را دارد
     */
    public function hasRole(int $chatId, int $userId, string $requiredRole): bool
    {
        $roleLevel = [
            'member' => 0,
            'moderator' => 1,
            'admin' => 2,
            'owner' => 3,
        ];

        $userRole = $this->getRole($chatId, $userId);
        $userLevel = $roleLevel[$userRole] ?? 0;
        $requiredLevel = $roleLevel[$requiredRole] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * دریافت لیست ادمین‌های گروه (با کش)
     */
    public function getAdmins(int $chatId): array
    {
        $cacheKey = "admins_list_{$chatId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (array)$cached;
        }

        try {
            $admins = $this->db->query(
                'SELECT user_id FROM admins WHERE group_id = ? AND is_active = 1',
                [$chatId]
            );
            $adminIds = array_column($admins, 'user_id');
            
            // اضافه کردن مالک به لیست
            if (!in_array($this->ownerId, $adminIds, true)) {
                $adminIds[] = $this->ownerId;
            }
            
            $this->cache->set($cacheKey, $adminIds, 600); // ۱۰ دقیقه
            return $adminIds;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get admins list.', ['chat' => $chatId, 'error' => $e->getMessage()]);
            return [$this->ownerId];
        }
    }
}