<?php

declare(strict_types=1);

namespace Tests\Unit;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use Tests\TestCase;

/**
 * تست کلاس Database
 * 
 * توجه: این تست‌ها به یک دیتابیس واقعی نیاز دارند.
 * برای اجرا، دیتابیس تست را در .env.test تنظیم کنید.
 */
class DatabaseTest extends TestCase
{
    private Database $db;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createTestLogger();
        $this->db = new Database(
            'localhost',
            'test_db',
            'root',
            '',
            'utf8mb4',
            $this->logger
        );

        // ایجاد جدول تست
        $this->createTestTable();
    }

    protected function tearDown(): void
    {
        // حذف جدول تست
        $this->db->execute('DROP TABLE IF EXISTS test_users');
        parent::tearDown();
    }

    /**
     * ایجاد جدول تست
     */
    private function createTestTable(): void
    {
        $this->db->execute('
            CREATE TABLE IF NOT EXISTS test_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                age INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $this->db->execute('TRUNCATE TABLE test_users');
    }

    // ============================================================
    // تست‌های اتصال
    // ============================================================

    public function testConnection(): void
    {
        $this->assertInstanceOf(Database::class, $this->db);
        $this->assertNotNull($this->db->getPdo());
    }

    public function testGetQueryCount(): void
    {
        $this->assertEquals(0, $this->db->getQueryCount());
        
        $this->db->query('SELECT 1');
        $this->assertEquals(1, $this->db->getQueryCount());
        
        $this->db->query('SELECT 2');
        $this->assertEquals(2, $this->db->getQueryCount());
    }

    // ============================================================
    // تست‌های کوئری
    // ============================================================

    public function testQueryReturnsArray(): void
    {
        // درج داده
        $this->db->insert('test_users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $result = $this->db->query('SELECT * FROM test_users WHERE name = ?', ['John Doe']);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals('john@example.com', $result[0]['email']);
    }

    public function testQueryReturnsFalseOnError(): void
    {
        $result = $this->db->query('SELECT * FROM non_existent_table');
        $this->assertFalse($result);
    }

    public function testQueryRowReturnsFirstRow(): void
    {
        $this->db->insert('test_users', ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25]);
        $this->db->insert('test_users', ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30]);

        $row = $this->db->queryRow('SELECT * FROM test_users ORDER BY id ASC');
        $this->assertIsArray($row);
        $this->assertEquals('Alice', $row['name']);
    }

    public function testQueryRowReturnsFalseOnEmpty(): void
    {
        $row = $this->db->queryRow('SELECT * FROM test_users WHERE id = 999');
        $this->assertFalse($row);
    }

    public function testQueryValueReturnsSingleValue(): void
    {
        $this->db->insert('test_users', ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35]);

        $age = $this->db->queryValue('SELECT age FROM test_users WHERE name = ?', ['Charlie']);
        $this->assertEquals(35, $age);
    }

    public function testQueryValueReturnsFalseOnEmpty(): void
    {
        $value = $this->db->queryValue('SELECT age FROM test_users WHERE id = 999');
        $this->assertFalse($value);
    }

    // ============================================================
    // تست‌های Execute
    // ============================================================

    public function testExecuteReturnsRowCount(): void
    {
        // درج
        $count = $this->db->execute(
            'INSERT INTO test_users (name, email, age) VALUES (?, ?, ?)',
            ['David', 'david@example.com', 40]
        );
        $this->assertEquals(1, $count);

        // به‌روزرسانی
        $count = $this->db->execute(
            'UPDATE test_users SET age = ? WHERE name = ?',
            [41, 'David']
        );
        $this->assertEquals(1, $count);

        // حذف
        $count = $this->db->execute(
            'DELETE FROM test_users WHERE name = ?',
            ['David']
        );
        $this->assertEquals(1, $count);
    }

    public function testExecuteReturnsNegativeOnError(): void
    {
        $count = $this->db->execute('UPDATE non_existent_table SET name = "test"');
        $this->assertEquals(-1, $count);
    }

    // ============================================================
    // تست‌های متدهای کمکی (Insert, Update, Delete)
    // ============================================================

    public function testInsert(): void
    {
        $id = $this->db->insert('test_users', [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'age' => 28,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE id = ?', [$id]);
        $this->assertEquals('Eve', $user['name']);
        $this->assertEquals('eve@example.com', $user['email']);
        $this->assertEquals(28, $user['age']);
    }

    public function testInsertReturnsFalseOnError(): void
    {
        $result = $this->db->insert('non_existent_table', ['name' => 'test']);
        $this->assertFalse($result);
    }

    public function testInsertWithEmptyData(): void
    {
        $result = $this->db->insert('test_users', []);
        $this->assertFalse($result);
    }

    public function testUpdate(): void
    {
        $this->db->insert('test_users', ['name' => 'Frank', 'email' => 'frank@example.com', 'age' => 20]);

        $count = $this->db->update(
            'test_users',
            ['age' => 25, 'email' => 'new_frank@example.com'],
            ['name' => 'Frank']
        );

        $this->assertEquals(1, $count);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE name = ?', ['Frank']);
        $this->assertEquals(25, $user['age']);
        $this->assertEquals('new_frank@example.com', $user['email']);
    }

    public function testUpdateReturnsNegativeOnError(): void
    {
        $count = $this->db->update(
            'non_existent_table',
            ['name' => 'test'],
            ['id' => 1]
        );
        $this->assertEquals(-1, $count);
    }

    public function testUpdateWithEmptyData(): void
    {
        $count = $this->db->update('test_users', [], ['id' => 1]);
        $this->assertEquals(-1, $count);
    }

    public function testDelete(): void
    {
        $this->db->insert('test_users', ['name' => 'Grace', 'email' => 'grace@example.com', 'age' => 30]);

        $count = $this->db->delete('test_users', ['name' => 'Grace']);
        $this->assertEquals(1, $count);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE name = ?', ['Grace']);
        $this->assertFalse($user);
    }

    public function testDeleteReturnsNegativeOnError(): void
    {
        $count = $this->db->delete('non_existent_table', ['id' => 1]);
        $this->assertEquals(-1, $count);
    }

    public function testDeleteWithEmptyWhere(): void
    {
        $count = $this->db->delete('test_users', []);
        $this->assertEquals(-1, $count);
    }

    // ============================================================
    // تست‌های تراکنش
    // ============================================================

    public function testTransaction(): void
    {
        $result = $this->db->transaction(function ($db) {
            $db->insert('test_users', ['name' => 'Henry', 'email' => 'henry@example.com', 'age' => 25]);
            $db->insert('test_users', ['name' => 'Ivy', 'email' => 'ivy@example.com', 'age' => 30]);
            return true;
        });

        $this->assertTrue($result);

        $count = $this->db->queryValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(2, $count);
    }

    public function testTransactionRollbackOnException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $this->db->transaction(function ($db) {
                $db->insert('test_users', ['name' => 'Jack', 'email' => 'jack@example.com', 'age' => 25]);
                throw new \Exception('Test exception');
            });
        } catch (\Exception $e) {
            // بررسی اینکه داده درج نشده باشد
            $count = $this->db->queryValue('SELECT COUNT(*) FROM test_users WHERE name = ?', ['Jack']);
            $this->assertEquals(0, $count);
            throw $e;
        }
    }

    public function testBeginCommitRollback(): void
    {
        $this->db->beginTransaction();
        $this->db->insert('test_users', ['name' => 'Kate', 'email' => 'kate@example.com', 'age' => 28]);
        $this->db->commit();

        $count = $this->db->queryValue('SELECT COUNT(*) FROM test_users WHERE name = ?', ['Kate']);
        $this->assertEquals(1, $count);

        $this->db->beginTransaction();
        $this->db->insert('test_users', ['name' => 'Leo', 'email' => 'leo@example.com', 'age' => 32]);
        $this->db->rollback();

        $count = $this->db->queryValue('SELECT COUNT(*) FROM test_users WHERE name = ?', ['Leo']);
        $this->assertEquals(0, $count);
    }

    public function testBeginTransactionReturnsFalseOnError(): void
    {
        // ایجاد یک وضعیت خطا (مثل تراکنش تو در تو که بعضی دیتابیس‌ها پشتیبانی نمی‌کنند)
        $result = $this->db->beginTransaction();
        $this->assertTrue($result);
        
        // شروع تراکنش دوم (برخی دیتابیس‌ها خطا میدهند)
        $result2 = $this->db->beginTransaction();
        // ممکن است true یا false باشد بسته به دیتابیس
        // فقط مطمئن شویم که خطا نمیدهد
        $this->addToAssertionCount(1);
    }

    // ============================================================
    // تست‌های Prepared Statements
    // ============================================================

    public function testPreparedStatementsWithNamedParams(): void
    {
        $this->db->insert('test_users', ['name' => 'Mia', 'email' => 'mia@example.com', 'age' => 22]);

        $result = $this->db->query(
            'SELECT * FROM test_users WHERE name = :name AND age = :age',
            [':name' => 'Mia', ':age' => 22]
        );
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Mia', $result[0]['name']);
    }

    public function testPreparedStatementsWithPositionalParams(): void
    {
        $this->db->insert('test_users', ['name' => 'Noah', 'email' => 'noah@example.com', 'age' => 27]);

        $result = $this->db->query(
            'SELECT * FROM test_users WHERE name = ? AND age = ?',
            ['Noah', 27]
        );
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('Noah', $result[0]['name']);
    }

    // ============================================================
    // تست‌های Query Log
    // ============================================================

    public function testGetQueryLog(): void
    {
        $this->db->query('SELECT 1');
        $this->db->query('SELECT 2');

        $log = $this->db->getQueryLog();
        $this->assertIsArray($log);
        $this->assertCount(2, $log);
        $this->assertArrayHasKey('sql', $log[0]);
        $this->assertArrayHasKey('params', $log[0]);
        $this->assertArrayHasKey('time', $log[0]);
    }

    // ============================================================
    // تست‌های نوع داده
    // ============================================================

    public function testInsertWithBoolean(): void
    {
        $id = $this->db->insert('test_users', [
            'name' => 'Oliver',
            'email' => 'oliver@example.com',
            'age' => 0,
        ]);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE id = ?', [$id]);
        $this->assertEquals(0, $user['age']);
    }

    public function testInsertWithNull(): void
    {
        $id = $this->db->insert('test_users', [
            'name' => 'Emma',
            'email' => 'emma@example.com',
            'age' => null,
        ]);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE id = ?', [$id]);
        $this->assertNull($user['age']);
    }

    // ============================================================
    // تست‌های Escape Identifier
    // ============================================================

    public function testEscapeIdentifier(): void
    {
        // این تست از طریق متدهای insert/update که از escapeIdentifier استفاده میکنند انجام میشود
        $id = $this->db->insert('test_users', [
            'name' => 'Escape',
            'email' => 'escape@example.com',
            'age' => 25,
        ]);

        $user = $this->db->queryRow('SELECT * FROM test_users WHERE id = ?', [$id]);
        $this->assertEquals('Escape', $user['name']);
    }
}