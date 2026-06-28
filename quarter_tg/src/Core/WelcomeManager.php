<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت پیام خوش‌آمدگویی برای اعضای جدید
 * پشتیبانی از فعال/غیرفعال کردن، تنظیم پیام، و ارسال خودکار
 */
class WelcomeManager
{
    private $db;
    private $cache;
    private $telegram;
    private $logger;
    private $table = 'bot_welcome_settings';
    private $cachePrefix = 'welcome_';
    private $cacheTtl = 300; // 5 minutes

    /**
     * @param Database $db
     * @param Cache $cache
     * @param TelegramApi|null $telegram
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
     * دریافت تنظیمات خوش‌آمدگویی یک گروه
     * @return array ['enabled' => 0/1, 'message' => string|null]
     */
    public function getSettings(int $groupId): array
    {
        $cacheKey = $this->cachePrefix . 'settings_' . $groupId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $sql = "SELECT * FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->queryRow($sql, [$groupId]);

        if (!$result) {
            // اگر رکوردی نبود، یک رکورد پیش‌فرض ایجاد می‌کنیم
            $defaults = [
                'group_id' => $groupId,
                'enabled' => 0,
                'message' => null,
            ];
            $this->db->insert($this->table, $defaults);
            $result = $this->db->queryRow($sql, [$groupId]);
        }

        $settings = [
            'enabled' => isset($result['enabled']) ? (int)$result['enabled'] : 0,
            'message' => $result['message'] ?? null,
        ];

        $this->cache->set($cacheKey, $settings, $this->cacheTtl);
        return $settings;
    }

    /**
     * فعال/غیرفعال کردن پیام خوش‌آمدگویی
     */
    public function setEnabled(int $groupId, bool $enabled): bool
    {
        $result = $this->db->update($this->table, ['enabled' => (int)$enabled], [
            'group_id' => $groupId,
        ]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'settings_' . $groupId);
            
            if ($this->logger) {
                $this->logger->info("Welcome message " . ($enabled ? 'enabled' : 'disabled') . " for group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * تنظیم متن پیام خوش‌آمدگویی
     * @param string|null $message (null = حذف پیام)
     */
    public function setMessage(int $groupId, ?string $message): bool
    {
        $data = ['message' => $message];
        $result = $this->db->update($this->table, $data, ['group_id' => $groupId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'settings_' . $groupId);
            
            if ($this->logger) {
                $this->logger->info("Welcome message updated for group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * بررسی فعال بودن پیام خوش‌آمدگویی
     */
    public function isEnabled(int $groupId): bool
    {
        $settings = $this->getSettings($groupId);
        return $settings['enabled'] == 1 && !empty($settings['message']);
    }

    /**
     * دریافت متن پیام خوش‌آمدگویی
     */
    public function getMessage(int $groupId): ?string
    {
        $settings = $this->getSettings($groupId);
        return $settings['message'];
    }

    /**
     * پردازش عضو جدید و ارسال پیام خوش‌آمدگویی
     * @param array $message پیام تلگرام (شامل new_chat_members)
     * @return bool
     */
    public function handleNewMember(array $message): bool
    {
        $chatId = $message['chat']['id'];
        $newMembers = $message['new_chat_members'] ?? [];

        if (empty($newMembers)) {
            return false;
        }

        // بررسی فعال بودن خوش‌آمدگویی
        if (!$this->isEnabled($chatId)) {
            return false;
        }

        $welcomeMessage = $this->getMessage($chatId);
        if (empty($welcomeMessage)) {
            return false;
        }

        $success = true;

        foreach ($newMembers as $member) {
            // اگر کاربر خود ربات است، نادیده گرفته می‌شود
            if (($member['id'] ?? 0) == $this->getBotId()) {
                continue;
            }

            // جایگزینی متغیرها در پیام
            $personalizedMessage = $this->personalizeMessage($welcomeMessage, $member);

            try {
                if ($this->telegram) {
                    $this->telegram->sendMessage($chatId, $personalizedMessage);
                    
                    if ($this->logger) {
                        $this->logger->debug("Welcome message sent to user {$member['id']} in group $chatId");
                    }
                }
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error("Failed to send welcome message: " . $e->getMessage());
                }
                $success = false;
            }
        }

        return $success;
    }

    /**
     * جایگزینی متغیرها در پیام خوش‌آمدگویی
     * متغیرهای پشتیبانی‌شده:
     * {first_name}, {last_name}, {username}, {full_name}, {mention}, {id}
     */
    private function personalizeMessage(string $message, array $member): string
    {
        $firstName = $member['first_name'] ?? '';
        $lastName = $member['last_name'] ?? '';
        $username = $member['username'] ?? '';
        $userId = $member['id'] ?? 0;

        $fullName = trim($firstName . ' ' . $lastName);
        if (empty($fullName)) {
            $fullName = $username ? '@' . $username : 'کاربر';
        }

        $mention = $username ? '@' . $username : "[$fullName](tg://user?id=$userId)";

        $replacements = [
            '{first_name}' => $firstName,
            '{last_name}' => $lastName,
            '{username}' => $username,
            '{full_name}' => $fullName,
            '{mention}' => $mention,
            '{id}' => $userId,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * دریافت آیدی ربات
     */
    private function getBotId(): int
    {
        // اگر Telegram API موجود باشد، اطلاعات ربات را دریافت می‌کنیم
        if ($this->telegram && method_exists($this->telegram, 'getMe')) {
            try {
                $me = $this->telegram->getMe();
                return $me['id'] ?? 0;
            } catch (\Exception $e) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * ارسال پیام خوش‌آمدگویی به یک کاربر خاص (برای تست یا ارسال مجدد)
     */
    public function sendWelcomeMessage(int $groupId, int $userId): bool
    {
        if (!$this->isEnabled($groupId)) {
            return false;
        }

        $welcomeMessage = $this->getMessage($groupId);
        if (empty($welcomeMessage)) {
            return false;
        }

        // دریافت اطلاعات کاربر از تلگرام
        if (!$this->telegram) {
            return false;
        }

        try {
            $member = $this->telegram->getChatMember($groupId, $userId);
            if (!$member) {
                return false;
            }

            $personalizedMessage = $this->personalizeMessage($welcomeMessage, $member['user'] ?? []);
            $this->telegram->sendMessage($groupId, $personalizedMessage);
            return true;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to send welcome message to $userId: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * حذف تنظیمات خوش‌آمدگویی یک گروه (غیرفعال کردن + پاک کردن پیام)
     */
    public function deleteSettings(int $groupId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE group_id = ?";
        $result = $this->db->execute($sql, [$groupId]);

        if ($result > 0) {
            $this->cache->delete($this->cachePrefix . 'settings_' . $groupId);
            
            if ($this->logger) {
                $this->logger->info("Welcome settings deleted for group $groupId");
            }
            return true;
        }

        return false;
    }

    /**
     * تنظیم Telegram API
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
}