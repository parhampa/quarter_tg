<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use QuarterTg\Helpers\ValidationHelper;
use Tests\TestCase;

/**
 * تست کلاس ValidationHelper
 */
class ValidationHelperTest extends TestCase
{
    public function testValidateId(): void
    {
        $this->assertTrue(ValidationHelper::validateId(123));
        $this->assertTrue(ValidationHelper::validateId('456'));
        $this->assertFalse(ValidationHelper::validateId(-1));
        $this->assertFalse(ValidationHelper::validateId(0));
        $this->assertFalse(ValidationHelper::validateId('abc'));
        $this->assertFalse(ValidationHelper::validateId(null));
        $this->assertFalse(ValidationHelper::validateId(''));
    }

    public function testValidateUsername(): void
    {
        $this->assertTrue(ValidationHelper::validateUsername('john_doe'));
        $this->assertTrue(ValidationHelper::validateUsername('@john_doe'));
        $this->assertTrue(ValidationHelper::validateUsername('JohnDoe123'));
        
        $this->assertFalse(ValidationHelper::validateUsername(''));
        $this->assertFalse(ValidationHelper::validateUsername('joe')); // کمتر از ۵ کاراکتر
        $this->assertFalse(ValidationHelper::validateUsername('this_is_a_very_long_username_that_exceeds_32_characters'));
        $this->assertFalse(ValidationHelper::validateUsername('john@doe'));
        $this->assertFalse(ValidationHelper::validateUsername('john doe'));
    }

    public function testValidateMessage(): void
    {
        $this->assertTrue(ValidationHelper::validateMessage('Hello'));
        $this->assertTrue(ValidationHelper::validateMessage('Hi', 2, 10));
        
        $this->assertFalse(ValidationHelper::validateMessage(''));
        $this->assertFalse(ValidationHelper::validateMessage('A', 2, 10));
        $this->assertFalse(ValidationHelper::validateMessage(str_repeat('A', 5000), 1, 4096));
    }

    public function testValidateCommandParams(): void
    {
        $this->assertTrue(ValidationHelper::validateCommandParams('hello', 1, 5));
        $this->assertTrue(ValidationHelper::validateCommandParams('user @test 1h', 2, 5));
        
        $this->assertFalse(ValidationHelper::validateCommandParams('', 1, 5));
        $this->assertFalse(ValidationHelper::validateCommandParams('hello world', 5, 10));
    }

    public function testValidateEmail(): void
    {
        $this->assertTrue(ValidationHelper::validateEmail('test@example.com'));
        $this->assertTrue(ValidationHelper::validateEmail('user.name@domain.co.uk'));
        
        $this->assertFalse(ValidationHelper::validateEmail('test@'));
        $this->assertFalse(ValidationHelper::validateEmail('@example.com'));
        $this->assertFalse(ValidationHelper::validateEmail('test@example'));
        $this->assertFalse(ValidationHelper::validateEmail('test'));
    }

    public function testValidateUrl(): void
    {
        $this->assertTrue(ValidationHelper::validateUrl('https://example.com'));
        $this->assertTrue(ValidationHelper::validateUrl('http://example.com'));
        $this->assertTrue(ValidationHelper::validateUrl('https://example.com/path?param=value'));
        
        $this->assertFalse(ValidationHelper::validateUrl('example.com'));
        $this->assertFalse(ValidationHelper::validateUrl('ftp://example.com'));
        $this->assertFalse(ValidationHelper::validateUrl('not a url'));
    }

    public function testValidateInteger(): void
    {
        $this->assertTrue(ValidationHelper::validateInteger(10, 1, 100));
        $this->assertTrue(ValidationHelper::validateInteger(50, 1, 100));
        $this->assertTrue(ValidationHelper::validateInteger('25', 1, 100));
        
        $this->assertFalse(ValidationHelper::validateInteger(0, 1, 100));
        $this->assertFalse(ValidationHelper::validateInteger(200, 1, 100));
        $this->assertFalse(ValidationHelper::validateInteger('abc', 1, 100));
    }

    public function testValidateDate(): void
    {
        $this->assertTrue(ValidationHelper::validateDate('2024-01-15'));
        $this->assertTrue(ValidationHelper::validateDate('2024-12-31'));
        $this->assertTrue(ValidationHelper::validateDate('15/01/2024', 'd/m/Y'));
        
        $this->assertFalse(ValidationHelper::validateDate('2024-13-01'));
        $this->assertFalse(ValidationHelper::validateDate('2024-01-32'));
        $this->assertFalse(ValidationHelper::validateDate('not a date'));
    }

    public function testValidateDuration(): void
    {
        $this->assertTrue(ValidationHelper::validateDuration('30s'));
        $this->assertTrue(ValidationHelper::validateDuration('5m'));
        $this->assertTrue(ValidationHelper::validateDuration('2h'));
        $this->assertTrue(ValidationHelper::validateDuration('1d'));
        $this->assertTrue(ValidationHelper::validateDuration('1w'));
        
        $this->assertFalse(ValidationHelper::validateDuration('abc'));
        $this->assertFalse(ValidationHelper::validateDuration('30'));
        $this->assertFalse(ValidationHelper::validateDuration('5m '));
        $this->assertFalse(ValidationHelper::validateDuration(''));    }

    public function testValidateLockType(): void
    {
        $allowed = ['links', 'tags', 'spam'];
        
        $this->assertTrue(ValidationHelper::validateLockType('links', $allowed));
        $this->assertTrue(ValidationHelper::validateLockType('tags', $allowed));
        $this->assertFalse(ValidationHelper::validateLockType('hashtags', $allowed));
        $this->assertFalse(ValidationHelper::validateLockType('', $allowed));
    }

    public function testValidateAdminLevel(): void
    {
        $allowed = ['admin', 'super_admin'];
        
        $this->assertTrue(ValidationHelper::validateAdminLevel('admin'));
        $this->assertTrue(ValidationHelper::validateAdminLevel('super_admin'));
        $this->assertFalse(ValidationHelper::validateAdminLevel('owner'));
        $this->assertFalse(ValidationHelper::validateAdminLevel(''));
    }

    public function testValidatePersian(): void
    {
        $this->assertTrue(ValidationHelper::validatePersian('سلام'));
        $this->assertTrue(ValidationHelper::validatePersian('سلام دنیا'));
        $this->assertTrue(ValidationHelper::validatePersian('سلام ۱۲۳')); // اعداد مجاز نیستند
        
        $this->assertFalse(ValidationHelper::validatePersian('Hello'));
        $this->assertFalse(ValidationHelper::validatePersian('سلام123'));
        $this->assertFalse(ValidationHelper::validatePersian(''));
    }

    public function testValidateEnglish(): void
    {
        $this->assertTrue(ValidationHelper::validateEnglish('Hello'));
        $this->assertTrue(ValidationHelper::validateEnglish('Hello World'));
        $this->assertTrue(ValidationHelper::validateEnglish('Hello 123')); // اعداد مجاز نیستند
        
        $this->assertFalse(ValidationHelper::validateEnglish('سلام'));
        $this->assertFalse(ValidationHelper::validateEnglish('Hello123'));
        $this->assertFalse(ValidationHelper::validateEnglish(''));
    }

    public function testValidateNumeric(): void
    {
        $this->assertTrue(ValidationHelper::validateNumeric('123'));
        $this->assertTrue(ValidationHelper::validateNumeric('123.45'));
        $this->assertTrue(ValidationHelper::validateNumeric('-123'));
        
        $this->assertFalse(ValidationHelper::validateNumeric('abc'));
        $this->assertFalse(ValidationHelper::validateNumeric('12a'));
        $this->assertFalse(ValidationHelper::validateNumeric(''));
    }

    public function testValidateSafeString(): void
    {
        $this->assertTrue(ValidationHelper::validateSafeString('Hello World'));
        $this->assertTrue(ValidationHelper::validateSafeString('سلام دنیا'));
        $this->assertTrue(ValidationHelper::validateSafeString('hello-123_'));
        
        $this->assertFalse(ValidationHelper::validateSafeString('hello@world'));
        $this->assertFalse(ValidationHelper::validateSafeString('hello#world'));
        $this->assertFalse(ValidationHelper::validateSafeString(''));
    }

    public function testValidateArray(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        
        $this->assertTrue(ValidationHelper::validateArray($data));
        $this->assertTrue(ValidationHelper::validateArray($data, ['key1', 'key2']));
        $this->assertFalse(ValidationHelper::validateArray($data, ['key1', 'key2', 'key3']));
        $this->assertFalse(ValidationHelper::validateArray([], ['key1']));
        $this->assertFalse(ValidationHelper::validateArray([], []));
    }

    public function testValidateBanParams(): void
    {
        // بدون پارامتر
        $result = ValidationHelper::validateBanParams('');
        $this->assertFalse($result['valid']);
        $this->assertNotNull($result['error']);
        
        // با کاربر هدف
        $result = ValidationHelper::validateBanParams('@user');
        $this->assertTrue($result['valid']);
        $this->assertEquals('@user', $result['target']);
        $this->assertNull($result['duration']);
        $this->assertEquals('تخلف از قوانین گروه', $result['reason']);
        
        // با کاربر هدف و زمان
        $result = ValidationHelper::validateBanParams('@user 1h');
        $this->assertTrue($result['valid']);
        $this->assertEquals('@user', $result['target']);
        $this->assertEquals('1h', $result['duration']);
        $this->assertEquals('تخلف از قوانین گروه', $result['reason']);
        
        // با کاربر هدف، زمان و دلیل
        $result = ValidationHelper::validateBanParams('@user 1h spam');
        $this->assertTrue($result['valid']);
        $this->assertEquals('@user', $result['target']);
        $this->assertEquals('1h', $result['duration']);
        $this->assertEquals('spam', $result['reason']);
    }

    public function testValidateClearParams(): void
    {
        // بدون پارامتر (پیشفرض ۱)
        $result = ValidationHelper::validateClearParams('');
        $this->assertTrue($result['valid']);
        $this->assertEquals(1, $result['count']);
        
        // عدد معتبر
        $result = ValidationHelper::validateClearParams('10');
        $this->assertTrue($result['valid']);
        $this->assertEquals(10, $result['count']);
        
        // عدد معتبر با محدودیت
        $result = ValidationHelper::validateClearParams('150', 100);
        $this->assertFalse($result['valid']);
        $this->assertEquals('حداکثر تعداد مجاز 100 پیام است.', $result['error']);
        
        // عدد نامعتبر
        $result = ValidationHelper::validateClearParams('abc');
        $this->assertFalse($result['valid']);
        $this->assertEquals('تعداد باید یک عدد مثبت باشد.', $result['error']);
        
        // صفر
        $result = ValidationHelper::validateClearParams('0');
        $this->assertFalse($result['valid']);
    }

    public function testValidateTextParams(): void
    {
        // متن معتبر
        $result = ValidationHelper::validateTextParams('Hello World');
        $this->assertTrue($result['valid']);
        $this->assertEquals('Hello World', $result['text']);
        
        // متن خالی
        $result = ValidationHelper::validateTextParams('');
        $this->assertFalse($result['valid']);
        $this->assertEquals('متن نمی‌تواند خالی باشد.', $result['error']);
        
        // متن با حداقل طول
        $result = ValidationHelper::validateTextParams('Hi', 3);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('حداقل باید', $result['error']);
        
        // متن با حداکثر طول
        $longText = str_repeat('A', 5000);
        $result = ValidationHelper::validateTextParams($longText, 1, 100);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('حداکثر باید', $result['error']);
    }

    public function testValidateLogLevel(): void
    {
        $this->assertTrue(ValidationHelper::validateLogLevel('debug'));
        $this->assertTrue(ValidationHelper::validateLogLevel('info'));
        $this->assertTrue(ValidationHelper::validateLogLevel('warning'));
        $this->assertTrue(ValidationHelper::validateLogLevel('error'));
        $this->assertTrue(ValidationHelper::validateLogLevel('critical'));
        
        $this->assertFalse(ValidationHelper::validateLogLevel('invalid'));
        $this->assertFalse(ValidationHelper::validateLogLevel(''));
    }

    public function testValidateJson(): void
    {
        $this->assertTrue(ValidationHelper::validateJson('{"key":"value"}'));
        $this->assertTrue(ValidationHelper::validateJson('[1,2,3]'));
        $this->assertTrue(ValidationHelper::validateJson('{"nested":{"key":"value"}}'));
        
        $this->assertFalse(ValidationHelper::validateJson('{"key":"value"'));
        $this->assertFalse(ValidationHelper::validateJson('not json'));
        $this->assertFalse(ValidationHelper::validateJson(''));
    }

    public function testValidateNoHtml(): void
    {
        $this->assertTrue(ValidationHelper::validateNoHtml('Hello World'));
        $this->assertTrue(ValidationHelper::validateNoHtml('سلام دنیا'));
        $this->assertTrue(ValidationHelper::validateNoHtml('Hello < World'));
        
        $this->assertFalse(ValidationHelper::validateNoHtml('<b>Hello</b>'));
        $this->assertFalse(ValidationHelper::validateNoHtml('<script>alert()</script>'));
        $this->assertFalse(ValidationHelper::validateNoHtml('<div>Content</div>'));
    }
}