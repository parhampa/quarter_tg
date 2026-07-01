<?php

declare(strict_types=1);

namespace QuarterTg\Helpers;

use Throwable;

/**
 * کلاس کمکی برای فرمت‌دهی و تبدیل داده‌ها
 * 
 * مسئولیت‌ها:
 * - تبدیل اعداد به فارسی و برعکس
 * - فرمت‌دهی تاریخ و زمان (شمسی و میلادی)
 * - فرمت‌دهی اندازه فایل‌ها
 * - فرمت‌دهی زمان (ثانیه به روز، ساعت، دقیقه)
 * - تولید متن با فرمت Markdown تلگرام
 * - فرمت‌دهی یوزرنیم و شناسه
 */
class FormatHelper
{
    /** @var array نقشه تبدیل اعداد انگلیسی به فارسی */
    private static array $englishToPersianDigits = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳',
        '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷',
        '8' => '۸', '9' => '۹'
    ];

    /** @var array نقشه تبدیل اعداد فارسی به انگلیسی */
    private static array $persianToEnglishDigits = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3',
        '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7',
        '۸' => '8', '۹' => '9'
    ];

    // ============================================================
    // تبدیل اعداد
    // ============================================================

    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    public static function toPersianDigits(string|int|float $number): string
    {
        $number = (string)$number;
        return strtr($number, self::$englishToPersianDigits);
    }

    /**
     * تبدیل اعداد فارسی به انگلیسی
     */
    public static function toEnglishDigits(string $number): string
    {
        return strtr($number, self::$persianToEnglishDigits);
    }

    /**
     * فرمت‌دهی عدد با جداکننده هزارگان (به فارسی)
     */
    public static function numberFormat(int|float $number, int $decimals = 0): string
    {
        $formatted = number_format($number, $decimals, '.', ',');
        return self::toPersianDigits($formatted);
    }

    // ============================================================
    // فرمت‌دهی تاریخ و زمان
    // ============================================================

    /**
     * فرمت‌دهی زمان به صورت خوانا (با پشتیبانی از فارسی)
     */
    public static function formatTimestamp(int $timestamp, string $format = 'Y-m-d H:i:s', bool $persian = true): string
    {
        try {
            $date = date($format, $timestamp);
            return $persian ? self::toPersianDigits($date) : $date;
        } catch (Throwable $e) {
            return date($format);
        }
    }

    /**
     * فرمت‌دهی تاریخ به صورت شمسی (ساده)
     * توجه: برای تبدیل دقیق نیاز به کتابخانه جداگانه است، این یک پیاده‌سازی ساده است
     */
    public static function formatPersianDate(int $timestamp): string
    {
        // این یک پیاده‌سازی ساده است و دقیق نیست
        // برای استفاده واقعی از کتابخانه‌هایی مثل Morilog\Jalali استفاده کنید
        $date = date('Y-m-d H:i:s', $timestamp);
        return self::toPersianDigits($date);
    }

    /**
     * فرمت‌دهی زمان به صورت نسبی (مثلاً "۲ ساعت پیش")
     */
    public static function timeAgo(int $timestamp, bool $persian = true): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $persian ? 'چند ثانیه پیش' : 'a few seconds ago';
        }
        
        $minutes = floor($diff / 60);
        if ($minutes < 60) {
            return ($persian ? self::toPersianDigits($minutes) : $minutes) . ' ' . ($persian ? 'دقیقه پیش' : 'minutes ago');
        }
        
        $hours = floor($minutes / 60);
        if ($hours < 24) {
            return ($persian ? self::toPersianDigits($hours) : $hours) . ' ' . ($persian ? 'ساعت پیش' : 'hours ago');
        }
        
        $days = floor($hours / 24);
        if ($days < 30) {
            return ($persian ? self::toPersianDigits($days) : $days) . ' ' . ($persian ? 'روز پیش' : 'days ago');
        }
        
        $months = floor($days / 30);
        if ($months < 12) {
            return ($persian ? self::toPersianDigits($months) : $months) . ' ' . ($persian ? 'ماه پیش' : 'months ago');
        }
        
        $years = floor($months / 12);
        return ($persian ? self::toPersianDigits($years) : $years) . ' ' . ($persian ? 'سال پیش' : 'years ago');
    }

    // ============================================================
    // فرمت‌دهی اندازه فایل
    // ============================================================

    /**
     * فرمت‌دهی اندازه فایل به صورت خوانا
     */
    public static function formatSize(int $bytes, int $decimals = 2, bool $persian = true): string
    {
        if ($bytes === 0) {
            return $persian ? '۰ بایت' : '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $size = $bytes / pow(1024, $i);
        $formatted = number_format($size, $decimals, '.', ',');

        if ($persian) {
            $formatted = self::toPersianDigits($formatted);
            $unitNames = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
            return $formatted . ' ' . $unitNames[$i];
        }

        return $formatted . ' ' . $units[$i];
    }

    // ============================================================
    // فرمت‌دهی زمان (ثانیه به روز/ساعت/دقیقه)
    // ============================================================

    /**
     * تبدیل ثانیه به زمان خوانا (مثلاً ۱ روز ۲ ساعت ۳۰ دقیقه)
     */
    public static function formatDuration(int $seconds, bool $persian = true): string
    {
        if ($seconds < 60) {
            return ($persian ? self::toPersianDigits($seconds) : $seconds) . ' ' . ($persian ? 'ثانیه' : 'sec');
        }

        $minutes = floor($seconds / 60);
        if ($minutes < 60) {
            return ($persian ? self::toPersianDigits($minutes) : $minutes) . ' ' . ($persian ? 'دقیقه' : 'min');
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        if ($hours < 24) {
            $result = ($persian ? self::toPersianDigits($hours) : $hours) . ' ' . ($persian ? 'ساعت' : 'h');
            if ($remainingMinutes > 0) {
                $result .= ' ' . ($persian ? self::toPersianDigits($remainingMinutes) : $remainingMinutes) . ' ' . ($persian ? 'دقیقه' : 'min');
            }
            return $result;
        }

        $days = floor($hours / 24);
        $remainingHours = $hours % 24;
        $result = ($persian ? self::toPersianDigits($days) : $days) . ' ' . ($persian ? 'روز' : 'd');
        if ($remainingHours > 0) {
            $result .= ' ' . ($persian ? self::toPersianDigits($remainingHours) : $remainingHours) . ' ' . ($persian ? 'ساعت' : 'h');
        }
        return $result;
    }

    // ============================================================
    // فرمت‌دهی متن با Markdown تلگرام
    // ============================================================

    /**
     * تولید متن Bold
     */
    public static function bold(string $text): string
    {
        return "*{$text}*";
    }

    /**
     * تولید متن Italic
     */
    public static function italic(string $text): string
    {
        return "_{$text}_";
    }

    /**
     * تولید متن با underline
     */
    public static function underline(string $text): string
    {
        return "__{$text}__";
    }

    /**
     * تولید متن با strikethrough
     */
    public static function strikethrough(string $text): string
    {
        return "~{$text}~";
    }

    /**
     * تولید متن با فرمت کد (monospace)
     */
    public static function code(string $text): string
    {
        return "`{$text}`";
    }

    /**
     * تولید متن با فرمت کد بلاک (چندخطی)
     */
    public static function codeBlock(string $text, string $language = ''): string
    {
        if (!empty($language)) {
            return "```{$language}\n{$text}\n```";
        }
        return "```\n{$text}\n```";
    }

    /**
     * تولید لینک در Markdown
     */
    public static function link(string $text, string $url): string
    {
        return "[{$text}]({$url})";
    }

    /**
     * تولید متن با فرمت اسپویلر (پنهان)
     */
    public static function spoiler(string $text): string
    {
        return "||{$text}||";
    }

    /**
     * تولید نقل قول (Quote)
     */
    public static function quote(string $text): string
    {
        return "> " . str_replace("\n", "\n> ", $text);
    }

    // ============================================================
    // فرمت‌دهی یوزرنیم و شناسه
    // ============================================================

    /**
     * فرمت‌دهی یوزرنیم (با @ در ابتدا)
     */
    public static function username(?string $username): string
    {
        if (empty($username)) {
            return 'نامشخص';
        }
        return '@' . ltrim($username, '@');
    }

    /**
     * فرمت‌دهی شناسه کاربر (با backtick برای نمایش بهتر)
     */
    public static function userId(int $userId): string
    {
        return "`" . self::toPersianDigits($userId) . "`";
    }

    /**
     * فرمت‌دهی نام کاربر (با یوزرنیم در صورت وجود)
     */
    public static function userDisplay(array $user): string
    {
        $username = $user['username'] ?? null;
        if (!empty($username)) {
            return self::username($username);
        }
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        return !empty($fullName) ? $fullName : 'کاربر ناشناس';
    }

    // ============================================================
    // متدهای کمکی عمومی
    // ============================================================

    /**
     * تولید خط جداکننده (برای زیبایی پیام‌ها)
     */
    public static function divider(string $char = '━', int $length = 30): string
    {
        return str_repeat($char, $length);
    }

    /**
     * تولید Emoji برای وضعیت
     */
    public static function statusEmoji(bool $success): string
    {
        return $success ? '✅' : '❌';
    }

    /**
     * تولید Emoji بر اساس سطح لاگ
     */
    public static function levelEmoji(string $level): string
    {
        return match ($level) {
            'debug' => '🔍',
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'error' => '❌',
            'critical' => '🚨',
            default => '📌',
        };
    }

    /**
     * کوتاه‌سازی متن با سه‌نقطه
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }

    /**
     * پاکسازی متن (حذف تگ‌های HTML و کاراکترهای خاص)
     */
    public static function sanitize(string $text): string
    {
        // حذف تگ‌های HTML
        $text = strip_tags($text);
        // حذف کاراکترهای خاص (اختیاری)
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return $text;
    }
}