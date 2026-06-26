<?php

namespace Core;

class WarningManager
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
     * افزودن اخطار به کاربر
     *
     * @param int $group_id
     * @param int $user_id
     * @param int $warned_by
     * @param string $reason
     * @return array ['status' => 'warned'|'banned', 'count' => int]
     */
    public function addWarning($group_id, $user_id, $warned_by, $reason = '')
    {
        // ثبت اخطار جدید
        $stmt = $this->db->prepare("INSERT INTO bot_warnings (group_id, user_id, warned_by, warned_at, reason) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iiis", $group_id, $user_id, $warned_by, $reason);
        $stmt->execute();

        // شمارش تعداد اخطارهای این کاربر در این گروه
        $count = $this->getWarningCount($group_id, $user_id);

        if ($count >= 3) {
            // بن خودکار
            $this->autoBan($group_id, $user_id);
            return ['status' => 'banned', 'count' => $count];
        }

        return ['status' => 'warned', 'count' => $count];
    }

    /**
     * حذف تمام اخطارهای کاربر در گروه
     *
     * @param int $group_id
     * @param int $user_id
     * @return bool
     */
    public function removeWarnings($group_id, $user_id)
    {
        $stmt = $this->db->prepare("DELETE FROM bot_warnings WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /**
     * دریافت تعداد اخطارهای کاربر در گروه
     *
     * @param int $group_id
     * @param int $user_id
     * @return int
     */
    public function getWarningCount($group_id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM bot_warnings WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }

    /**
     * بن خودکار کاربر در صورت رسیدن به ۳ اخطار
     *
     * @param int $group_id
     * @param int $user_id
     */
    private function autoBan($group_id, $user_id)
    {
        // بررسی اینکه کاربر قبلاً بن نشده باشد
        $stmt = $this->db->prepare("SELECT id FROM bot_bans WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return; // قبلاً بن شده
        }

        // افزودن به جدول بن‌ها
        $stmt = $this->db->prepare("INSERT INTO bot_bans (group_id, user_id, banned_by, banned_at, reason) VALUES (?, ?, ?, NOW(), ?)");
        $banned_by = 0; // سیستمی
        $reason = 'رسیدن به ۳ اخطار';
        $stmt->bind_param("iiis", $group_id, $user_id, $banned_by, $reason);
        $stmt->execute();

        $this->logger->log("User $user_id auto-banned in group $group_id due to 3 warnings");
    }
}