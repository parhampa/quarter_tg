<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use QuarterTg\Core\Config;
use QuarterTg\Core\Logger;

/**
 * کلاس پایه برای همه تست‌ها
 */
abstract class TestCase extends BaseTestCase
{
    protected Config $config;
    protected Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ایجاد یک Logger موقت برای تست
        $this->logger = new Logger(
            __DIR__ . '/../logs/test.log',
            'debug',
            1024,
            2
        );

        // ایجاد Config با مقادیر پیش‌فرض تست
        $this->config = new Config([
            'bot_token' => 'test_token_123456',
            'database' => [
                'host' => 'localhost',
                'name' => 'test_db',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            'cache' => [
                'path' => __DIR__ . '/../cache_test',
                'ttl' => 60,
            ],
            'log' => [
                'path' => __DIR__ . '/../logs/test.log',
                'level' => 'debug',
                'max_size' => 1024,
            ],
            'owner_id' => 123456789,
            'modules' => [
                'path' => __DIR__ . '/../src/Modules',
                'namespace' => 'QuarterTg\\Modules\\',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        // پاک کردن فایل‌های تست (اختیاری)
        parent::tearDown();
    }

    /**
     * ایجاد یک Logger موقت برای تست
     */
    protected function createTestLogger(): Logger
    {
        return new Logger(
            __DIR__ . '/../logs/test.log',
            'debug',
            1024,
            2
        );
    }

    /**
     * ایجاد یک Config موقت برای تست
     */
    protected function createTestConfig(array $overrides = []): Config
    {
        $defaults = [
            'bot_token' => 'test_token_123456',
            'database' => [
                'host' => 'localhost',
                'name' => 'test_db',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            'cache' => [
                'path' => __DIR__ . '/../cache_test',
                'ttl' => 60,
            ],
            'log' => [
                'path' => __DIR__ . '/../logs/test.log',
                'level' => 'debug',
                'max_size' => 1024,
            ],
            'owner_id' => 123456789,
        ];

        $config = array_merge_recursive($defaults, $overrides);
        return new Config($config);
    }
}