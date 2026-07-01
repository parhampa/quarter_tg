<?php

declare(strict_types=1);

namespace Tests\Unit;

use QuarterTg\Core\Config;
use Tests\TestCase;

/**
 * تست کلاس Config
 */
class ConfigTest extends TestCase
{
    public function testGetReturnsCorrectValue(): void
    {
        $config = new Config(['test_key' => 'test_value']);
        $this->assertEquals('test_value', $config->get('test_key'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $config = new Config([]);
        $this->assertEquals('default', $config->get('non_existent_key', 'default'));
    }

    public function testGetWithDotNotation(): void
    {
        $config = new Config([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
    }

    public function testSetUpdatesValue(): void
    {
        $config = new Config([]);
        $config->set('new_key', 'new_value');
        $this->assertEquals('new_value', $config->get('new_key'));
    }

    public function testSetWithDotNotation(): void
    {
        $config = new Config([]);
        $config->set('database.host', '127.0.0.1');
        $this->assertEquals('127.0.0.1', $config->get('database.host'));
    }

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $config = new Config(['exists' => 'yes']);
        $this->assertTrue($config->has('exists'));
        $this->assertFalse($config->has('not_exists'));
    }

    public function testAllReturnsFullArray(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $config = new Config($data);
        $this->assertEquals($data, $config->all());
    }

    public function testMergeCombinesConfigs(): void
    {
        $config1 = new Config(['key1' => 'value1']);
        $config1->merge(['key2' => 'value2']);
        
        $this->assertEquals('value1', $config1->get('key1'));
        $this->assertEquals('value2', $config1->get('key2'));
    }

    public function testValidateRequiredThrowsExceptionOnMissingKeys(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required config keys missing: bot_token');
        
        $config = new Config([]);
        $config->validateRequired(['bot_token']);
    }

    public function testValidateRequiredPassesWhenKeysExist(): void
    {
        $config = new Config(['bot_token' => '123', 'owner_id' => 456]);
        $this->assertNull($config->validateRequired(['bot_token', 'owner_id']));
    }

    public function testGetStringReturnsString(): void
    {
        $config = new Config(['str' => 'hello']);
        $this->assertEquals('hello', $config->getString('str'));
        $this->assertEquals('default', $config->getString('missing', 'default'));
    }

    public function testGetIntReturnsInt(): void
    {
        $config = new Config(['num' => '123']);
        $this->assertEquals(123, $config->getInt('num'));
        $this->assertEquals(0, $config->getInt('missing'));
        $this->assertEquals(42, $config->getInt('missing', 42));
    }

    public function testGetBoolReturnsBool(): void
    {
        $config = new Config(['true' => true, 'false_str' => 'false']);
        $this->assertTrue($config->getBool('true'));
        $this->assertFalse($config->getBool('false_str'));
        $this->assertFalse($config->getBool('missing'));
        $this->assertTrue($config->getBool('missing', true));
    }

    public function testGetArrayReturnsArray(): void
    {
        $config = new Config(['arr' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $config->getArray('arr'));
        $this->assertEquals([], $config->getArray('missing'));
        $this->assertEquals(['default'], $config->getArray('missing', ['default']));
    }

    public function testEnvMethod(): void
    {
        // تنظیم متغیر محیطی موقت
        putenv('TEST_ENV=test_value');
        
        $this->assertEquals('test_value', Config::env('TEST_ENV'));
        $this->assertEquals('default', Config::env('NOT_EXISTS', 'default'));
        
        // تست تبدیل boolean
        putenv('TEST_TRUE=true');
        $this->assertTrue(Config::env('TEST_TRUE'));
        
        putenv('TEST_FALSE=false');
        $this->assertFalse(Config::env('TEST_FALSE'));
        
        // پاک کردن
        putenv('TEST_ENV');
        putenv('TEST_TRUE');
        putenv('TEST_FALSE');
    }

    public function testCreateFromEnv(): void
    {
        // تنظیم متغیرهای محیطی
        putenv('BOT_TOKEN=test_token_from_env');
        putenv('DB_HOST=test_db_host');
        
        $config = Config::createFromEnv();
        $this->assertEquals('test_token_from_env', $config->get('bot_token'));
        $this->assertEquals('test_db_host', $config->get('database.host'));
        
        // پاک کردن
        putenv('BOT_TOKEN');
        putenv('DB_HOST');
    }

    public function testClearCache(): void
    {
        $config = new Config(['key' => 'value']);
        $config->get('key');
        $config->clearCache();
        
        // تنظیم مجدد برای تست
        $config->set('key', 'new_value');
        $this->assertEquals('new_value', $config->get('key'));
    }

    public function testIsLoaded(): void
    {
        $config = new Config([]);
        $this->assertTrue($config->isLoaded());
        
        $config = new Config(__DIR__ . '/../../config/config.php');
        $this->assertTrue($config->isLoaded());
    }

    public function testMagicGet(): void
    {
        $config = new Config(['test_key' => 'test_value']);
        $this->assertEquals('test_value', $config->test_key);
    }

    public function testMagicSet(): void
    {
        $config = new Config([]);
        $config->new_key = 'new_value';
        $this->assertEquals('new_value', $config->new_key);
    }

    public function testMagicIsset(): void
    {
        $config = new Config(['exists' => 'yes']);
        $this->assertTrue(isset($config->exists));
        $this->assertFalse(isset($config->not_exists));
    }
}