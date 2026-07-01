<?php

declare(strict_types=1);

namespace QuarterTg\Managers;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use Throwable;

/**
 * مدیریت اطلاعات کاربران
 * 
 * مسئولیتها:
 * - ثبت و به‌روزرسانی اطلاعات کاربران
 * - دریافت اطلاعات کاربر با کش
 * - جستجوی کاربران بر اساس نام، یوزرنیم یا شناسه
 * - دریافت لیست کاربران یک گروه
 * - آمارگیری از کاربران
 */
class UserManager
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;
    
    /** @var array کش دروندرخواستی اطلاعات کاربران */
    private array $userCache = [];
    
    /** @var int زمان کش (پیشفرض ۱ ساعت) */
    private int $cacheTtl = 3600;

    public function __construct(Database $db, Cache $cache, Logger $logger)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * ثبت یا به‌روزرسانی اطلاعات یک کاربر
     * 
     * @param int $userId شناسه کاربر
     * @param array $data اطلاعات کاربر (from تلگرام)
     * @return bool موفقیت
     */
    public function registerOrUpdate(int $userId, array $data): bool
    {
        if (empty($data) || $userId <= 0) {
            $this->logger->warning('Invalid user data for registration.', ['user_id' => $userId]);
            return false;
        }

        try {
            // استخراج اطلاعات
            $userData = [
                'user_id'    => $userId,
                'first_name' => $data['first_name'] ?? '',
                'last_name'  => $data['last_name'] ?? null,
                'username'   => $data['username'] ?? null,
                'language_code' => $data['language_code'] ?? null,
                'is_bot'     => (int)($data['is_bot'] ?? 0),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // بررسی وجود کاربر
            $exists = $this->db->queryValue(
                'SELECT COUNT(*) FROM users WHERE user_id = ?',
                [$userId]
            );

            if ($exists > 0) {
                // به‌روزرسانی
                $result = $this->db->update('users', $userData, ['user_id' => $userId]);
                $this->logger->debug('User updated.', ['user_id' => $userId]);
            } else {
                // درج جدید
                $userData['created_at'] = date('Y-m-d H:i:s');
                $result = $this->db->insert('users', $userData);
                $this->logger->debug('User registered.', ['user_id' => $userId]);
            }

            // پاک کردن کش
            $this->clearCache($userId);

            return $result !== false;

        } catch (Throwable $e) {
            $this->logger->error('Failed to register/update user.', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * دریافت اطلاعات یک کاربر با کش
     * 
     * @param int $userId شناسه کاربر
     * @return array|null اطلاعات کاربر یا null در صورت عدم وجود
     */
    public function getUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // کش دروندرخواستی
        $cacheKey = "user_{$userId}";
        if (isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }

        // کش فایل
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            $this->userCache[$cacheKey] = $cached;
            return $cached;
        }

        // خواندن از دیتابیس
        try {
            $user = $this->db->queryRow(
                'SELECT * FROM users WHERE user_id = ?',
                [$userId]
            );

            if ($user === false) {
                return null;
            }

            // ذخیره در کش
            $this->cache->set($cacheKey, $user, $this->cacheTtl);
            $this->userCache[$cacheKey] = $user;

            return $user;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get user.', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * دریافت چندین کاربر به‌طور همزمان (با کش)
     */
    public function getUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $users = [];
        $uncachedIds = [];

        // بررسی کش
        foreach ($userIds as $id) {
            $user = $this->getUser((int)$id);
            if ($user !== null) {
                $users[] = $user;
            } else {
                $uncachedIds[] = (int)$id;
            }
        }

        // اگر کاربرانی در کش نبودند، یکجا از دیتابیس بگیریم
        if (!empty($uncachedIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($uncachedIds), '?'));
                $results = $this->db->query(
                    "SELECT * FROM users WHERE user_id IN ({$placeholders})",
                    $uncachedIds
                );

                if (is_array($results)) {
                    foreach ($results as $user) {
                        $users[] = $user;
                        // ذخیره در کش
                        $key = "user_{$user['user_id']}";
                        $this->cache->set($key, $user, $this->cacheTtl);
                        $this->userCache[$key] = $user;
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error('Failed to get multiple users.', [
                    'count' => count($uncachedIds),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $users;
    }

    // ============================================================
    // متدهای جستجو
    // ============================================================

    /**
     * جستجوی کاربران بر اساس نام (firstName یا lastName)
     */
    public function searchByName(string $query, int $limit = 20): array
    {
        if (empty($query)) {
            return [];
        }

        try {
            $searchTerm = '%' . $query . '%';
            $results = $this->db->query(
                'SELECT * FROM users 
                 WHERE first_name LIKE ? OR last_name LIKE ? 
                 ORDER BY first_name ASC 
                 LIMIT ?',
                [$searchTerm, $searchTerm, $limit]
            );

            return is_array($results) ? $results : [];

        } catch (Throwable $e) {
            $this->logger->error('Failed to search users by name.', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * جستجوی کاربران بر اساس یوزرنیم
     */
    public function searchByUsername(string $username): ?array
    {
        if (empty($username)) {
            return null;
        }

        // حذف @ اگر وجود داشته باشد
        $username = ltrim($username, '@');

        try {
            $result = $this->db->queryRow(
                'SELECT * FROM users WHERE username = ?',
                [$username]
            );

            return $result !== false ? $result : null;

        } catch (Throwable $e) {
            $this->logger->error('Failed to search user by username.', [
                'username' => $username,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * جستجوی کاربران بر اساس شناسه کاربری
     */
    public function searchById(int $userId): ?array
    {
        return $this->getUser($userId);
    }

    // ============================================================
    // متدهای گروهی
    // ============================================================

    /**
     * دریافت لیست کاربران یک گروه (با کش)
     */
    public function getGroupMembers(int $chatId): array
    {
        $cacheKey = "group_members_{$chatId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        try {
            // فرض میکنیم جدول group_members وجود دارد
            // اگر وجود ندارد، میتوانیم از جدول کاربران فعال در گروه استفاده کنیم
            $results = $this->db->query(
                'SELECT u.* FROM users u
                 INNER JOIN group_members gm ON gm.user_id = u.user_id
                 WHERE gm.group_id = ? AND gm.is_active = 1
                 ORDER BY u.first_name ASC',
                [$chatId]
            );

            $members = is_array($results) ? $results : [];
            $this->cache->set($cacheKey, $members, 600); // ۱۰ دقیقه

            return $members;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get group members.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * ثبت عضویت کاربر در گروه
     */
    public function addGroupMember(int $chatId, int $userId): bool
    {
        try {
            // بررسی وجود کاربر
            $exists = $this->db->queryValue(
                'SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?',
                [$chatId, $userId]
            );

            if ($exists > 0) {
                // فعال کردن مجدد
                $this->db->update(
                    'group_members',
                    ['is_active' => 1, 'updated_at' => date('Y-m-d H:i:s')],
                    ['group_id' => $chatId, 'user_id' => $userId]
                );
            } else {
                // درج جدید
                $this->db->insert('group_members', [
                    'group_id' => $chatId,
                    'user_id'  => $userId,
                    'joined_at' => date('Y-m-d H:i:s'),
                    'is_active' => 1,
                ]);
            }

            // پاک کردن کش
            $this->cache->delete("group_members_{$chatId}");
            $this->logger->debug('Group member added.', ['chat' => $chatId, 'user' => $userId]);

            return true;

        } catch (Throwable $e) {
            $this->logger->error('Failed to add group member.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * حذف عضویت کاربر از گروه
     */
    public function removeGroupMember(int $chatId, int $userId): bool
    {
        try {
            $result = $this->db->update(
                'group_members',
                ['is_active' => 0, 'left_at' => date('Y-m-d H:i:s')],
                ['group_id' => $chatId, 'user_id' => $userId]
            );

            $this->cache->delete("group_members_{$chatId}");
            $this->logger->debug('Group member removed.', ['chat' => $chatId, 'user' => $userId]);

            return $result >= 0;

        } catch (Throwable $e) {
            $this->logger->error('Failed to remove group member.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ============================================================
    // متدهای آماری
    // ============================================================

    /**
     * تعداد کل کاربران ثبت‌شده
     */
    public function getTotalUsers(): int
    {
        try {
            $count = $this->db->queryValue('SELECT COUNT(*) FROM users');
            return (int)($count ?: 0);
        } catch (Throwable $e) {
            $this->logger->error('Failed to get total users.', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * تعداد کاربران یک گروه
     */
    public function getGroupMemberCount(int $chatId): int
    {
        $cacheKey = "group_member_count_{$chatId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_int($cached)) {
            return $cached;
        }

        try {
            $count = $this->db->queryValue(
                'SELECT COUNT(*) FROM group_members WHERE group_id = ? AND is_active = 1',
                [$chatId]
            );
            $count = (int)($count ?: 0);
            $this->cache->set($cacheKey, $count, 600); // ۱۰ دقیقه
            return $count;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get group member count.', ['chat' => $chatId]);
            return 0;
        }
    }

    /**
     * دریافت کاربران جدید (ثبت‌شده در ۲۴ ساعت گذشته)
     */
    public function getRecentUsers(int $limit = 10): array
    {
        try {
            $results = $this->db->query(
                'SELECT * FROM users 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                 ORDER BY created_at DESC 
                 LIMIT ?',
                [$limit]
            );
            return is_array($results) ? $results : [];

        } catch (Throwable $e) {
            $this->logger->error('Failed to get recent users.', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ============================================================
    // متدهای مدیریت کش
    // ============================================================

    /**
     * پاک کردن کش یک کاربر خاص
     */
    public function clearCache(int $userId): void
    {
        $cacheKey = "user_{$userId}";
        $this->cache->delete($cacheKey);
        unset($this->userCache[$cacheKey]);
        $this->logger->debug('User cache cleared.', ['user_id' => $userId]);
    }

    /**
     * پاک کردن همه کش کاربران
     * این متد باید با اسکن کلیدها انجام شود، اما فعلاً ساده پیادهسازی میشود
     */
    public function clearAllCache(): void
    {
        $this->logger->warning('Clear all user cache requested, but not fully implemented.');
        $this->userCache = [];
        // در آینده میتوان از الگوی پیشوند کش استفاده کرد
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