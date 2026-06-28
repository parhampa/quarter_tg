<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت اختصاصی ادمین‌ها
 * شامل عملیات افزودن، حذف، لیست کردن و مدیریت سطوح دسترسی
 * این کلاس مکمل AuthorizationManager است و عملیات CRUD را انجام می‌دهد
 */
class AdminManager
{
    private $db;
    private $cache;
    private $logger;
    private $adminTable = 'bot_admins';
    private $subAdminTable = 'bot_sub_admins';
    private $cachePrefix = 'admin_mgr_';
    private $cacheTtl = 300; // 5 minutes

    /**
     * @param Database $db
     * @param Cache $cache
     * @param Logger|null $logger
     */
    public function __construct(Database $db, Cache $cache, $logger = null)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * افزودن ادمین اصلی به گروه
     * @param int $groupId آیدی گروه
     * @param int $userId آیدی کاربر جدید
     * @param int $addedBy آیدی ادمین افزاینده
     * @param string|null $username یوزرنیم کاربر (اختیاری)
     * @param string|null $firstName نام کوچک
     * @param string|null $lastName نام خانوادگی
     * @return bool
     */
    public function addAdmin(
        int $groupId,
        int $userId,
        int $addedBy,
        ?string $username = null,
        ?string $firstName = null,
        ?string $lastName = null
    ): bool {
        // بررسی وجود کاربر در جدول ادمین‌ها
        $existing = $this->getAdmin($groupId, $userId);
        if ($existing) {
            return true; // قبلاً ادمین است
        }

        // اگر ساب‌ادمین است، ابتدا حذفش می‌کنیم
        if ($this->isSubAdmin($groupId, $userId)) {
            $this->removeSubAdmin($groupId, $userId);
        }

        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'added_by' => $addedBy,
            'added_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->db->insert($this->adminTable, $data);

        if ($result) {
            // پاک کردن کش
            $this->clearCache($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("Admin $userId added to group $groupId by $addedBy");
            }
            return true;
        }

        return false;
    }

    /**
     * حذف ادمین اصلی از گروه
     */
    public function removeAdmin(int $groupId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->adminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$groupId, $userId]);

        if ($result > 0) {
            $this->clearCache($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("Admin $userId removed from group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * افزودن ساب‌ادمین به گروه
     */
    public function addSubAdmin(
        int $groupId,
        int $userId,
        int $addedBy,
        ?string $username = null,
        ?string $firstName = null,
        ?string $lastName = null
    ): bool {
        // اگر ادمین اصلی است، نمی‌توان ساب‌ادمین کرد
        if ($this->isMainAdmin($groupId, $userId)) {
            return false;
        }

        // بررسی وجود کاربر در جدول ساب‌ادمین‌ها
        $existing = $this->getSubAdmin($groupId, $userId);
        if ($existing) {
            return true; // قبلاً ساب‌ادمین است
        }

        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'added_by' => $addedBy,
            'added_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->db->insert($this->subAdminTable, $data);

        if ($result) {
            $this->clearCache($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("SubAdmin $userId added to group $groupId by $addedBy");
            }
            return true;
        }

        return false;
    }

    /**
     * حذف ساب‌ادمین از گروه
     */
    public function removeSubAdmin(int $groupId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->subAdminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$groupId, $userId]);

        if ($result > 0) {
            $this->clearCache($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("SubAdmin $userId removed from group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * دریافت اطلاعات یک ادمین اصلی
     * @return array|null
     */
    public function getAdmin(int $groupId, int $userId): ?array
    {
        $cacheKey = $this->cachePrefix . 'admin_' . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->adminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $userId]);

        if ($result) {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * دریافت اطلاعات یک ساب‌ادمین
     * @return array|null
     */
    public function getSubAdmin(int $groupId, int $userId): ?array
    {
        $cacheKey = $this->cachePrefix . 'subadmin_' . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->subAdminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $userId]);

        if ($result) {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * دریافت لیست تمام ادمین‌های اصلی یک گروه
     * @return array
     */
    public function getAdmins(int $groupId): array
    {
        $cacheKey = $this->cachePrefix . 'admins_list_' . $groupId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->adminTable} WHERE group_id = ? ORDER BY added_at ASC";
        $result = $this->db->query($sql, [$groupId]);

        $this->cache->set($cacheKey, $result, $this->cacheTtl);
        return $result;
    }

    /**
     * دریافت لیست تمام ساب‌ادمین‌های یک گروه
     * @return array
     */
    public function getSubAdmins(int $groupId): array
    {
        $cacheKey = $this->cachePrefix . 'subadmins_list_' . $groupId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->subAdminTable} WHERE group_id = ? ORDER BY added_at ASC";
        $result = $this->db->query($sql, [$groupId]);

        $this->cache->set($cacheKey, $result, $this->cacheTtl);
        return $result;
    }

    /**
     * دریافت لیست تمام مدیران (ادمین + ساب‌ادمین) با سطح دسترسی
     * @param int $groupId
     * @param int|null $ownerId آیدی مالک اصلی (برای اضافه کردن به لیست)
     * @return array
     */
    public function getAllAdmins(int $groupId, ?int $ownerId = null): array
    {
        $admins = $this->getAdmins($groupId);
        $subAdmins = $this->getSubAdmins($groupId);

        $result = [];

        // اضافه کردن مالک اصلی در صورت مشخص بودن
        if ($ownerId !== null) {
            $result[] = [
                'user_id' => $ownerId,
                'username' => 'OWNER',
                'first_name' => 'Owner',
                'last_name' => '',
                'level' => 'owner',
                'added_by' => null,
                'added_at' => date('Y-m-d H:i:s'),
            ];
        }

        foreach ($admins as $admin) {
            $admin['level'] = 'admin';
            $result[] = $admin;
        }

        foreach ($subAdmins as $subAdmin) {
            $subAdmin['level'] = 'subadmin';
            $result[] = $subAdmin;
        }

        return $result;
    }

    /**
     * بررسی اینکه کاربر ادمین اصلی است
     */
    public function isMainAdmin(int $groupId, int $userId): bool
    {
        $admin = $this->getAdmin($groupId, $userId);
        return $admin !== null;
    }

    /**
     * بررسی اینکه کاربر ساب‌ادمین است
     */
    public function isSubAdmin(int $groupId, int $userId): bool
    {
        $subAdmin = $this->getSubAdmin($groupId, $userId);
        return $subAdmin !== null;
    }

    /**
     * بررسی اینکه کاربر ادمین است (ادمین یا ساب‌ادمین)
     */
    public function isAdmin(int $groupId, int $userId): bool
    {
        return $this->isMainAdmin($groupId, $userId) || $this->isSubAdmin($groupId, $userId);
    }

    /**
     * به‌روزرسانی اطلاعات یک ادمین (یوزرنیم، نام، ...)
     */
    public function updateAdminInfo(int $groupId, int $userId, array $data): bool
    {
        $allowedFields = ['username', 'first_name', 'last_name'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        // ابتدا بررسی می‌کنیم که ادمین اصلی است یا ساب‌ادمین
        if ($this->isMainAdmin($groupId, $userId)) {
            $result = $this->db->update($this->adminTable, $updateData, [
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);
        } elseif ($this->isSubAdmin($groupId, $userId)) {
            $result = $this->db->update($this->subAdminTable, $updateData, [
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);
        } else {
            return false;
        }

        if ($result > 0) {
            $this->clearCache($groupId, $userId);
            return true;
        }

        return false;
    }

    /**
     * ارتقا ساب‌ادمین به ادمین اصلی
     */
    public function promoteToAdmin(int $groupId, int $userId, int $promotedBy): bool
    {
        // بررسی اینکه ساب‌ادمین است
        $subAdmin = $this->getSubAdmin($groupId, $userId);
        if (!$subAdmin) {
            return false;
        }

        // افزودن به ادمین‌های اصلی
        $result = $this->addAdmin(
            $groupId,
            $userId,
            $promotedBy,
            $subAdmin['username'],
            $subAdmin['first_name'],
            $subAdmin['last_name']
        );

        if ($result) {
            // حذف از ساب‌ادمین‌ها
            $this->removeSubAdmin($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("SubAdmin $userId promoted to Admin in group $groupId by $promotedBy");
            }
            return true;
        }

        return false;
    }

    /**
     * تنزل ادمین اصلی به ساب‌ادمین
     */
    public function demoteToSubAdmin(int $groupId, int $userId, int $demotedBy): bool
    {
        // بررسی اینکه ادمین اصلی است
        $admin = $this->getAdmin($groupId, $userId);
        if (!$admin) {
            return false;
        }

        // افزودن به ساب‌ادمین‌ها
        $result = $this->addSubAdmin(
            $groupId,
            $userId,
            $demotedBy,
            $admin['username'],
            $admin['first_name'],
            $admin['last_name']
        );

        if ($result) {
            // حذف از ادمین‌های اصلی
            $this->removeAdmin($groupId, $userId);
            
            if ($this->logger) {
                $this->logger->info("Admin $userId demoted to SubAdmin in group $groupId by $demotedBy");
            }
            return true;
        }

        return false;
    }

    /**
     * دریافت تعداد ادمین‌های یک گروه
     */
    public function countAdmins(int $groupId): int
    {
        $admins = $this->getAdmins($groupId);
        return count($admins);
    }

    /**
     * دریافت تعداد ساب‌ادمین‌های یک گروه
     */
    public function countSubAdmins(int $groupId): int
    {
        $subAdmins = $this->getSubAdmins($groupId);
        return count($subAdmins);
    }

    /**
     * پاک کردن کش مربوط به یک کاربر در یک گروه
     */
    private function clearCache(int $groupId, int $userId): void
    {
        $keys = [
            $this->cachePrefix . 'admin_' . $groupId . '_' . $userId,
            $this->cachePrefix . 'subadmin_' . $groupId . '_' . $userId,
            $this->cachePrefix . 'admins_list_' . $groupId,
            $this->cachePrefix . 'subadmins_list_' . $groupId,
        ];

        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    /**
     * پاک کردن تمام کش‌های مربوط به یک گروه
     */
    public function clearAllCache(int $groupId): void
    {
        // در صورت وجود متد deleteByPattern در Cache، استفاده شود
        // پیاده‌سازی ساده: حذف کلیدهای مشخص
        $keys = [
            $this->cachePrefix . 'admins_list_' . $groupId,
            $this->cachePrefix . 'subadmins_list_' . $groupId,
        ];

        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }

    /**
     * تنظیم Logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    /**
     * تنظیم TTL کش
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = $ttl;
    }
}