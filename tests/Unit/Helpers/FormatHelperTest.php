<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use QuarterTg\Helpers\FormatHelper;
use Tests\TestCase;

/**
 * تست کلاس FormatHelper
 */
class FormatHelperTest extends TestCase
{
    public function testToPersianDigits(): void
    {
        $this->assertEquals('۰۱۲۳۴۵۶۷۸۹', FormatHelper::toPersianDigits('0123456789'));
        $this->assertEquals('۱۲۳', FormatHelper::toPersianDigits(123));
        $this->assertEquals('۴۵۶.۷۸', FormatHelper::toPersianDigits(456.78));
        $this->assertEquals('', FormatHelper::toPersianDigits(''));
    }

    public function testToEnglishDigits(): void
    {
        $this->assertEquals('0123456789', FormatHelper::toEnglishDigits('۰۱۲۳۴۵۶۷۸۹'));
        $this->assertEquals('123', FormatHelper::toEnglishDigits('۱۲۳'));
        $this->assertEquals('456.78', FormatHelper::toEnglishDigits('۴۵۶.۷۸'));
        $this->assertEquals('', FormatHelper::toEnglishDigits(''));
    }

    public function testNumberFormat(): void
    {
        $this->assertEquals('۱٬۰۰۰', FormatHelper::numberFormat(1000));
        $this->assertEquals('۲٬۵۰۰', FormatHelper::numberFormat(2500));
        $this->assertEquals('۱٬۰۰۰٬۰۰۰', FormatHelper::numberFormat(1000000));
        $this->assertEquals('۱٬۲۳۴.۵۶', FormatHelper::numberFormat(1234.56, 2));
        $this->assertEquals('۰', FormatHelper::numberFormat(0));
    }

    public function testFormatTimestamp(): void
    {
        $timestamp = strtotime('2024-01-15 14:30:00');
        $this->assertEquals('2024-01-15 14:30:00', FormatHelper::formatTimestamp($timestamp));
        $this->assertEquals('۱۴۰۲-۱۰-۲۵ ۱۴:۳۰:۰۰', FormatHelper::formatTimestamp($timestamp, 'Y-m-d H:i:s', true));
        $this->assertEquals('15/01/2024', FormatHelper::formatTimestamp($timestamp, 'd/m/Y', false));
    }

    public function testTimeAgo(): void
    {
        $now = time();
        
        // چند ثانیه پیش
        $result = FormatHelper::timeAgo($now - 30);
        $this->assertStringContainsString('ثانیه', $result);
        $this->assertStringContainsString('پیش', $result);
        
        // چند دقیقه پیش
        $result = FormatHelper::timeAgo($now - 300);
        $this->assertStringContainsString('دقیقه', $result);
        $this->assertStringContainsString('پیش', $result);
        
        // چند ساعت پیش
        $result = FormatHelper::timeAgo($now - 7200);
        $this->assertStringContainsString('ساعت', $result);
        $this->assertStringContainsString('پیش', $result);
    }

    public function testFormatSize(): void
    {
        $this->assertEquals('۰ بایت', FormatHelper::formatSize(0));
        $this->assertStringContainsString('بایت', FormatHelper::formatSize(100));
        $this->assertStringContainsString('کیلوبایت', FormatHelper::formatSize(1024));
        $this->assertStringContainsString('مگابایت', FormatHelper::formatSize(1024 * 1024));
        $this->assertStringContainsString('گیگابایت', FormatHelper::formatSize(1024 * 1024 * 1024));
    }

    public function testFormatDuration(): void
    {
        $this->assertStringContainsString('ثانیه', FormatHelper::formatDuration(30));
        $this->assertStringContainsString('دقیقه', FormatHelper::formatDuration(90));
        $this->assertStringContainsString('ساعت', FormatHelper::formatDuration(7200));
        $this->assertStringContainsString('روز', FormatHelper::formatDuration(172800)); // 2 روز
    }

    public function testBold(): void
    {
        $this->assertEquals('*text*', FormatHelper::bold('text'));
        $this->assertEquals('*hello world*', FormatHelper::bold('hello world'));
    }

    public function testItalic(): void
    {
        $this->assertEquals('_text_', FormatHelper::italic('text'));
    }

    public function testUnderline(): void
    {
        $this->assertEquals('__text__', FormatHelper::underline('text'));
    }

    public function testStrikethrough(): void
    {
        $this->assertEquals('~text~', FormatHelper::strikethrough('text'));
    }

    public function testCode(): void
    {
        $this->assertEquals('`code`', FormatHelper::code('code'));
    }

    public function testCodeBlock(): void
    {
        $this->assertEquals("```\ntext\n```", FormatHelper::codeBlock('text'));
        $this->assertEquals("```php\ntext\n```", FormatHelper::codeBlock('text', 'php'));
    }

    public function testLink(): void
    {
        $this->assertEquals('[text](url)', FormatHelper::link('text', 'url'));
    }

    public function testSpoiler(): void
    {
        $this->assertEquals('||text||', FormatHelper::spoiler('text'));
    }

    public function testQuote(): void
    {
        $text = "line1\nline2";
        $expected = "> line1\n> line2";
        $this->assertEquals($expected, FormatHelper::quote($text));
    }

    public function testUsername(): void
    {
        $this->assertEquals('@test', FormatHelper::username('test'));
        $this->assertEquals('@test', FormatHelper::username('@test'));
        $this->assertEquals('نامشخص', FormatHelper::username(null));
        $this->assertEquals('نامشخص', FormatHelper::username(''));
    }

    public function testUserId(): void
    {
        $this->assertEquals('`123`', FormatHelper::userId(123));
        $this->assertEquals('`۱۲۳`', FormatHelper::userId(123));
    }

    public function testUserDisplay(): void
    {
        $user = ['first_name' => 'John', 'last_name' => 'Doe'];
        $this->assertEquals('John Doe', FormatHelper::userDisplay($user));
        
        $userWithUsername = ['first_name' => 'John', 'username' => 'john'];
        $this->assertEquals('@john', FormatHelper::userDisplay($userWithUsername));
        
        $userWithId = ['id' => 123];
        $this->assertEquals('کاربر ناشناس', FormatHelper::userDisplay($userWithId));
        
        $emptyUser = [];
        $this->assertEquals('کاربر ناشناس', FormatHelper::userDisplay($emptyUser));
    }

    public function testDivider(): void
    {
        $this->assertEquals('━━━━━━━━━━', FormatHelper::divider('━', 10));
        $this->assertEquals('----------', FormatHelper::divider('-', 10));
        $this->assertEquals('', FormatHelper::divider('━', 0));
    }

    public function testStatusEmoji(): void
    {
        $this->assertEquals('✅', FormatHelper::statusEmoji(true));
        $this->assertEquals('❌', FormatHelper::statusEmoji(false));
    }

    public function testLevelEmoji(): void
    {
        $this->assertEquals('🔍', FormatHelper::levelEmoji('debug'));
        $this->assertEquals('ℹ️', FormatHelper::levelEmoji('info'));
        $this->assertEquals('⚠️', FormatHelper::levelEmoji('warning'));
        $this->assertEquals('❌', FormatHelper::levelEmoji('error'));
        $this->assertEquals('🚨', FormatHelper::levelEmoji('critical'));
        $this->assertEquals('📌', FormatHelper::levelEmoji('unknown'));
    }

    public function testTruncate(): void
    {
        $this->assertEquals('hello', FormatHelper::truncate('hello', 10));
        $this->assertEquals('hel...', FormatHelper::truncate('hello world', 3));
        $this->assertEquals('hello world!', FormatHelper::truncate('hello world!', 20));
        
        $persianText = 'سلام دنیا';
        $this->assertEquals('سلام د...', FormatHelper::truncate($persianText, 4));
    }

    public function testSanitize(): void
    {
        $this->assertEquals('hello', FormatHelper::sanitize('<b>hello</b>'));
        $this->assertEquals('1 &lt; 2', FormatHelper::sanitize('1 < 2'));
        $this->assertEquals('', FormatHelper::sanitize('<script>alert()</script>'));
    }
}