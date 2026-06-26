<?php
namespace Core;

class PermissionManager
{
    private $db;
    private $cache;
    private $permissionsCache = null;

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    private function loadPermissionsFromDb(): array
    {
        if ($this->permissionsCache !== null) {
            return $this->permissionsCache;
        }

        $sql = "SELECT user_id, commands FROM bot_permissions";
        $rows = $this->db->fetchAll($sql);
        $permissions = [];
        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];
            $commands = array_map('trim', explode(',', $row['commands']));
            $permissions[$userId] = $commands;
        }
        $this->permissionsCache = $permissions;
        return $permissions;
    }

    public function hasPermission(int $userId, string $command): ?bool
    {
        $permissions = $this->loadPermissionsFromDb();
        if (!isset($permissions[$userId])) {
            return null;
        }
        return in_array($command, $permissions[$userId]);
    }
}