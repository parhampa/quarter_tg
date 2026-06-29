<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;

/**
 * کلاس مدیریت اخطارها (Warnings) با قابلیت بن خودکار پس از ۳ اخطار
 * هر کاربر در هر گروه به‌طور جداگانه اخطار دریافت می‌کند
 */
class WarningManager
{
    private $db;
    private $cache;
    private $telegram;
    private $logger;
    private $table = 'bot_warnings';
    private $cachePrefix = 'warning_';
    private $cacheTtl = 300; // 5 minutes
    private $maxWarnings = 3; // حداکثر اخطار قبل از بن خودکار
    private $banManager = null;

    /**
     * @param Database $db
     * @param Cache $cache
     * @param TelegramApi|null $telegram (اختیاری برای بن کردن)
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
     * تنظیم BanManager برای بن خودکار
     */
    public function setBanManager($banManager): void
    {
        $this->banManager = $banManager;
    }

    /**
     * تنظیم حداکثر تعداد اخطار قبل از بن
     */
    public function setMaxWarnings(int $max): void
    {
        $this->maxWarnings = $max;
    }

    /**
     * افزودن اخطار به کاربر
     * @return array ['success' => bool, 'warning_count' => int, 'is_banned' => bool, 'message' => string]
     */
    public function addWarning(int $groupId, int $userId, int $warnedBy, ?string $reason = null): array
    {
        // بررسی وجود کاربر در جدول اخطارها
        $currentCount = $this->getWarningCount($groupId, $userId);
        
        if ($currentCount === null) {
            // کاربر اخطار ندارد، ایجاد رکورد جدید
            $data = [
                'group_id' => $groupId,
                'user_id' => $userId,
                'warned_by' => $warnedBy,
                'reason' => $reason,
                'warned_at' => date('Y-m-d H:i:s'),
                'count' => 1,
            ];
            $this->db->insert($this->table, $data);
            $newCount = 1;
        } else {
            // افزایش تعداد اخطار
            $newCount = $currentCount + 1;
            $data = [
                'count' => $newCount,
                'warned_by' => $warnedBy,
                'reason' => $reason,
                'warned_at' => date('Y-m-d H:i:s'),
            ];
            $this->db->update($this->table, $data, [
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);
        }

        // پاک کردن کش
        $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);

        // لاگ
        if ($this->logger) {
            $this->logger->info("User $userId received warning $newCount/$this->maxWarnings in group $groupId", [
                'reason' => $reason,
                'warned_by' => $warnedBy,
            ]);
        }

        // بررسی بن خودکار
        $isBanned = false;
        if ($newCount >= $this->maxWarnings) {
            $isBanned = $this->autoBan($groupId, $userId, $warnedBy);
        }

        return [
            'success' => true,
            'warning_count' => $newCount,
            'is_banned' => $isBanned,
            'message' => $isBanned 
                ? "کاربر پس از $newCount اخطار به‌طور خودکار بن شد." 
                : "اخطار $newCount از $this->maxWarnings ثبت شد.",
        ];
    }

    /**
     * حذف تمام اخطارهای یک کاربر
     */
    public function clearWarnings(int $groupId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$groupId, $userId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);
            
            if ($this->logger) {
                $this->logger->info("Warnings cleared for user $userId in group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * حذف یک اخطار خاص (کاهش تعداد اخطارها)
     */
    public function removeOneWarning(int $groupId, int $userId): bool
    {
        $currentCount = $this->getWarningCount($groupId, $userId);
        
        if ($currentCount === null || $currentCount <= 0) {
            return false;
        }

        $newCount = $currentCount - 1;
        
        if ($newCount <= 0) {
            // اگر به صفر رسید، رکورد را حذف می‌کنیم
            return $this->clearWarnings($groupId, $userId);
        }

        // کاهش تعداد
        $sql = "UPDATE {$this->table} SET count = ? WHERE group_id = ? AND user_id = ?";
        $result = $this->db->execute($sql, [$newCount, $groupId, $userId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . $groupId . '_' . $userId);
            
            if ($this->logger) {
                $this->logger->info("One warning removed for user $userId in group $groupId, now $newCount warnings");
            }
            return true;
        }

        return false;
    }

    /**
     * دریافت تعداد اخطارهای کاربر
     * @return int|null (null اگر اخطاری نداشته باشد)
     */
    public function getWarningCount(int $groupId, int $userId): ?int
    {
        $cacheKey = $this->cachePrefix . $groupId . '_' . $userId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT count FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryColumn($sql, [$groupId, $userId]);

        if ($result !== false) {
            $count = (int)$result;
            $this->cache->set($cacheKey, $count, $this->cacheTtl);
            return $count;
        }

        return null;
    }

    /**
     * دریافت لیست کامل اخطارهای یک کاربر
     * @return array|null
     */
    public function getWarnings(int $groupId, int $userId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        return $this->db->queryRow($sql, [$groupId, $userId]);
    }

    /**
     * دریافت لیست کاربران با بیشترین اخطار در یک گروه
     * @return array
     */
    public function getTopWarnedUsers(int $groupId, int $limit = 10): array
    {
        $sql = "SELECT user_id, count, warned_at 
                FROM {$this->table} 
                WHERE group_id = ? AND count > 0
                ORDER BY count DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $limit]);
    }

    /**
     * دریافت آمار اخطارهای یک گروه
     * @return array ['total_users' => int, 'total_warnings' => int, 'max_warnings' => int, 'avg_warnings' => float]
     */
    public function getStats(int $groupId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_users,
                    SUM(count) as total_warnings,
                    MAX(count) as max_warnings,
                    AVG(count) as avg_warnings
                FROM {$this->table} 
                WHERE group_id = ? AND count > 0";
        
        $result = $this->db->queryRow($sql, [$groupId]);
        
        return [
            'total_users' => (int)($result['total_users'] ?? 0),
            'total_warnings' => (int)($result['total_warnings'] ?? 0),
            'max_warnings' => (int)($result['max_warnings'] ?? 0),
            'avg_warnings' => (float)($result['avg_warnings'] ?? 0),
        ];
    }

    /**
     * بن خودکار کاربر پس از رسیدن به حداکثر اخطار
     */
    private function autoBan(int $groupId, int $userId, int $warnedBy): bool
    {
        // اگر BanManager تنظیم شده باشد، از آن استفاده می‌کنیم
        if ($this->banManager !== null && method_exists($this->banManager, 'ban')) {
            $reason = "بن خودکار پس از {$this->maxWarnings} اخطار";
            return $this->banManager->ban($groupId, $userId, $warnedBy, $reason);
        }

        // در غیر این صورت، اگر Telegram API موجود باشد، مستقیم بن می‌کنیم
        if ($this->telegram !== null) {
            try {
                $this->telegram->banChatMember($groupId, $userId);
                
                // پاک کردن اخطارها پس از بن
                $this->clearWarnings($groupId, $userId);
                
                if ($this->logger) {
                    $this->logger->info("User $userId auto-banned in group $groupId after $this->maxWarnings warnings");
                }
                return true;
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Auto-ban failed for user $userId in group $groupId: " . $e->getMessage());
                }
                return false;
            }
        }

        return false;
    }

    /**
     * بررسی اینکه آیا کاربر به حداکثر اخطار رسیده است یا خیر
     */
    public function hasReachedMaxWarnings(int $groupId, int $userId): bool
    {
        $count = $this->getWarningCount($groupId, $userId);
        return $count !== null && $count >= $this->maxWarnings;
    }

    /**
     * بررسی اینکه آیا کاربر اخطار دارد یا خیر
     */
    public function hasWarnings(int $groupId, int $userId): bool
    {
        $count = $this->getWarningCount($groupId, $userId);
        return $count !== null && $count > 0;
    }

    /**
     * دریافت تعداد اخطارهای باقی‌مانده تا بن
     */
    public function getRemainingWarnings(int $groupId, int $userId): int
    {
        $count = $this->getWarningCount($groupId, $userId);
        if ($count === null) {
            return $this->maxWarnings;
        }
        return max(0, $this->maxWarnings - $count);
    }

    /**
     * تنظیم Logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    /**
     * تنظیم Telegram API
     */
    public function setTelegram($telegram): void
    {
        $this->telegram = $telegram;
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

    /**
     * دریافت حداکثر تعداد اخطارها
     */
    public function getMaxWarnings(): int
    {
        return $this->maxWarnings;
    }
}