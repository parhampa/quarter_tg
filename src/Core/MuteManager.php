<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;

/**
 * کلاس مدیریت میوت (سکوت) کاربران در گروه
 * پشتیبانی از میوت دائمی و موقت با قابلیت بررسی خودکار انقضا
 */
class MuteManager
{
    private $db;
    private $cache;
    private $telegram;
    private $logger;
    private $table = 'bot_mutes';
    private $cachePrefix = 'mute_';
    private $cacheTtl = 300; // 5 minutes

    /**
     * @param Database $db
     * @param Cache $cache
     * @param TelegramApi|null $telegram (اختیاری برای حذف پیام‌ها)
     * @param Logger|null $logger
     */
    public function __construct(Database $db, Cache $cache, $telegram = null, $logger = null)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->telegram = $telegram;
        $this->logger = $logger;
    }

    /**
     * میوت کردن یک کاربر
     * @param int $groupId آیدی گروه
     * @param int $userId آیدی کاربر
     * @param int $mutedBy آیدی ادمین میوت‌کننده
     * @param string|null $reason دلیل میوت
     * @param int|null $duration مدت زمان میوت بر حسب ثانیه (null = دائمی)
     * @param bool $deleteMessages حذف پیام‌های کاربر (تا ۵۰ پیام آخر)
     * @return bool
     */
    public function mute(
        int $groupId,
        int $userId,
        int $mutedBy,
        ?string $reason = null,
        ?int $duration = null,
        bool $deleteMessages = true
    ): bool {
        // بررسی وجود کاربر در دیتابیس
        $existing = $this->getMuteInfo($groupId, $userId);
        
        if ($existing) {
            // به‌روزرسانی میوت موجود
            return $this->updateMute($groupId, $userId, $mutedBy, $reason, $duration);
        }

        // درج میوت جدید
        $data = [
            'group_id' => $groupId,
            'user_id' => $userId,
            'muted_by' => $mutedBy,
            'reason' => $reason,
            'muted_at' => date('Y-m-d H:i:s'),
        ];

        if ($duration !== null) {
            $data['until'] = date('Y-m-d H:i:s', time() + $duration);
        }

        $result = $this->db->insert($this->table, $data);

        if ($result) {
            // پاک کردن کش
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);
            
            // حذف پیام‌های کاربر (اختیاری)
            if ($deleteMessages && $this->telegram) {
                $this->deleteUserMessages($groupId, $userId);
            }

            // لاگ
            if ($this->logger) {
                $this->logger->info("User $userId muted in group $groupId by admin $mutedBy", [
                    'duration' => $duration,
                    'reason' => $reason,
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * رفع میوت کاربر
     */
    public function unmute(int $groupId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$groupId, $userId]);

        if ($result > 0) {
            // پاک کردن کش
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);

            if ($this->logger) {
                $this->logger->info("User $userId unmuted in group $groupId");
            }

            return true;
        }

        return false;
    }

    /**
     * بررسی میوت بودن کاربر
     */
    public function isMuted(int $groupId, int $userId): bool
    {
        $muteInfo = $this->getMuteInfo($groupId, $userId);
        
        if (!$muteInfo) {
            return false;
        }

        // بررسی انقضا
        if (isset($muteInfo['until']) && $muteInfo['until'] !== null) {
            $until = strtotime($muteInfo['until']);
            if ($until < time()) {
                // میوت منقضی شده، حذف خودکار
                $this->unmute($groupId, $userId);
                return false;
            }
        }

        return true;
    }

    /**
     * دریافت اطلاعات میوت کاربر
     * @return array|null
     */
    public function getMuteInfo(int $groupId, int $userId): ?array
    {
        $cacheKey = $this->cachePrefix . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $userId]);

        if ($result) {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * دریافت لیست کاربران میوت‌شده یک گروه
     * @return array
     */
    public function getMutedUsers(int $groupId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE group_id = ? AND (until IS NULL OR until > NOW())";
        return $this->db->query($sql, [$groupId]);
    }

    /**
     * به‌روزرسانی میوت موجود
     */
    private function updateMute(int $groupId, int $userId, int $mutedBy, ?string $reason, ?int $duration): bool
    {
        $data = [
            'muted_by' => $mutedBy,
            'muted_at' => date('Y-m-d H:i:s'),
        ];

        if ($reason !== null) {
            $data['reason'] = $reason;
        }

        if ($duration !== null) {
            $data['until'] = date('Y-m-d H:i:s', time() + $duration);
        } else {
            $data['until'] = null;
        }

        $result = $this->db->update($this->table, $data, [
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);
            return true;
        }

        return false;
    }

    /**
     * حذف پیام‌های یک کاربر (حداکثر ۵۰ پیام آخر)
     */
    private function deleteUserMessages(int $groupId, int $userId): void
    {
        if (!$this->telegram) {
            return;
        }

        try {
            // دریافت ۵۰ پیام آخر کاربر از دیتابیس
            $sql = "SELECT message_id FROM bot_messages 
                    WHERE group_id = ? AND user_id = ? 
                    ORDER BY sent_at DESC LIMIT 50";
            $messages = $this->db->query($sql, [$groupId, $userId]);

            foreach ($messages as $msg) {
                $this->telegram->deleteMessage($groupId, $msg['message_id']);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->warning("Failed to delete messages for user $userId in group $groupId: " . $e->getMessage());
            }
        }
    }

    /**
     * تمدید مدت میوت
     * @return bool
     */
    public function extendMute(int $groupId, int $userId, int $extraSeconds): bool
    {
        $muteInfo = $this->getMuteInfo($groupId, $userId);
        if (!$muteInfo) {
            return false;
        }

        $currentUntil = $muteInfo['until'] ?? null;
        if ($currentUntil === null) {
            // میوت دائمی است، تغییری نمی‌کنیم
            return true;
        }

        $newUntil = strtotime($currentUntil) + $extraSeconds;
        $data = ['until' => date('Y-m-d H:i:s', $newUntil)];

        $result = $this->db->update($this->table, $data, [
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);
            return true;
        }

        return false;
    }

    /**
     * حذف میوت‌های منقضی‌شده (برای اجرا توسط کرون جاب)
     * @return int تعداد میوت‌های حذف‌شده
     */
    public function cleanExpiredMutes(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE until IS NOT NULL AND until < NOW()";
        return $this->db->execute($sql, []);
    }

    /**
     * دریافت آمار میوت‌های یک گروه
     * @return array ['total' => int, 'permanent' => int, 'temporary' => int, 'expired' => int]
     */
    public function getStats(int $groupId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN until IS NULL THEN 1 ELSE 0 END) as permanent,
                    SUM(CASE WHEN until IS NOT NULL AND until > NOW() THEN 1 ELSE 0 END) as temporary,
                    SUM(CASE WHEN until IS NOT NULL AND until < NOW() THEN 1 ELSE 0 END) as expired
                FROM {$this->table} 
                WHERE group_id = ?";
        
        $result = $this->db->queryRow($sql, [$groupId]);
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'permanent' => (int)($result['permanent'] ?? 0),
            'temporary' => (int)($result['temporary'] ?? 0),
            'expired' => (int)($result['expired'] ?? 0),
        ];
    }

    /**
     * تنظیم Telegram API برای حذف پیام‌ها
     */
    public function setTelegram($telegram): void
    {
        $this->telegram = $telegram;
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

    /**
     * دریافت Telegram API
     */
    public function getTelegram()
    {
        return $this->telegram;
    }

    /**
     * دریافت Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }
}