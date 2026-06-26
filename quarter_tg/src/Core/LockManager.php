<?php
namespace Core;

class LockManager
{
    private $db;
    private $cache;

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    private function getLocks(int $groupId): array
    {
        $cacheKey = "locks_{$groupId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM bot_group_locks WHERE group_id = {$groupId}";
        $row = $this->db->fetchOne($sql);
        if (!$row) {
            $default = [
                'group_id' => $groupId,
                'lock_messages' => 0,
                'lock_stickers' => 0,
                'lock_photos' => 0,
                'lock_videos' => 0,
                'lock_gifs' => 0,
                'lock_voice' => 0,          // NEW
                'lock_video_notes' => 0,    // NEW
            ];
            $this->cache->set($cacheKey, $default);
            return $default;
        }
        $this->cache->set($cacheKey, $row);
        return $row;
    }

    public function isLocked(int $groupId, string $type): bool
    {
        $locks = $this->getLocks($groupId);
        $field = 'lock_' . $type;
        return isset($locks[$field]) && (int)$locks[$field] === 1;
    }

    public function setLock(int $groupId, string $type, bool $enabled): void
    {
        $field = 'lock_' . $type;
        $value = $enabled ? 1 : 0;
        $sql = "INSERT INTO bot_group_locks (group_id, {$field}) VALUES ({$groupId}, {$value})
                ON DUPLICATE KEY UPDATE {$field} = {$value}, updated_at = CURRENT_TIMESTAMP";
        $this->db->execute($sql);
        $this->cache->delete("locks_{$groupId}");
    }
}