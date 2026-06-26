<?php
namespace Core;

class AdminManager
{
    private $db;
    private $cache;

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function canManageAdmins(int $userId): bool
    {
        $sql = "SELECT 1 FROM bot_admins WHERE user_id = {$userId} AND role = 'owner'";
        $result = $this->db->fetchOne($sql);
        if ($result) {
            return true;
        }

        $sql = "SELECT 1 FROM bot_sub_admins WHERE user_id = {$userId}";
        $result = $this->db->fetchOne($sql);
        return $result !== null;
    }

    public function addAdmin(int $userId, int $groupId, string $role = 'admin'): bool
    {
        $sql = "SELECT 1 FROM bot_admins WHERE user_id = {$userId} AND group_id = {$groupId}";
        $exist = $this->db->fetchOne($sql);
        if ($exist) {
            return false;
        }

        $sql = "INSERT INTO bot_admins (user_id, group_id, role) VALUES ({$userId}, {$groupId}, '{$role}')";
        $this->db->execute($sql);
        $this->cache->delete('admins_cache');
        return true;
    }

    public function removeAdmin(int $userId, int $groupId): bool
    {
        $sql = "DELETE FROM bot_admins WHERE user_id = {$userId} AND group_id = {$groupId}";
        $this->db->execute($sql);
        $this->cache->delete('admins_cache');
        return true;
    }

    public function getAdminsByGroup(int $groupId): array
    {
        $sql = "SELECT user_id FROM bot_admins WHERE group_id = {$groupId} OR group_id IS NULL";
        $rows = $this->db->fetchAll($sql);
        return array_column($rows, 'user_id');
    }

    public function isAdminOfGroup(int $userId, int $groupId): bool
    {
        $sql = "SELECT 1 FROM bot_admins WHERE user_id = {$userId} AND (group_id = {$groupId} OR group_id IS NULL)";
        $result = $this->db->fetchOne($sql);
        return $result !== null;
    }
}