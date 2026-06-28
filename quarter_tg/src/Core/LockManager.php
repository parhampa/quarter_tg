<?php

namespace Core;

use Core\Database;
use Core\Cache;

class LockManager
{
    private $db;
    private $cache;

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * وضعیت هر نوع قفل را برای یک گروه برمی‌گرداند
     */
    private function isLocked($group_id, $type)
    {
        $cache_key = "lock_{$group_id}_{$type}";
        $cached = $this->cache->get($cache_key);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $stmt = $this->db->prepare("SELECT status FROM locks WHERE group_id = ? AND type = ?");
        $stmt->execute([$group_id, $type]);
        $row = $stmt->fetch();
        $status = $row ? (bool) $row['status'] : false;

        $this->cache->set($cache_key, $status);
        return $status;
    }

    /**
     * تغییر وضعیت یک قفل
     */
    private function setLock($group_id, $type, $status)
    {
        $this->db->prepare("
            INSERT INTO locks (group_id, type, status, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE status = ?, updated_at = NOW()
        ")->execute([$group_id, $type, $status, $status]);

        $this->cache->delete("lock_{$group_id}_{$type}");
        return true;
    }

    // --- متدهای عمومی برای هر نوع قفل ---

    public function isMsgLocked($group_id)     { return $this->isLocked($group_id, 'msg'); }
    public function toggleMsgLock($group_id, $status) { return $this->setLock($group_id, 'msg', $status); }

    public function isPicLocked($group_id)     { return $this->isLocked($group_id, 'pic'); }
    public function togglePicLock($group_id, $status) { return $this->setLock($group_id, 'pic', $status); }

    public function isFilmLocked($group_id)    { return $this->isLocked($group_id, 'film'); }
    public function toggleFilmLock($group_id, $status) { return $this->setLock($group_id, 'film', $status); }

    public function isGifLocked($group_id)     { return $this->isLocked($group_id, 'gif'); }
    public function toggleGifLock($group_id, $status) { return $this->setLock($group_id, 'gif', $status); }

    public function isStickerLocked($group_id) { return $this->isLocked($group_id, 'sticker'); }
    public function toggleStickerLock($group_id, $status) { return $this->setLock($group_id, 'sticker', $status); }

    public function isVoiceLocked($group_id)   { return $this->isLocked($group_id, 'voice'); }
    public function toggleVoiceLock($group_id, $status) { return $this->setLock($group_id, 'voice', $status); }

    public function isVmLocked($group_id)      { return $this->isLocked($group_id, 'vm'); }
    public function toggleVmLock($group_id, $status) { return $this->setLock($group_id, 'vm', $status); }

    public function isLinkLocked($group_id)    { return $this->isLocked($group_id, 'link'); }
    public function toggleLinkLock($group_id, $status) { return $this->setLock($group_id, 'link', $status); }

    public function isTagLocked($group_id)     { return $this->isLocked($group_id, 'tag'); }
    public function toggleTagLock($group_id, $status) { return $this->setLock($group_id, 'tag', $status); }

    // === متدهای جدید برای هشتگ ===
    public function isHashtagLocked($group_id) { return $this->isLocked($group_id, 'hashtag'); }
    public function toggleHashtagLock($group_id, $status) { return $this->setLock($group_id, 'hashtag', $status); }
}