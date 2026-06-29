<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;

/**
 * کلاس مدیریت مجوزهای پیشرفته
 * این کلاس مکمل AuthorizationManager است و قابلیت‌های زیر را ارائه می‌دهد:
 * - مدیریت مجوزهای دستوری با سطح‌بندی دقیق‌تر
 * - مجوزهای مبتنی بر نقش (Role-based)
 * - مجوزهای موقت و زمان‌دار
 * - لاگ تغییرات مجوزها
 */
class PermissionManager
{
    private $db;
    private $cache;
    private $logger;
    private $table = 'bot_permissions';
    private $cachePrefix = 'perm_';
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
     * تنظیم مجوز یک دستور برای گروه
     * @param int $groupId
     * @param string $command نام دستور (با یا بدون /)
     * @param bool $allowed
     * @param string|null $roleLevel سطح دسترسی (admin, subadmin, all)
     * @param int|null $expiresAt زمان انقضا (timestamp)
     * @return bool
     */
    public function setPermission(
        int $groupId,
        string $command,
        bool $allowed,
        ?string $roleLevel = null,
        ?int $expiresAt = null
    ): bool {
        $cleanCommand = ltrim($command, '/');

        $data = [
            'group_id' => $groupId,
            'command' => $cleanCommand,
            'allowed' => (int)$allowed,
            'role_level' => $roleLevel ?? 'all',
            'expires_at' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $sql = "INSERT INTO {$this->table} 
                (group_id, command, allowed, role_level, expires_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                allowed = ?, role_level = ?, expires_at = ?, updated_at = ?";

        $result = $this->db->execute($sql, [
            $groupId,
            $cleanCommand,
            (int)$allowed,
            $data['role_level'],
            $data['expires_at'],
            $data['updated_at'],
            (int)$allowed,
            $data['role_level'],
            $data['expires_at'],
            $data['updated_at'],
        ]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $cleanCommand);
            $this->cache->delete($this->cachePrefix . 'list_' . $groupId);

            if ($this->logger) {
                $this->logger->info("Permission set for command $cleanCommand in group $groupId", [
                    'allowed' => $allowed,
                    'role_level' => $roleLevel,
                ]);
            }
            return true;
        }

        return false;
    }

    /**
     * دریافت مجوز یک دستور خاص
     * @return array|null ['allowed' => bool, 'role_level' => string, 'expires_at' => string|null]
     */
    public function getPermission(int $groupId, string $command): ?array
    {
        $cleanCommand = ltrim($command, '/');
        $cacheKey = $this->cachePrefix . $groupId . '_' . $cleanCommand;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT allowed, role_level, expires_at FROM {$this->table} 
                WHERE group_id = ? AND command = ?";
        $result = $this->db->queryRow($sql, [$groupId, $cleanCommand]);

        if ($result) {
            // بررسی انقضا
            if ($result['expires_at'] && strtotime($result['expires_at']) < time()) {
                $this->removePermission($groupId, $cleanCommand);
                return null;
            }

            $data = [
                'allowed' => (bool)$result['allowed'],
                'role_level' => $result['role_level'] ?? 'all',
                'expires_at' => $result['expires_at'],
            ];
            $this->cache->set($cacheKey, $data, $this->cacheTtl);
            return $data;
        }

        return null;
    }

    /**
     * بررسی اینکه یک کاربر مجاز به اجرای دستور است یا خیر
     * @param int $groupId
     * @param int $userId
     * @param string $command
     * @param string $userRole نقش کاربر (owner, admin, subadmin, user)
     * @return bool
     */
    public function isAllowed(int $groupId, int $userId, string $command, string $userRole = 'user'): bool
    {
        $permission = $this->getPermission($groupId, $command);

        // اگر مجوزی تنظیم نشده باشد، به‌طور پیش‌فرض اجازه داده می‌شود
        if (!$permission) {
            return true;
        }

        // بررسی سطح دسترسی
        $roleLevel = $permission['role_level'];
        $allowed = $permission['allowed'];

        // اگر مجوز فقط برای سطح خاصی است
        if ($roleLevel !== 'all') {
            $roleHierarchy = [
                'owner' => 0,
                'admin' => 1,
                'subadmin' => 2,
                'user' => 3,
            ];

            $userRoleLevel = $roleHierarchy[$userRole] ?? 3;
            $requiredRoleLevel = $roleHierarchy[$roleLevel] ?? 3;

            // اگر کاربر سطح پایین‌تری دارد، مجاز نیست
            if ($userRoleLevel > $requiredRoleLevel) {
                return false;
            }
        }

        return $allowed;
    }

    /**
     * حذف مجوز یک دستور
     */
    public function removePermission(int $groupId, string $command): bool
    {
        $cleanCommand = ltrim($command, '/');
        $sql = "DELETE FROM {$this->table} WHERE group_id = ? AND command = ?";
        $result = $this->db->execute($sql, [$groupId, $cleanCommand]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $cleanCommand);
            $this->cache->delete($this->cachePrefix . 'list_' . $groupId);

            if ($this->logger) {
                $this->logger->info("Permission removed for command $cleanCommand in group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * دریافت لیست تمام مجوزهای یک گروه
     * @return array ['command' => ['allowed' => bool, 'role_level' => string, 'expires_at' => string|null]]
     */
    public function getPermissions(int $groupId): array
    {
        $cacheKey = $this->cachePrefix . 'list_' . $groupId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT command, allowed, role_level, expires_at FROM {$this->table} 
                WHERE group_id = ?";
        $results = $this->db->query($sql, [$groupId]);

        $permissions = [];
        foreach ($results as $row) {
            // بررسی انقضا
            if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
                $this->removePermission($groupId, $row['command']);
                continue;
            }
            $permissions[$row['command']] = [
                'allowed' => (bool)$row['allowed'],
                'role_level' => $row['role_level'] ?? 'all',
                'expires_at' => $row['expires_at'],
            ];
        }

        $this->cache->set($cacheKey, $permissions, $this->cacheTtl);
        return $permissions;
    }

    /**
     * دریافت لیست دستورات ممنوع برای یک گروه
     * @return array
     */
    public function getDeniedCommands(int $groupId): array
    {
        $permissions = $this->getPermissions($groupId);
        $denied = [];
        foreach ($permissions as $command => $data) {
            if (!$data['allowed']) {
                $denied[] = $command;
            }
        }
        return $denied;
    }

    /**
     * دریافت لیست دستورات مجاز برای یک گروه
     * @return array
     */
    public function getAllowedCommands(int $groupId): array
    {
        $permissions = $this->getPermissions($groupId);
        $allowed = [];
        foreach ($permissions as $command => $data) {
            if ($data['allowed']) {
                $allowed[] = $command;
            }
        }
        return $allowed;
    }

    /**
     * تنظیم مجوزهای دسته‌جمعی
     * @param int $groupId
     * @param array $permissions ['command' => ['allowed' => bool, 'role_level' => string]]
     * @return bool
     */
    public function setBulkPermissions(int $groupId, array $permissions): bool
    {
        $success = true;
        foreach ($permissions as $command => $data) {
            $allowed = $data['allowed'] ?? true;
            $roleLevel = $data['role_level'] ?? 'all';
            $expiresAt = $data['expires_at'] ?? null;

            if (!$this->setPermission($groupId, $command, $allowed, $roleLevel, $expiresAt)) {
                $success = false;
            }
        }

        // پاک کردن کش لیست
        $this->cache->delete($this->cachePrefix . 'list_' . $groupId);
        return $success;
    }

    /**
     * ریست کردن تمام مجوزهای یک گروه (حذف همه)
     */
    public function resetPermissions(int $groupId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->execute($sql, [$groupId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'list_' . $groupId);
            
            if ($this->logger) {
                $this->logger->info("All permissions reset for group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * پاک کردن مجوزهای منقضی‌شده (برای اجرا توسط کرون جاب)
     * @return int تعداد مجوزهای حذف‌شده
     */
    public function cleanExpiredPermissions(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < NOW()";
        $result = $this->db->execute($sql, []);

        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result expired permissions");
        }

        return $result;
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