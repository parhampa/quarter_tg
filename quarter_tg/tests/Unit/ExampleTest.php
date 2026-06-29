<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * نمونه تست ساده برای بررسی عملکرد پایه
 */
class ExampleTest extends TestCase
{
    /**
     * تست اینکه PHPUnit به‌درستی کار می‌کند
     */
    public function testBasicTest(): void
    {
        $this->assertTrue(true);
    }

    /**
     * تست اتصال به دیتابیس
     */
    public function testDatabaseConnection(): void
    {
        $this->assertTrue($this->database->isConnected());
    }

    /**
     * تست ایجاد گروه تست
     */
    public function testCreateTestGroup(): void
    {
        $groupId = 777777;
        $group = $this->createTestGroup($groupId);
        
        $this->assertEquals($groupId, $group['id']);
        $this->assertEquals('Test Group', $group['title']);
        
        // بررسی اینکه رکورد در دیتابیس ایجاد شده است
        $sql = "SELECT id FROM bot_group_locks WHERE group_id = ?";
        $result = $this->database->queryColumn($sql, [$groupId]);
        $this->assertNotFalse($result);
    }

    /**
     * تست ایجاد کاربر تست
     */
    public function testCreateTestUser(): void
    {
        $userId = 666666;
        $user = $this->createTestUser($userId);
        
        $this->assertEquals($userId, $user['id']);
        $this->assertEquals('Test', $user['first_name']);
        $this->assertEquals('testuser', $user['username']);
    }
}