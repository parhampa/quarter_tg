<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت قفل‌های محتوایی گروه
 * پشتیبانی از: text, photo, video, gif, sticker, voice, video_note, link, tag, hashtag
 */
class LockManager
{
    private $db;
    private $cache;
    private $table = 'bot_group_locks';
    private $lockTypes = [
        'text', 'photo', 'video', 'gif', 'sticker', 
        'voice', 'video_note', 'link', 'tag', 'hashtag'
    ];
    private $cachePrefix = 'lock_';
    private $cacheTtl = 300; // 5 minutes

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * دریافت وضعیت تمام قفل‌های یک گروه
     * @return array ['text' => 0/1, 'photo' => 0/1, ...]
     */
    public function getLocks(int $groupId): array
    {
        $cacheKey = $this->cachePrefix . $groupId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->queryRow($sql, [$groupId]);

        if (!$result) {
            // اگر رکوردی نبود، یک رکورد پیش‌فرض ایجاد می‌کنیم
            $defaults = array_fill_keys($this->lockTypes, 0);
            $defaults['group_id'] = $groupId;
            $this->db->insert($this->table, $defaults);
            $result = $this->db->queryRow($sql, [$groupId]);
        }

        // استخراج فقط فیلدهای قفل
        $locks = [];
        foreach ($this->lockTypes as $type) {
            $column = 'lock_' . $type;
            $locks[$type] = isset($result[$column]) ? (int)$result[$column] : 0;
        }

        $this->cache->set($cacheKey, $locks, $this->cacheTtl);
        return $locks;
    }

    /**
     * بررسی یک قفل خاص
     */
    public function isLocked(int $groupId, string $type): bool
    {
        if (!in_array($type, $this->lockTypes)) {
            return false;
        }

        $locks = $this->getLocks($groupId);
        return isset($locks[$type]) && $locks[$type] == 1;
    }

    /**
     * فعال/غیرفعال کردن یک قفل
     * @return bool موفقیت عملیات
     */
    public function toggleLock(int $groupId, string $type, bool $status): bool
    {
        if (!in_array($type, $this->lockTypes)) {
            return false;
        }

        $column = 'lock_' . $type;
        $sql = "UPDATE {$this->table} SET $column = ? WHERE group_id = ?";
        $result = $this->db->execute($sql, [(int)$status, $groupId]);

        // پاک کردن کش
        $cacheKey = $this->cachePrefix . $groupId;
        $this->cache->delete($cacheKey);

        return $result > 0;
    }

    /**
     * متدهای اختصاصی برای هر نوع قفل
     */
    public function isTextLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'text');
    }

    public function toggleTextLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'text', $status);
    }

    public function isPhotoLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'photo');
    }

    public function togglePhotoLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'photo', $status);
    }

    public function isVideoLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'video');
    }

    public function toggleVideoLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'video', $status);
    }

    public function isGifLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'gif');
    }

    public function toggleGifLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'gif', $status);
    }

    public function isStickerLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'sticker');
    }

    public function toggleStickerLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'sticker', $status);
    }

    public function isVoiceLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'voice');
    }

    public function toggleVoiceLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'voice', $status);
    }

    public function isVideoNoteLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'video_note');
    }

    public function toggleVideoNoteLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'video_note', $status);
    }

    public function isLinkLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'link');
    }

    public function toggleLinkLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'link', $status);
    }

    public function isTagLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'tag');
    }

    public function toggleTagLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'tag', $status);
    }

    /**
     * متدهای جدید برای قفل هشتگ
     */
    public function isHashtagLocked(int $groupId): bool
    {
        return $this->isLocked($groupId, 'hashtag');
    }

    public function toggleHashtagLock(int $groupId, bool $status): bool
    {
        return $this->toggleLock($groupId, 'hashtag', $status);
    }

    /**
     * فعال کردن همه قفل‌ها
     */
    public function lockAll(int $groupId): bool
    {
        $data = ['group_id' => $groupId];
        foreach ($this->lockTypes as $type) {
            $data['lock_' . $type] = 1;
        }
        return $this->db->update($this->table, $data, ['group_id' => $groupId]) > 0;
    }

    /**
     * غیرفعال کردن همه قفل‌ها
     */
    public function unlockAll(int $groupId): bool
    {
        $data = ['group_id' => $groupId];
        foreach ($this->lockTypes as $type) {
            $data['lock_' . $type] = 0;
        }
        return $this->db->update($this->table, $data, ['group_id' => $groupId]) > 0;
    }

    /**
     * دریافت لیست انواع قفل‌ها
     */
    public function getLockTypes(): array
    {
        return $this->lockTypes;
    }

    /**
     * تنظیم TTL کش
     */
    public function setCacheTtl(int $ttl): void
    {
        $this->cacheTtl = $ttl;
    }
}