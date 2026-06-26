<?php

namespace Core;

class MuteManager
{
    private $db;
    private $telegram;
    private $logger;

    public function __construct($db, $telegram, $logger)
    {
        $this->db = $db;
        $this->telegram = $telegram;
        $this->logger = $logger;
    }

    /**
     * ساکت کردن کاربر در گروه
     *
     * @param int $group_id
     * @param int $user_id
     * @param int $muted_by
     * @param string $reason
     * @return bool
     */
    public function muteUser($group_id, $user_id, $muted_by, $reason = '')
    {
        // بررسی وجود سکوت قبلی
        $stmt = $this->db->prepare("SELECT id FROM bot_mutes WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return false; // قبلاً ساکت است
        }

        $stmt = $this->db->prepare("INSERT INTO bot_mutes (group_id, user_id, muted_by, muted_at, reason) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iiis", $group_id, $user_id, $muted_by, $reason);
        $stmt->execute();
        $this->logger->log("User $user_id muted in group $group_id by $muted_by");
        return true;
    }

    /**
     * حذف سکوت کاربر
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool
     */
    public function unmuteUser($group_id, $user_id)
    {
        $stmt = $this->db->prepare("DELETE FROM bot_mutes WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $this->logger->log("User $user_id unmuted in group $group_id");
            return true;
        }
        return false;
    }

    /**
     * بررسی سکوت بودن کاربر
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool
     */
    public function isMuted($group_id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM bot_mutes WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * حذف پیام‌های اخیر کاربر (برای پاکسازی هنگام سکوت)
     *
     * @param int $group_id
     * @param int $user_id
     * @param int $limit
     * @return int تعداد پیام‌های حذف‌شده
     */
    public function deleteUserMessages($group_id, $user_id, $limit = 50)
    {
        $stmt = $this->db->prepare("SELECT message_id FROM bot_messages WHERE group_id = ? AND user_id = ? ORDER BY id DESC LIMIT ?");
        $stmt->bind_param("iii", $group_id, $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            // حذف پیام از تلگرام
            $this->telegram->deleteMessage($group_id, $row['message_id']);
            $count++;
        }
        return $count;
    }
}