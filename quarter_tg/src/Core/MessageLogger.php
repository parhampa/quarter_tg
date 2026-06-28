<?php

namespace QuarterTg\Core;

/**
 * کلاس ثبت لاگ پیام‌های دریافتی در دیتابیس
 * برای ذخیره‌سازی و تحلیل بعدی پیام‌ها و همچنین امکان حذف پیام‌های کاربر
 */
class MessageLogger
{
    private $db;
    private $logger;
    private $table = 'bot_messages';
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
     * ثبت یک پیام در دیتابیس
     * @param array $message پیام دریافتی از تلگرام
     * @return bool
     */
    public function log(array $message): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // استخراج اطلاعات ضروری
        $chatId = $message['chat']['id'] ?? 0;
        $userId = $message['from']['id'] ?? 0;
        $messageId = $message['message_id'] ?? 0;
        $text = $message['text'] ?? null;
        $messageType = $this->detectMessageType($message);

        // اگر آیدی پیام یا گروه نامعتبر باشد، ثبت نمی‌کنیم
        if ($chatId <= 0 || $messageId <= 0) {
            return false;
        }

        // بررسی وجود پیام در دیتابیس (برای جلوگیری از تکراری‌سازی)
        if ($this->isMessageLogged($chatId, $messageId)) {
            return true;
        }

        $data = [
            'group_id' => $chatId,
            'user_id' => $userId,
            'message_id' => $messageId,
            'message_text' => $text,
            'message_type' => $messageType,
            'sent_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $result = $this->db->insert($this->table, $data);
            
            if ($result && $this->logger) {
                $this->logger->debug("Message logged: chat $chatId, user $userId, type $messageType");
            }
            
            return (bool)$result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to log message: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * تشخیص نوع پیام
     */
    private function detectMessageType(array $message): string
    {
        if (isset($message['text'])) {
            return 'text';
        }
        if (isset($message['photo'])) {
            return 'photo';
        }
        if (isset($message['video'])) {
            return 'video';
        }
        if (isset($message['animation'])) {
            return 'gif';
        }
        if (isset($message['sticker'])) {
            return 'sticker';
        }
        if (isset($message['voice'])) {
            return 'voice';
        }
        if (isset($message['video_note'])) {
            return 'video_note';
        }
        if (isset($message['audio'])) {
            return 'audio';
        }
        if (isset($message['document'])) {
            return 'document';
        }
        if (isset($message['contact'])) {
            return 'contact';
        }
        if (isset($message['location'])) {
            return 'location';
        }
        if (isset($message['poll'])) {
            return 'poll';
        }
        if (isset($message['new_chat_members'])) {
            return 'new_member';
        }
        if (isset($message['left_chat_member'])) {
            return 'left_member';
        }
        if (isset($message['pinned_message'])) {
            return 'pinned';
        }
        return 'unknown';
    }

    /**
     * بررسی اینکه پیام قبلاً ثبت شده است یا خیر
     */
    private function isMessageLogged(int $chatId, int $messageId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE group_id = ? AND message_id = ?";
        $result = $this->db->queryColumn($sql, [$chatId, $messageId]);
        return $result !== false;
    }

    /**
     * دریافت آخرین پیام‌های یک گروه
     * @param int $groupId
     * @param int $limit تعداد پیام‌ها (پیش‌فرض ۱۰۰)
     * @return array
     */
    public function getRecentMessages(int $groupId, int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE group_id = ? 
                ORDER BY sent_at DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $limit]);
    }

    /**
     * دریافت آخرین پیام‌های یک کاربر در یک گروه
     * @return array
     */
    public function getUserMessages(int $groupId, int $userId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE group_id = ? AND user_id = ? 
                ORDER BY sent_at DESC 
                LIMIT ?";
        return $this->db->query($sql, [$groupId, $userId, $limit]);
    }

    /**
     * دریافت پیام با آیدی مشخص
     * @return array|null
     */
    public function getMessage(int $groupId, int $messageId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE group_id = ? AND message_id = ?";
        return $this->db->queryRow($sql, [$groupId, $messageId]);
    }

    /**
     * دریافت آمار پیام‌های یک گروه
     * @return array ['total' => int, 'text' => int, 'photo' => int, ...]
     */
    public function getStats(int $groupId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN message_type = 'text' THEN 1 ELSE 0 END) as text,
                    SUM(CASE WHEN message_type = 'photo' THEN 1 ELSE 0 END) as photo,
                    SUM(CASE WHEN message_type = 'video' THEN 1 ELSE 0 END) as video,
                    SUM(CASE WHEN message_type = 'gif' THEN 1 ELSE 0 END) as gif,
                    SUM(CASE WHEN message_type = 'sticker' THEN 1 ELSE 0 END) as sticker,
                    SUM(CASE WHEN message_type = 'voice' THEN 1 ELSE 0 END) as voice,
                    SUM(CASE WHEN message_type = 'video_note' THEN 1 ELSE 0 END) as video_note,
                    SUM(CASE WHEN message_type = 'audio' THEN 1 ELSE 0 END) as audio,
                    SUM(CASE WHEN message_type = 'document' THEN 1 ELSE 0 END) as document,
                    SUM(CASE WHEN message_type = 'new_member' THEN 1 ELSE 0 END) as new_member,
                    SUM(CASE WHEN message_type = 'left_member' THEN 1 ELSE 0 END) as left_member
                FROM {$this->table} 
                WHERE group_id = ?";
        
        $result = $this->db->queryRow($sql, [$groupId]);
        
        return [
            'total' => (int)($result['total'] ?? 0),
            'text' => (int)($result['text'] ?? 0),
            'photo' => (int)($result['photo'] ?? 0),
            'video' => (int)($result['video'] ?? 0),
            'gif' => (int)($result['gif'] ?? 0),
            'sticker' => (int)($result['sticker'] ?? 0),
            'voice' => (int)($result['voice'] ?? 0),
            'video_note' => (int)($result['video_note'] ?? 0),
            'audio' => (int)($result['audio'] ?? 0),
            'document' => (int)($result['document'] ?? 0),
            'new_member' => (int)($result['new_member'] ?? 0),
            'left_member' => (int)($result['left_member'] ?? 0),
        ];
    }

    /**
     * دریافت تعداد پیام‌های یک کاربر در یک گروه
     */
    public function getUserMessageCount(int $groupId, int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE group_id = ? AND user_id = ?";
        return (int)$this->db->queryColumn($sql, [$groupId, $userId]);
    }

    /**
     * حذف پیام‌های قدیمی (بیش از X روز)
     * @return int تعداد پیام‌های حذف‌شده
     */
    public function cleanupOldMessages(int $days = 30): int
    {
        $sql = "DELETE FROM {$this->table} WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $result = $this->db->execute($sql, [$days]);
        
        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result old messages (older than $days days)");
        }
        
        return $result;
    }

    /**
     * حذف پیام‌های یک گروه خاص
     */
    public function clearGroupMessages(int $groupId): int
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->execute($sql, [$groupId]);
        
        if ($this->logger && $result > 0) {
            $this->logger->info("Deleted $result messages from group $groupId");
        }
        
        return $result;
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