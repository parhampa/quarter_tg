<?php

declare(strict_types=1);

namespace QuarterTg\Managers;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use Throwable;

/**
 * مدیریت قفل‌های گروه
 * 
 * مسئولیتها:
 * - فعال/غیرفعال کردن قفل‌ها روی انواع مختلف محتوا
 * - بررسی وضعیت قفل‌ها برای یک گروه
 * - کش کردن وضعیت قفل‌ها برای کاهش کوئری
 */
class LockManager
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    
    /** @var array لیست انواع قفل‌های پشتیبانی‌شده */
    private array $lockTypes = [];
    
    /** @var array کش وضعیت قفل‌ها برای هر گروه (در طول درخواست) */
    private array $lockCache = [];
    
    /** @var int زمان کش (پیشفرض ۵ دقیقه) */
    private int $cacheTtl = 300;

    public function __construct(
        Database $db,
        Cache $cache,
        Logger $logger,
        array $lockTypes = []
    ) {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
        
        // لیست پیشفرض قفلها اگر از config داده نشده باشد
        $this->lockTypes = !empty($lockTypes) ? $lockTypes : [
            'links', 'tags', 'hashtags', 'commands',
            'arabic', 'english', 'persian',
            'spam', 'sticker', 'video', 'audio',
            'document', 'voice', 'photo', 'gif'
        ];
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * دریافت لیست قفل‌های فعال برای یک گروه
     * 
     * @param int $groupId شناسه گروه
     * @return array لیست انواع قفل (مثلاً ['links', 'tags'])
     */
    public function getLocks(int $groupId): array
    {
        // بررسی کش دروندرخواستی
        $cacheKey = "group_locks_{$groupId}";
        if (isset($this->lockCache[$cacheKey])) {
            return $this->lockCache[$cacheKey];
        }

        // تلاش برای خواندن از کش (۵ دقیقه)
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $this->lockCache[$cacheKey] = $cached;
            return $cached;
        }

        // خواندن از دیتابیس
        try {
            $result = $this->db->query(
                'SELECT lock_type FROM group_locks WHERE group_id = ? AND is_active = 1',
                [$groupId]
            );
            
            $locks = array_column($result, 'lock_type');
            
            // فیلتر کردن قفل‌های ناشناخته (امنیت)
            $locks = array_intersect($locks, $this->lockTypes);
            
            // ذخیره در کش
            $this->cache->set($cacheKey, $locks, $this->cacheTtl);
            $this->lockCache[$cacheKey] = $locks;
            
            $this->logger->debug('Locks fetched from database.', ['group' => $groupId, 'count' => count($locks)]);
            return $locks;
            
        } catch (Throwable $e) {
            $this->logger->error('Failed to get locks for group.', [
                'group' => $groupId,
                'error' => $e->getMessage(),
            ]);
            return []; // در صورت خطا، هیچ قفلی فعال نیست (ایمن)
        }
    }

    /**
     * بررسی اینکه آیا یک نوع قفل خاص برای گروه فعال است؟
     */
    public function isLocked(int $groupId, string $lockType): bool
    {
        $locks = $this->getLocks($groupId);
        return in_array($lockType, $locks, true);
    }

    /**
     * فعال کردن یک قفل برای گروه
     * 
     * @param int $groupId شناسه گروه
     * @param string $lockType نوع قفل (مثلاً 'links')
     * @return bool موفقیت
     */
    public function setLock(int $groupId, string $lockType): bool
    {
        // اعتبارسنجی نوع قفل
        if (!in_array($lockType, $this->lockTypes, true)) {
            $this->logger->warning('Invalid lock type.', ['lock' => $lockType, 'group' => $groupId]);
            return false;
        }

        try {
            // بررسی اینکه آیا قبلاً فعال است؟
            $exists = $this->db->queryValue(
                'SELECT COUNT(*) FROM group_locks WHERE group_id = ? AND lock_type = ? AND is_active = 1',
                [$groupId, $lockType]
            );
            
            if ($exists > 0) {
                $this->logger->debug('Lock already active.', ['group' => $groupId, 'lock' => $lockType]);
                return true;
            }

            // غیرفعال کردن نسخه‌های غیرفعال قبلی (اگر وجود داشتند)
            $this->db->execute(
                'UPDATE group_locks SET is_active = 0 WHERE group_id = ? AND lock_type = ?',
                [$groupId, $lockType]
            );

            // درج قفل جدید
            $result = $this->db->insert('group_locks', [
                'group_id'   => $groupId,
                'lock_type'  => $lockType,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            if ($result !== false) {
                $this->clearCache($groupId);
                $this->logger->info('Lock enabled.', ['group' => $groupId, 'lock' => $lockType]);
                return true;
            }

            return false;
            
        } catch (Throwable $e) {
            $this->logger->error('Failed to set lock.', [
                'group' => $groupId,
                'lock'  => $lockType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * غیرفعال کردن یک قفل برای گروه
     */
    public function removeLock(int $groupId, string $lockType): bool
    {
        if (!in_array($lockType, $this->lockTypes, true)) {
            $this->logger->warning('Invalid lock type for removal.', ['lock' => $lockType, 'group' => $groupId]);
            return false;
        }

        try {
            $result = $this->db->execute(
                'UPDATE group_locks SET is_active = 0 WHERE group_id = ? AND lock_type = ?',
                [$groupId, $lockType]
            );

            if ($result >= 0) {
                $this->clearCache($groupId);
                $this->logger->info('Lock disabled.', ['group' => $groupId, 'lock' => $lockType]);
                return true;
            }

            return false;
            
        } catch (Throwable $e) {
            $this->logger->error('Failed to remove lock.', [
                'group' => $groupId,
                'lock'  => $lockType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * تغییر وضعیت یک قفل (Toggle) – اگر فعال باشد غیرفعال و بالعکس
     */
    public function toggleLock(int $groupId, string $lockType): bool
    {
        if ($this->isLocked($groupId, $lockType)) {
            return $this->removeLock($groupId, $lockType);
        } else {
            return $this->setLock($groupId, $lockType);
        }
    }

    // ============================================================
    // متدهای مدیریت کش
    // ============================================================

    /**
     * پاک کردن کش قفل‌های یک گروه
     */
    public function clearCache(int $groupId): void
    {
        $cacheKey = "group_locks_{$groupId}";
        $this->cache->delete($cacheKey);
        unset($this->lockCache[$cacheKey]);
        $this->logger->debug('Lock cache cleared.', ['group' => $groupId]);
    }

    /**
     * پاک کردن همه کش قفل‌ها (برای همه گروهها)
     * این متد باید با اسکن کلیدها انجام شود، اما فعلاً ساده پیادهسازی میشود
     */
    public function clearAllCache(): void
    {
        $this->logger->warning('Clear all lock cache requested, but not fully implemented.');
        // در آینده میتوان با الگوی پیشوند کش، کلیدهای مربوطه را حذف کرد
        $this->lockCache = [];
    }

    // ============================================================
    // متدهای مدیریت قفل‌های گروه (عملیات گروهی)
    // ============================================================

    /**
     * فعال کردن چند قفل به‌طور همزمان (با تراکنش)
     */
    public function setMultipleLocks(int $groupId, array $lockTypes): bool
    {
        $validLocks = array_intersect($lockTypes, $this->lockTypes);
        if (empty($validLocks)) {
            $this->logger->warning('No valid lock types provided.', ['group' => $groupId]);
            return false;
        }

        try {
            $this->db->beginTransaction();
            
            // غیرفعال کردن همه قفل‌های قبلی (اختیاری)
            $this->db->execute(
                'UPDATE group_locks SET is_active = 0 WHERE group_id = ?',
                [$groupId]
            );

            // درج قفل‌های جدید
            foreach ($validLocks as $lockType) {
                $this->db->insert('group_locks', [
                    'group_id'   => $groupId,
                    'lock_type'  => $lockType,
                    'is_active'  => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
            $this->clearCache($groupId);
            $this->logger->info('Multiple locks set.', ['group' => $groupId, 'locks' => $validLocks]);
            return true;
            
        } catch (Throwable $e) {
            $this->db->rollback();
            $this->logger->error('Failed to set multiple locks.', [
                'group' => $groupId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * غیرفعال کردن همه قفل‌های یک گروه
     */
    public function removeAllLocks(int $groupId): bool
    {
        try {
            $result = $this->db->execute(
                'UPDATE group_locks SET is_active = 0 WHERE group_id = ?',
                [$groupId]
            );

            $this->clearCache($groupId);
            $this->logger->info('All locks removed.', ['group' => $groupId]);
            return $result >= 0;
            
        } catch (Throwable $e) {
            $this->logger->error('Failed to remove all locks.', [
                'group' => $groupId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * اضافه کردن یک نوع قفل جدید به لیست پشتیبانی‌شده
     */
    public function addLockType(string $lockType): void
    {
        $lockType = trim($lockType);
        if (!empty($lockType) && !in_array($lockType, $this->lockTypes, true)) {
            $this->lockTypes[] = $lockType;
            $this->logger->debug('New lock type added.', ['lock' => $lockType]);
        }
    }

    /**
     * دریافت لیست همه انواع قفل‌های پشتیبانی‌شده
     */
    public function getSupportedLockTypes(): array
    {
        return $this->lockTypes;
    }

    /**
     * دریافت آمار قفل‌های فعال برای یک گروه
     */
    public function getLockStats(int $groupId): array
    {
        try {
            $total = $this->db->queryValue(
                'SELECT COUNT(*) FROM group_locks WHERE group_id = ? AND is_active = 1',
                [$groupId]
            );
            
            $locks = $this->getLocks($groupId);
            
            return [
                'total_active' => (int)$total,
                'lock_types'   => $locks,
                'supported'    => $this->lockTypes,
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to get lock stats.', ['group' => $groupId]);
            return ['total_active' => 0, 'lock_types' => [], 'supported' => $this->lockTypes];
        }
    }

    /**
     * تنظیم زمان TTL کش (به ثانیه)
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = max(60, $ttl); // حداقل ۱ دقیقه
    }
}