<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;

/**
 * کلاس پایه برای تمام تست‌های پروژه
 * شامل متدهای کمکی برای راه‌اندازی محیط تست
 */
abstract class TestCase extends BaseTestCase
{
    protected $database;
    protected $cache;
    protected $logger;
    protected $telegram;
    protected $config;

    /**
     * تنظیمات اولیه قبل از هر تست
     */
    protected function setUp(): void
    {
        parent::setUp();

        // تنظیمات دیتابیس تست
        $dbConfig = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'quarter_tg_test',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
        ];

        // ایجاد اشیاء پایه
        $this->database = new Database($dbConfig);
        $this->cache = new Cache(
            CACHE_DIR,
            60,
            $_ENV['CACHE_ENABLED'] ?? false
        );
        $this->logger = new Logger(
            LOGS_DIR . '/test.log',
            $_ENV['LOG_ENABLED'] ?? false
        );
        $this->telegram = new TelegramApi($_ENV['BOT_TOKEN'] ?? 'test_token');
        
        $this->config = [
            'bot_token' => $_ENV['BOT_TOKEN'] ?? 'test_token',
            'owner_id' => (int)($_ENV['OWNER_ID'] ?? 123456789),
            'database' => $dbConfig,
        ];

        // پاک کردن دیتابیس تست قبل از هر تست
        $this->cleanDatabase();
    }

    /**
     * پاک کردن دیتابیس تست پس از هر تست
     */
    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    /**
     * پاک کردن داده‌های تست از دیتابیس
     */
    protected function cleanDatabase(): void
    {
        $tables = [
            'bot_admins',
            'bot_sub_admins',
            'bot_permissions',
            'bot_group_locks',
            'bot_bans',
            'bot_mutes',
            'bot_warnings',
            'bot_welcome_settings',
            'bot_messages',
            'bot_command_logs',
            'bot_clear_cooldown',
        ];

        foreach ($tables as $table) {
            try {
                $this->database->execute("DELETE FROM $table WHERE group_id = 0 OR group_id IS NULL");
            } catch (\Exception $e) {
                // جدول ممکن است وجود نداشته باشد
            }
        }
    }

    /**
     * ایجاد یک گروه تست با آیدی مشخص
     */
    protected function createTestGroup(int $groupId = 999999): array
    {
        // درج یک رکورد پیش‌فرض در جدول قفل‌ها
        $this->database->insert('bot_group_locks', [
            'group_id' => $groupId,
            'lock_text' => 0,
            'lock_photo' => 0,
            'lock_video' => 0,
            'lock_gif' => 0,
            'lock_sticker' => 0,
            'lock_voice' => 0,
            'lock_video_note' => 0,
            'lock_link' => 0,
            'lock_tag' => 0,
            'lock_hashtag' => 0,
        ]);

        return [
            'id' => $groupId,
            'title' => 'Test Group',
            'type' => 'supergroup',
        ];
    }

    /**
     * ایجاد یک کاربر تست
     */
    protected function createTestUser(int $userId = 999999): array
    {
        return [
            'id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'is_bot' => false,
        ];
    }

    /**
     * ایجاد یک پیام تست
     */
    protected function createTestMessage(
        int $userId = 999999,
        int $chatId = 999999,
        string $text = '/help',
        int $messageId = 1
    ): array {
        return [
            'message_id' => $messageId,
            'from' => $this->createTestUser($userId),
            'chat' => [
                'id' => $chatId,
                'type' => 'supergroup',
                'title' => 'Test Group',
            ],
            'date' => time(),
            'text' => $text,
        ];
    }

    /**
     * ایجاد یک پیام تست با ریپلای
     */
    protected function createTestMessageWithReply(
        int $userId = 999999,
        int $chatId = 999999,
        string $text = '/ban',
        int $replyToUserId = 888888,
        int $messageId = 1,
        int $replyMessageId = 2
    ): array {
        $message = $this->createTestMessage($userId, $chatId, $text, $messageId);
        $message['reply_to_message'] = $this->createTestMessage($replyToUserId, $chatId, 'test reply', $replyMessageId);
        return $message;
    }

    /**
     * بررسی اینکه یک پیام در دیتابیس ثبت شده است
     */
    protected function assertMessageLogged(int $groupId, int $messageId): void
    {
        $sql = "SELECT id FROM bot_messages WHERE group_id = ? AND message_id = ?";
        $result = $this->database->queryColumn($sql, [$groupId, $messageId]);
        $this->assertNotFalse($result, "Message $messageId not logged in database");
    }

    /**
     * بررسی اینکه یک کاربر ادمین است
     */
    protected function assertUserIsAdmin(int $groupId, int $userId): void
    {
        $sql = "SELECT id FROM bot_admins WHERE group_id = ? AND user_id = ?";
        $result = $this->database->queryColumn($sql, [$groupId, $userId]);
        $this->assertNotFalse($result, "User $userId is not admin in group $groupId");
    }

    /**
     * بررسی اینکه یک کاربر ساب‌ادمین است
     */
    protected function assertUserIsSubAdmin(int $groupId, int $userId): void
    {
        $sql = "SELECT id FROM bot_sub_admins WHERE group_id = ? AND user_id = ?";
        $result = $this->database->queryColumn($sql, [$groupId, $userId]);
        $this->assertNotFalse($result, "User $userId is not sub-admin in group $groupId");
    }

    /**
     * بررسی اینکه یک کاربر بن شده است
     */
    protected function assertUserIsBanned(int $groupId, int $userId): void
    {
        $sql = "SELECT id FROM bot_bans WHERE group_id = ? AND user_id = ?";
        $result = $this->database->queryColumn($sql, [$groupId, $userId]);
        $this->assertNotFalse($result, "User $userId is not banned in group $groupId");
    }

    /**
     * بررسی اینکه یک کاربر میوت شده است
     */
    protected function assertUserIsMuted(int $groupId, int $userId): void
    {
        $sql = "SELECT id FROM bot_mutes WHERE group_id = ? AND user_id = ? AND (until IS NULL OR until > NOW())";
        $result = $this->database->queryColumn($sql, [$groupId, $userId]);
        $this->assertNotFalse($result, "User $userId is not muted in group $groupId");
    }

    /**
     * ایجاد داده‌های تست در دیتابیس
     */
    protected function seedTestData(): void
    {
        // درج یک ادمین تست
        $this->database->insert('bot_admins', [
            'group_id' => 999999,
            'user_id' => 999999,
            'username' => 'admin',
            'first_name' => 'Admin',
            'added_by' => 123456789,
            'added_at' => date('Y-m-d H:i:s'),
        ]);

        // درج یک ساب‌ادمین تست
        $this->database->insert('bot_sub_admins', [
            'group_id' => 999999,
            'user_id' => 888888,
            'username' => 'subadmin',
            'first_name' => 'SubAdmin',
            'added_by' => 999999,
            'added_at' => date('Y-m-d H:i:s'),
        ]);
    }
}