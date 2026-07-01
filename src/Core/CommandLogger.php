<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;

/**
 * کلاس ثبت لاگ دستورات اجرا شده توسط ادمین‌ها
 * برای ممیزی، تحلیل و پیگیری اقدامات مدیران
 */
class CommandLogger
{
    private $db;
    private $logger;
    private $table = 'bot_command_logs';
    private $enabled = true;

    /**
     * @param Database $db
     * @param Logger|null $logger
     */
    public function __construct(Database $db, $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * ثبت یک دستور اجرا شده
     * @param int $groupId آیدی گروه
     * @param int $adminId آیدی ادمین اجراکننده
     * @param string $command نام دستور
     * @param string|null $params پارامترهای دستور
     * @param int|null $targetUserId آیدی کاربر هدف (در صورت وجود)
     * @return bool
     */
    public function log(
        int $groupId,
        int $adminId,
        string $command,
        ?string $params = null,
        ?int $targetUserId = null
    ): bool {
        if (!$this->enabled) {
            return false;
        }

        // اگر گروه نامعتبر باشد، ثبت نمی‌کنیم
        if ($groupId <= 0 || $adminId <= 0) {
            return false;
        }

        // محدود کردن طول پارامترها
        if ($params && strlen($params) > 500) {
            $params = substr($params, 0, 500) . '...';
        }

        // پاک کردن / از ابتدای دستور
        $cleanCommand = ltrim($command, '/');

        $data = [
            'group_id' => $groupId,
            'admin_id' => $adminId,
            'command' => $cleanCommand,
            'params' => $params,
            'target_user' => $targetUserId,
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $result = $this->db->insert($this->table, $data);
            
            if ($this->logger) {
                $this->logger->debug("Command logged: $cleanCommand by admin $adminId in group $groupId");
            }
            
            return (bool)$result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to log command: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * ثبت دستور با استفاده از پیام دریافتی (ساده‌تر)
     * @param array $message پیام دریافتی از تلگرام
     * @param string $command نام دستور
     * @param string|null $params پارامترها
     * @param int|null $targetUserId کاربر هدف
     * @return bool
     */
    public function logFromMessage(
        array $message,
        string $command,
        ?string $params = null,
        ?int $targetUserId = null
    ): bool {
        $chatId = $message['chat']['id'] ?? 0;
        $userId = $message['from']['id'] ?? 0;

        return $this->log($chatId, $userId, $command, $params, $targetUserId);
    }

    /**
     * دریافت آخرین دستورات یک گروه
     * @param int $groupId
     * @param int $limit تعداد (پیش‌فرض ۵۰)
     * @return array
     */
    public function getRecentCommands(int $groupId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE group_id = ? 
                ORDER BY executed_at DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $limit]);
    }

    /**
     * دریافت آخرین دستورات یک ادمین خاص
     * @return array
     */
    public function getAdminCommands(int $groupId, int $adminId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE group_id = ? AND admin_id = ? 
                ORDER BY executed_at DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $adminId, $limit]);
    }

    /**
     * دریافت آمار دستورات یک گروه
     * @return array ['total' => int, 'commands' => ['command_name' => count, ...]]
     */
    public function getStats(int $groupId): array
    {
        // تعداد کل
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE group_id = ?";
        $total = (int)$this->db->queryColumn($sql, [$groupId]);

        // تعداد هر دستور
        $sql = "SELECT command, COUNT(*) as count 
                FROM {$this->table} 
                WHERE group_id = ? 
                GROUP BY command 
                ORDER BY count DESC";
        $results = $this->db->query($sql, [$groupId]);

        $commands = [];
        foreach ($results as $row) {
            $commands[$row['command']] = (int)$row['count'];
        }

        return [
            'total' => $total,
            'commands' => $commands,
        ];
    }

    /**
     * دریافت آمار دستورات یک ادمین خاص
     * @return array ['total' => int, 'commands' => ['command_name' => count, ...]]
     */
    public function getAdminStats(int $groupId, int $adminId): array
    {
        // تعداد کل
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE group_id = ? AND admin_id = ?";
        $total = (int)$this->db->queryColumn($sql, [$groupId, $adminId]);

        // تعداد هر دستور
        $sql = "SELECT command, COUNT(*) as count 
                FROM {$this->table} 
                WHERE group_id = ? AND admin_id = ? 
                GROUP BY command 
                ORDER BY count DESC";
        $results = $this->db->query($sql, [$groupId, $adminId]);

        $commands = [];
        foreach ($results as $row) {
            $commands[$row['command']] = (int)$row['count'];
        }

        return [
            'total' => $total,
            'commands' => $commands,
        ];
    }

    /**
     * دریافت پرتکرارترین دستورات یک گروه
     * @return array
     */
    public function getTopCommands(int $groupId, int $limit = 10): array
    {
        $sql = "SELECT command, COUNT(*) as count 
                FROM {$this->table} 
                WHERE group_id = ? 
                GROUP BY command 
                ORDER BY count DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $limit]);
    }

    /**
     * دریافت فعالیت‌ترین ادمین‌های یک گروه
     * @return array
     */
    public function getTopAdmins(int $groupId, int $limit = 10): array
    {
        $sql = "SELECT admin_id, COUNT(*) as count 
                FROM {$this->table} 
                WHERE group_id = ? 
                GROUP BY admin_id 
                ORDER BY count DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $limit]);
    }

    /**
     * جستجو در لاگ دستورات
     * @param int $groupId
     * @param string $searchTerm عبارت جستجو (در command و params)
     * @param int $limit
     * @return array
     */
    public function searchCommands(int $groupId, string $searchTerm, int $limit = 50): array
    {
        $searchTerm = '%' . $searchTerm . '%';
        $sql = "SELECT * FROM {$this->table} 
                WHERE group_id = ? 
                AND (command LIKE ? OR params LIKE ?) 
                ORDER BY executed_at DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $searchTerm, $searchTerm, $limit]);
    }

    /**
     * حذف دستورات قدیمی (بیش از X روز)
     * @return int تعداد دستورات حذف‌شده
     */
    public function cleanupOldCommands(int $days = 30): int
    {
        $sql = "DELETE FROM {$this->table} WHERE executed_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $result = $this->db->execute($sql, [$days]);
        
        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result old command logs (older than $days days)");
        }
        
        return $result;
    }

    /**
     * حذف تمام دستورات یک گروه
     */
    public function clearGroupCommands(int $groupId): int
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->execute($sql, [$groupId]);
        
        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result command logs from group $groupId");
        }
        
        return $result;
    }

    /**
     * حذف تمام دستورات یک ادمین خاص
     */
    public function clearAdminCommands(int $groupId, int $adminId): int
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ? AND admin_id = ?";
        $result = $this->db->execute($sql, [$groupId, $adminId]);
        
        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result command logs for admin $adminId in group $groupId");
        }
        
        return $result;
    }

    /**
     * دریافت تعداد کل دستورات ثبت‌شده
     */
    public function getTotalCount(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        return (int)$this->db->queryColumn($sql, []);
    }

    /**
     * فعال/غیرفعال کردن لاگ
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * تنظیم Logger
     */
    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }

    /**
     * بررسی فعال بودن لاگ
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}