<?php

declare(strict_types=1);

namespace QuarterTg\Managers;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use Throwable;

/**
 * مدیریت ادمین‌های گروه
 * 
 * مسئولیتها:
 * - افزودن و حذف ادمین‌ها
 * - بررسی وضعیت ادمین بودن کاربران با کش
 * - دریافت لیست ادمین‌های گروه با کش
 * - پشتیبانی از سطوح دسترسی مختلف
 */
class AdminManager
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    private int $ownerId;
    
    /** @var array کش دروندرخواستی وضعیت ادمین‌ها */
    private array $adminCache = [];
    
    /** @var array کش دروندرخواستی لیست ادمین‌ها */
    private array $adminListCache = [];
    
    /** @var int زمان کش (پیشفرض ۵ دقیقه) */
    private int $cacheTtl = 300;

    public function __construct(
        Database $db,
        Cache $cache,
        Logger $logger,
        int $ownerId
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->ownerId = $ownerId;
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * بررسی اینکه یک کاربر ادمین گروه است یا خیر (با کش)
     * 
     * @param int $chatId شناسه گروه
     * @param int $userId شناسه کاربر
     * @return bool
     */
    public function isAdmin(int $chatId, int $userId): bool
    {
        // مالک همیشه ادمین است
        if ($userId === $this->ownerId) {
            return true;
        }

        // کش دروندرخواستی
        $cacheKey = "admin_{$chatId}_{$userId}";
        if (isset($this->adminCache[$cacheKey])) {
            return $this->adminCache[$cacheKey];
        }

        // کش فایل
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_bool($cached)) {
            $this->adminCache[$cacheKey] = $cached;
            return $cached;
        }

        // خواندن از دیتابیس
        try {
            $count = $this->db->queryValue(
                'SELECT COUNT(*) FROM admins WHERE group_id = ? AND user_id = ? AND is_active = 1',
                [$chatId, $userId]
            );
            $isAdmin = $count > 0;

            // ذخیره در کش
            $this->cache->set($cacheKey, $isAdmin, $this->cacheTtl);
            $this->adminCache[$cacheKey] = $isAdmin;

            return $isAdmin;

        } catch (Throwable $e) {
            $this->logger->error('Failed to check admin status.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * افزودن یک کاربر به عنوان ادمین گروه
     * 
     * @param int $chatId شناسه گروه
     * @param int $userId شناسه کاربر
     * @param string $level سطح دسترسی (admin, super_admin)
     * @param int|null $addedBy شناسه کاربر افزاینده (اختیاری)
     * @return bool موفقیت
     */
    public function addAdmin(int $chatId, int $userId, string $level = 'admin', ?int $addedBy = null): bool
    {
        // اعتبارسنجی سطح دسترسی
        $validLevels = ['admin', 'super_admin'];
        if (!in_array($level, $validLevels, true)) {
            $this->logger->warning('Invalid admin level.', ['level' => $level, 'user' => $userId]);
            return false;
        }

        // جلوگیری از افزودن مالک به عنوان ادمین معمولی (مالک همیشه ادمین است)
        if ($userId === $this->ownerId) {
            $this->logger->warning('Attempted to add owner as regular admin.', ['user' => $userId]);
            return false;
        }

        try {
            // بررسی اینکه قبلاً ادمین است یا خیر
            $exists = $this->db->queryValue(
                'SELECT COUNT(*) FROM admins WHERE group_id = ? AND user_id = ?',
                [$chatId, $userId]
            );

            if ($exists > 0) {
                // به‌روزرسانی وضعیت
                $this->db->update(
                    'admins',
                    [
                        'is_active' => 1,
                        'level' => $level,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    ['group_id' => $chatId, 'user_id' => $userId]
                );
            } else {
                // درج جدید
                $this->db->insert('admins', [
                    'group_id' => $chatId,
                    'user_id'  => $userId,
                    'level'    => $level,
                    'added_by' => $addedBy ?? 0,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // پاک کردن کش
            $this->clearCache($chatId, $userId);
            $this->logger->info('Admin added.', ['chat' => $chatId, 'user' => $userId, 'level' => $level]);

            return true;

        } catch (Throwable $e) {
            $this->logger->error('Failed to add admin.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * حذف یک کاربر از ادمین‌های گروه
     */
    public function removeAdmin(int $chatId, int $userId): bool
    {
        // جلوگیری از حذف مالک
        if ($userId === $this->ownerId) {
            $this->logger->warning('Attempted to remove owner from admins.', ['user' => $userId]);
            return false;
        }

        try {
            $result = $this->db->update(
                'admins',
                ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
                ['group_id' => $chatId, 'user_id' => $userId]
            );

            if ($result >= 0) {
                $this->clearCache($chatId, $userId);
                $this->logger->info('Admin removed.', ['chat' => $chatId, 'user' => $userId]);
                return true;
            }

            return false;

        } catch (Throwable $e) {
            $this->logger->error('Failed to remove admin.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * دریافت لیست کامل ادمین‌های یک گروه (با کش)
     * 
     * @param int $chatId شناسه گروه
     * @return array لیست ادمین‌ها
     */
    public function getAdmins(int $chatId): array
    {
        // کش دروندرخواستی
        $cacheKey = "admins_list_{$chatId}";
        if (isset($this->adminListCache[$cacheKey])) {
            return $this->adminListCache[$cacheKey];
        }

        // کش فایل
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $this->adminListCache[$cacheKey] = $cached;
            return $cached;
        }

        try {
            $admins = $this->db->query(
                'SELECT * FROM admins WHERE group_id = ? AND is_active = 1 ORDER BY level DESC, created_at ASC',
                [$chatId]
            );

            $adminList = is_array($admins) ? $admins : [];
            
            // اضافه کردن مالک به لیست (اگر در لیست نبود)
            $ownerExists = false;
            foreach ($adminList as $admin) {
                if ((int)$admin['user_id'] === $this->ownerId) {
                    $ownerExists = true;
                    break;
                }
            }
            
            if (!$ownerExists && $this->ownerId > 0) {
                $adminList[] = [
                    'user_id' => $this->ownerId,
                    'level' => 'owner',
                    'is_active' => 1,
                ];
            }

            // ذخیره در کش
            $this->cache->set($cacheKey, $adminList, $this->cacheTtl);
            $this->adminListCache[$cacheKey] = $adminList;

            return $adminList;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get admins list.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * دریافت شناسه‌های ادمین‌های یک گروه
     */
    public function getAdminIds(int $chatId): array
    {
        $admins = $this->getAdmins($chatId);
        return array_column($admins, 'user_id');
    }

    /**
     * دریافت سطح دسترسی یک کاربر در گروه
     */
    public function getAdminLevel(int $chatId, int $userId): ?string
    {
        // مالک بالاترین سطح را دارد
        if ($userId === $this->ownerId) {
            return 'owner';
        }

        $admins = $this->getAdmins($chatId);
        foreach ($admins as $admin) {
            if ((int)$admin['user_id'] === $userId) {
                return $admin['level'] ?? 'admin';
            }
        }

        return null;
    }

    // ============================================================
    // متدهای مدیریت کش
    // ============================================================

    /**
     * پاک کردن کش یک کاربر خاص در یک گروه
     */
    public function clearCache(int $chatId, int $userId): void
    {
        $keys = [
            "admin_{$chatId}_{$userId}",
            "admins_list_{$chatId}",
        ];
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
        unset($this->adminCache["admin_{$chatId}_{$userId}"]);
        unset($this->adminListCache["admins_list_{$chatId}"]);
        $this->logger->debug('Admin cache cleared.', ['chat' => $chatId, 'user' => $userId]);
    }

    /**
     * پاک کردن همه کش ادمین‌های یک گروه
     */
    public function clearGroupCache(int $chatId): void
    {
        $this->cache->delete("admins_list_{$chatId}");
        unset($this->adminListCache["admins_list_{$chatId}"]);
        // کلیدهای تکی با اسکن کامل قابل حذف هستند (در آینده با پیشوند کش)
        $this->logger->debug('Group admin cache cleared.', ['chat' => $chatId]);
    }

    // ============================================================
    // متدهای آماری
    // ============================================================

    /**
     * تعداد ادمین‌های فعال یک گروه
     */
    public function getAdminCount(int $chatId): int
    {
        $admins = $this->getAdmins($chatId);
        return count($admins);
    }

    /**
     * بررسی اینکه یک کاربر سطح دسترسی super_admin دارد یا خیر
     */
    public function isSuperAdmin(int $chatId, int $userId): bool
    {
        if ($userId === $this->ownerId) {
            return true;
        }

        $level = $this->getAdminLevel($chatId, $userId);
        return $level === 'super_admin';
    }

    /**
     * دریافت آمار کلی ادمین‌ها در همه گروه‌ها
     */
    public function getGlobalStats(): array
    {
        try {
            $total = $this->db->queryValue('SELECT COUNT(*) FROM admins WHERE is_active = 1');
            $superAdmins = $this->db->queryValue('SELECT COUNT(*) FROM admins WHERE level = "super_admin" AND is_active = 1');
            $groupsWithAdmins = $this->db->queryValue('SELECT COUNT(DISTINCT group_id) FROM admins WHERE is_active = 1');

            return [
                'total_admins' => (int)($total ?: 0),
                'super_admins' => (int)($superAdmins ?: 0),
                'groups_with_admins' => (int)($groupsWithAdmins ?: 0),
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get global admin stats.', ['error' => $e->getMessage()]);
            return [
                'total_admins' => 0,
                'super_admins' => 0,
                'groups_with_admins' => 0,
            ];
        }
    }

    // ============================================================
    // متدهای تنظیمات
    // ============================================================

    /**
     * تنظیم زمان TTL کش (به ثانیه)
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = max(60, $ttl);
    }
}