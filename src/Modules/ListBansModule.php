<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول نمایش لیست کاربران بن‌شده گروه
 * فقط ادمین‌ها می‌توانند لیست را مشاهده کنند
 */
class ListBansModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $banTable = 'bot_bans';

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
    }

    /**
     * اجرای ماژول
     */
    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // فقط ادمین‌ها می‌توانند لیست را ببینند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه دسترسی به لیست بن‌ها را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // دریافت لیست بن‌ها
        $bans = $this->getBans($chatId);

        if (empty($bans)) {
            $this->telegram->sendMessage(
                $chatId,
                "📋 لیست کاربران بن‌شده خالی است.",
                $message['message_id'] ?? null
            );
            return;
        }

        // ساخت متن لیست
        $listText = $this->formatBanList($bans, $chatId);

        // اگر لیست طولانی است، به چند بخش تقسیم می‌کنیم
        if (strlen($listText) > 4000) {
            $parts = $this->splitLongMessage($listText);
            foreach ($parts as $index => $part) {
                $this->telegram->sendMessage(
                    $chatId,
                    $part,
                    $index === 0 ? ($message['message_id'] ?? null) : null,
                    'HTML'
                );
            }
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $listText,
                $message['message_id'] ?? null,
                'HTML'
            );
        }

        // لاگ
        $this->logger->info("Ban list shown to user $userId in group $chatId");
    }

    /**
     * دریافت لیست بن‌ها از دیتابیس
     * @return array
     */
    private function getBans(int $groupId): array
    {
        $sql = "SELECT * FROM {$this->banTable} WHERE group_id = ? ORDER BY banned_at DESC";
        return $this->db->query($sql, [$groupId]);
    }

    /**
     * فرمت کردن لیست بن‌ها
     */
    private function formatBanList(array $bans, int $groupId): string
    {
        $text = "📋 <b>لیست کاربران بن‌شده</b>\n";
        $text .= str_repeat('━', 30) . "\n\n";

        $count = 0;
        foreach ($bans as $ban) {
            $count++;
            $userId = $ban['user_id'];
            $username = $ban['username'] ?? '';
            $firstName = $ban['first_name'] ?? '';
            $lastName = $ban['last_name'] ?? '';
            $bannedBy = $ban['banned_by'] ?? 0;
            $reason = $ban['reason'] ?? '';
            $bannedAt = $ban['banned_at'] ?? '';

            // ساخت نام نمایشی
            $displayName = $firstName;
            if (!empty($lastName)) {
                $displayName .= ' ' . $lastName;
            }
            if (empty($displayName)) {
                $displayName = $username ? '@' . $username : "کاربر ناشناس";
            }

            // ساخت لینک به کاربر
            if (!empty($username)) {
                $userLink = '@' . $username;
            } else {
                $userLink = "<a href=\"tg://user?id={$userId}\">{$displayName}</a>";
            }

            $text .= "<b>{$count}.</b> {$userLink}";
            $text .= " <i>(ID: {$userId})</i>\n";

            // اطلاعات بن
            if ($bannedAt) {
                $date = date('Y/m/d H:i', strtotime($bannedAt));
                $text .= "    📅 تاریخ بن: {$date}\n";
            }

            if ($bannedBy) {
                // تلاش برای دریافت اطلاعات ادمین بن‌کننده
                $adminName = $this->getAdminName($groupId, $bannedBy);
                $text .= "    👤 بن‌کننده: {$adminName}\n";
            }

            if (!empty($reason)) {
                $text .= "    📝 دلیل: {$reason}\n";
            }

            $text .= "\n";
        }

        $text .= str_repeat('━', 30) . "\n";
        $text .= "📊 مجموع: <b>{$count}</b> نفر";

        return $text;
    }

    /**
     * دریافت نام ادمین بن‌کننده
     */
    private function getAdminName(int $groupId, int $adminId): string
    {
        // بررسی در جدول ادمین‌ها
        $sql = "SELECT username, first_name, last_name FROM bot_admins WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $adminId]);
        
        if ($result) {
            $name = $result['first_name'] ?? '';
            if (!empty($result['last_name'])) {
                $name .= ' ' . $result['last_name'];
            }
            if (empty($name)) {
                $name = $result['username'] ? '@' . $result['username'] : "ادمین ناشناس";
            }
            return $name . " (ID: {$adminId})";
        }

        // بررسی در جدول ساب‌ادمین‌ها
        $sql = "SELECT username, first_name, last_name FROM bot_sub_admins WHERE group_id = ? AND user_id = ?";
        $result = $this->db->queryRow($sql, [$groupId, $adminId]);
        
        if ($result) {
            $name = $result['first_name'] ?? '';
            if (!empty($result['last_name'])) {
                $name .= ' ' . $result['last_name'];
            }
            if (empty($name)) {
                $name = $result['username'] ? '@' . $result['username'] : "ساب‌ادمین ناشناس";
            }
            return $name . " (ID: {$adminId})";
        }

        // اگر در هیچ جدولی پیدا نشد، از Telegram API سعی می‌کنیم
        try {
            $chatMember = $this->telegram->getChatMember($groupId, $adminId);
            if ($chatMember && isset($chatMember['user'])) {
                $user = $chatMember['user'];
                $name = $user['first_name'] ?? '';
                if (!empty($user['last_name'])) {
                    $name .= ' ' . $user['last_name'];
                }
                if (empty($name)) {
                    $name = $user['username'] ? '@' . $user['username'] : "کاربر ناشناس";
                }
                return $name . " (ID: {$adminId})";
            }
        } catch (\Exception $e) {
            // نادیده گرفته می‌شود
        }

        return "کاربر با آیدی {$adminId}";
    }

    /**
     * تقسیم پیام طولانی به چند بخش
     */
    private function splitLongMessage(string $text): array
    {
        $parts = [];
        $lines = explode("\n", $text);
        $currentPart = '';
        $maxLength = 4000;

        foreach ($lines as $line) {
            if (strlen($currentPart) + strlen($line) + 1 > $maxLength) {
                if (!empty($currentPart)) {
                    $parts[] = $currentPart;
                    $currentPart = '';
                }
                // اگر یک خط به تنهایی طولانی است، آن را به چند بخش تقسیم می‌کنیم
                if (strlen($line) > $maxLength) {
                    $chunks = str_split($line, $maxLength);
                    foreach ($chunks as $chunk) {
                        $parts[] = $chunk;
                    }
                    continue;
                }
            }
            $currentPart .= $line . "\n";
        }

        if (!empty($currentPart)) {
            $parts[] = $currentPart;
        }

        return $parts;
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "نمایش لیست کاربران بن‌شده / Show banned users list";
    }
}