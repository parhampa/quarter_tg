<?php

declare(strict_types=1);

namespace Tests\Unit;

use QuarterTg\Core\Logger;
use Tests\TestCase;

/**
 * تست کلاس Logger
 */
class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = __DIR__ . '/../../logs/test.log';
        
        // حذف فایل لاگ قبلی
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    protected function tearDown(): void
    {
        // حذف فایل لاگ تست
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function testLoggerCreatesLogFile(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('Test message');
        
        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Test message', $content);
    }

    public function testLoggerWritesDifferentLevels(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        $logger->critical('Critical message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('CRITICAL', $content);
    }

    public function testLoggerFiltersByLevel(): void
    {
        $logger = new Logger($this->logFile, 'warning');
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('DEBUG', $content);
        $this->assertStringNotContainsString('INFO', $content);
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('ERROR', $content);
    }

    public function testLoggerIncludesContext(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('User action', ['user_id' => 123, 'action' => 'login']);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('"user_id":123', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testLoggerIncludesTimestamp(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('Test with timestamp');

        $content = file_get_contents($this->logFile);
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testLoggerIncludesPID(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('Test with PID');

        $content = file_get_contents($this->logFile);
        $this->assertMatchesRegularExpression('/\[PID:\d+\]/', $content);
    }

    public function testLoggerLogMethod(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->log('info', 'Log method test');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Log method test', $content);
    }

    public function testLoggerSetLevel(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->debug('Debug message');
        $logger->setLevel('debug');
        $logger->debug('Debug message after level change');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringContainsString('Debug message after level change', $content);
    }

    public function testLoggerGetLevel(): void
    {
        $logger = new Logger($this->logFile, 'error');
        $this->assertEquals('error', $logger->getLevel());
        
        $logger->setLevel('debug');
        $this->assertEquals('debug', $logger->getLevel());
    }

    public function testLoggerSetMaxSize(): void
    {
        $logger = new Logger($this->logFile, 'info', 1024);
        $this->assertEquals(1024, $logger->getMaxSize());
        
        $logger->setMaxSize(2048);
        $this->assertEquals(2048, $logger->getMaxSize());
    }

    public function testLoggerSetMaxFiles(): void
    {
        $logger = new Logger($this->logFile, 'info', 1024, 10);
        $this->assertEquals(10, $logger->getMaxFiles());
        
        $logger->setMaxFiles(5);
        $this->assertEquals(5, $logger->getMaxFiles());
    }

    public function testLoggerAddHandler(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $handlerCalled = false;
        
        $logger->addHandler(function ($message) use (&$handlerCalled) {
            $handlerCalled = true;
        });
        
        $logger->info('Test with custom handler');
        $this->assertTrue($handlerCalled);
    }

    public function testLoggerClearHandlers(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $handlerCalled = false;
        
        $logger->addHandler(function ($message) use (&$handlerCalled) {
            $handlerCalled = true;
        });
        
        $logger->clearHandlers();
        $logger->info('Test after clearing handlers');
        
        // هادر اصلی (فایل) همچنان کار میکند
        $this->assertFileExists($this->logFile);
        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Test after clearing handlers', $content);
    }

    public function testLoggerGetLogFile(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $this->assertEquals($this->logFile, $logger->getLogFile());
    }

    public function testLoggerHandlesWriteError(): void
    {
        // مسیر غیرمجاز برای خطا
        $logger = new Logger('/invalid/path/log.log', 'info');
        $this->addToAssertionCount(1); // فقط مطمئن شوید که کرش نمیکند
        $logger->info('This should not crash');
    }
}