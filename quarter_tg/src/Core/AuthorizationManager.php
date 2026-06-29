<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;

/**
 * کلاس مدیریت دسترسی‌ها و احراز هویت کاربران
 * پشتیبانی از سطوح دسترسی: Owner, Admin, SubAdmin
 * امکان بررسی دسترسی به دستورات خاص با استفاده از جدول permissions
 */
class AuthorizationManager
{
    private $db;
    private $cache;
    private $ownerId;
    private $adminTable = 'bot_admins';
    private $subAdminTable = 'bot_sub_admins';
    private $permissionsTable = 'bot_permissions';
    private $cachePrefix = 'auth_';
    private $cacheTtl = 300; // 5 minutes

    /**
     * @param Database $db
     * @param Cache $cache
     * @param int $ownerId آیدی مالک اصلی (از config)
     */
    public function __construct(Database $db, Cache $cache, int $ownerId)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->ownerId = $ownerId;
    }

    /**
     * بررسی اینکه کاربر مالک اصلی است یا خیر
     */
    public function isOwner(int $userId): bool
    {
        return $userId === $this->ownerId;
    }

    /**
     * دریافت آیدی مالک اصلی
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * بررسی اینکه کاربر ادمین گروه است (شامل Owner, Admin, SubAdmin)
     */
    public function isAdmin(int $groupId, int $userId): bool
    {
        // مالک اصلی همیشه ادمین است
        if ($this->isOwner($userId)) {
            return true;
        }

        $cacheKey = $this->cachePrefix . 'admin_' . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool)$cached;
        }

        // بررسی در جدول ادمین‌ها (ادمین‌های اصلی)
        $sql = "SELECT id FROM {$this->adminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);
        
        if ($result !== false) {
            $this->cache->set($cacheKey, true, $this->cacheTtl);
            return true;
        }

        // بررسی در جدول ساب‌ادمین‌ها
        $sql = "SELECT id FROM {$this->subAdminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);

        $isAdmin = ($result !== false);
        $this->cache->set($cacheKey, $isAdmin, $this->cacheTtl);
        return $isAdmin;
    }

    /**
     * بررسی اینکه کاربر ادمین اصلی است (نه ساب‌ادمین)
     */
    public function isMainAdmin(int $groupId, int $userId): bool
    {
        if ($this->isOwner($userId)) {
            return true;
        }

        $cacheKey = $this->cachePrefix . 'main_admin_' . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool)$cached;
        }

        $sql = "SELECT id FROM {$this->adminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);

        $isMain = ($result !== false);
        $this->cache->set($cacheKey, $isMain, $this->cacheTtl);
        return $isMain;
    }

    /**
     * بررسی اینکه کاربر ساب‌ادمین است (نه ادمین اصلی و نه مالک)
     */
    public function isSubAdmin(int $groupId, int $userId): bool
    {
        if ($this->isOwner($userId) || $this->isMainAdmin($groupId, $userId)) {
            return false;
        }

        $cacheKey = $this->cachePrefix . 'sub_admin_' . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool)$cached;
        }

        $sql = "SELECT id FROM {$this->subAdminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);

        $isSub = ($result !== false);
        $this->cache->set($cacheKey, $isSub, $this->cacheTtl);
        return $isSub;
    }

    /**
     * دریافت سطح دسترسی کاربر
     * @return string 'owner', 'admin', 'subadmin', 'user'
     */
    public function getRole(int $groupId, int $userId): string
    {
        if ($this->isOwner($userId)) {
            return 'owner';
        }
        if ($this->isMainAdmin($groupId, $userId)) {
            return 'admin';
        }
        if ($this->isSubAdmin($groupId, $userId)) {
            return 'subadmin';
        }
        return 'user';
    }

    /**
     * بررسی اینکه کاربر می‌تواند یک دستور خاص را اجرا کند یا خیر
     * @param string $command نام دستور (با یا بدون /)
     */
    public function canExecute(int $groupId, int $userId, string $command): bool
    {
        // مالک اصلی همه دستورات را می‌تواند اجرا کند
        if ($this->isOwner($userId)) {
            return true;
        }

        // اگر کاربر ادمین نباشد، هیچ دستوری نمی‌تواند اجرا کند
        if (!$this->isAdmin($groupId, $userId)) {
            return false;
        }

        // پاک کردن / از ابتدای دستور
        $cleanCommand = ltrim($command, '/');

        // بررسی دسترسی در جدول permissions
        $cacheKey = $this->cachePrefix . 'perm_' . $groupId . '_' . $cleanCommand;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool)$cached;
        }

        // اگر رکوردی وجود نداشته باشد، به‌طور پیش‌فرض اجازه داده می‌شود
        $sql = "SELECT allowed FROM {$this->permissionsTable} WHERE group_id = ? AND command = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $cleanCommand]);

        if ($result === false) {
            // بدون تنظیمات خاص، اجازه داده می‌شود
            $this->cache->set($cacheKey, true, $this->cacheTtl);
            return true;
        }

        $allowed = (bool)$result;
        $this->cache->set($cacheKey, $allowed, $this->cacheTtl);
        return $allowed;
    }

    /**
     * تنظیم دسترسی یک دستور خاص برای یک گروه
     */
    public function setPermission(int $groupId, string $command, bool $allowed): bool
    {
        $cleanCommand = ltrim($command, '/');
        
        $sql = "INSERT INTO {$this->permissionsTable} (group_id, command, allowed) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE allowed = ?";
        $result = $this->db->execute($sql, [$groupId, $cleanCommand, (int)$allowed, (int)$allowed]);

        if ($result > 0) {
            // پاک کردن کش
            $this->cache->delete($this->cachePrefix . 'perm_' . $groupId . '_' . $cleanCommand);
            return true;
        }

        return false;
    }

    /**
     * حذف تنظیمات دسترسی یک دستور
     */
    public function removePermission(int $groupId, string $command): bool
    {
        $cleanCommand = ltrim($command, '/');
        $sql = "DELETE FROM {$this->permissionsTable} WHERE group_id = ? AND command = ?";
        $result = $this->db->execute($sql, [$groupId, $cleanCommand]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'perm_' . $groupId . '_' . $cleanCommand);
            return true;
        }

        return false;
    }

    /**
     * دریافت لیست دستورات ممنوع برای یک گروه
     * @return array
     */
    public function getDeniedCommands(int $groupId): array
    {
        $sql = "SELECT command FROM {$this->permissionsTable} WHERE group_id = ? AND allowed = 0";
        $results = $this->db->query($sql, [$groupId]);
        return array_column($results, 'command');
    }

    /**
     * دریافت تمام تنظیمات دسترسی یک گروه
     * @return array ['command' => 0/1, ...]
     */
    public function getAllPermissions(int $groupId): array
    {
        $sql = "SELECT command, allowed FROM {$this->permissionsTable} WHERE group_id = ?";
        $results = $this->db->query($sql, [$groupId]);
        $permissions = [];
        foreach ($results as $row) {
            $permissions[$row['command']] = (int)$row['allowed'];
        }
        return $permissions;
    }

    /**
     * افزودن ادمین به گروه
     * @return bool
     */
    public function addAdmin(int $groupId, int $userId, int $addedBy): bool
    {
        // اگر قبلاً ادمین است، نیازی نیست
        if ($this->isMainAdmin($groupId, $userId)) {
            return true;
        }

        // اگر ساب‌ادمین است، ابتدا حذفش می‌کنیم
        if ($this->isSubAdmin($groupId, $userId)) {
            $this->removeSubAdmin($groupId, $userId);
        }

        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'added_by' => $addedBy,
            'added_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->db->insert($this->adminTable, $data);

        if ($result) {
            // پاک کردن کش
            $this->cache->delete($this->cachePrefix . 'admin_' . $groupId . '_' . $userId);
            $this->cache->delete($this->cachePrefix . 'main_admin_' . $groupId . '_' . $userId);
            return true;
        }

        return false;
    }

    /**
     * حذف ادمین از گروه
     */
    public function removeAdmin(int $groupId, int $userId): bool
    {
        // نمی‌توان مالک اصلی را حذف کرد
        if ($this->isOwner($userId)) {
            return false;
        }

        $sql = "DELETE FROM {$this->adminTable} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$groupId, $userId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'admin_' . $groupId . '_' . $userId);
            $this->cache->delete($this->cachePrefix . 'main_admin_' . $groupId . '_' . $userId);
            return true;
        }

        return false;
    }

    /**
     * افزودن ساب‌ادمین به گروه
     */
    public function addSubAdmin(int $groupId, int $userId, int $addedBy): bool
    {
        // اگر ادمین اصلی یا مالک است، نمی‌توان ساب‌ادمین کرد
        if ($this->isMainAdmin($groupId, $userId) || $this->isOwner($userId)) {
            return false;
        }

        // اگر قبلاً ساب‌ادمین است، نیازی نیست
        if ($this->isSubAdmin($groupId, $userId)) {
            return true;
        }

        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'added_by' => $addedBy,
            'added_at' => date('Y-m-d H:i:s'),
        ];

        $result = $this->db->insert($this->subAdminTable, $data);

        if ($result) {
            $this->cache->delete($this->cachePrefix . 'admin_' . $groupId . '_' . $userId);
            $this->cache->delete($this->cachePrefix . 'sub_admin_' . $groupId . '_' . $userId);
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
            $this->cache->delete($this->cachePrefix . 'admin_' . $groupId . '_' . $userId);
            $this->cache->delete($this->cachePrefix . 'sub_admin_' . $groupId . '_' . $userId);
            return true;
        }

        return false;
    }

    /**
     * دریافت لیست ادمین‌های اصلی یک گروه
     * @return array
     */
    public function getAdmins(int $groupId): array
    {
        $sql = "SELECT * FROM {$this->adminTable} WHERE group_id = ?";
        return $this->db->query($sql, [$groupId]);
    }

    /**
     * دریافت لیست ساب‌ادمین‌های یک گروه
     * @return array
     */
    public function getSubAdmins(int $groupId): array
    {
        $sql = "SELECT * FROM {$this->subAdminTable} WHERE group_id = ?";
        return $this->db->query($sql, [$groupId]);
    }

    /**
     * دریافت لیست تمام مدیران (ادمین + ساب‌ادمین) یک گروه
     * @return array
     */
    public function getAllAdmins(int $groupId): array
    {
        $admins = $this->getAdmins($groupId);
        $subAdmins = $this->getSubAdmins($groupId);
        
        // اضافه کردن مالک اصلی به لیست
        $allAdmins = [
            [
                'user_id' => $this->ownerId,
                'username' => 'OWNER',
                'first_name' => 'Owner',
                'last_name' => '',
                'level' => 'owner',
                'added_by' => null,
                'added_at' => date('Y-m-d H:i:s'),
            ]
        ];

        foreach ($admins as $admin) {
            $admin['level'] = 'admin';
            $allAdmins[] = $admin;
        }

        foreach ($subAdmins as $subAdmin) {
            $subAdmin['level'] = 'subadmin';
            $allAdmins[] = $subAdmin;
        }

        return $allAdmins;
    }

    /**
     * پاک کردن تمام کش‌های احراز هویت برای یک گروه
     */
    public function clearGroupCache(int $groupId): void
    {
        // حذف کش‌های مرتبط با گروه
        // در صورت وجود متد deleteByPattern در Cache، استفاده شود
        // پیاده‌سازی ساده: حذف کلیدهای مشخص
        $keys = [
            $this->cachePrefix . 'admin_',
            $this->cachePrefix . 'main_admin_',
            $this->cachePrefix . 'sub_admin_',
        ];
        // در حال حاضر کش به‌صورت دستی پاک می‌شود
        // می‌توان با اسکن دایرکتوری کش، فایل‌های مربوطه را حذف کرد
    }

    /**
     * تنظیم TTL کش
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = $ttl;
    }
}