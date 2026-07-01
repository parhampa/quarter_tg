<?php

declare(strict_types=1);

namespace QuarterTg\Helpers;

use Throwable;

/**
 * کلاس کمکی برای اعتبارسنجی داده‌ها
 * 
 * مسئولیت‌ها:
 * - اعتبارسنجی شناسه کاربر و گروه
 * - اعتبارسنجی یوزرنیم تلگرام
 * - اعتبارسنجی پیام‌ها و متون
 * - اعتبارسنجی پارامترهای دستورات
 * - اعتبارسنجی ایمیل و URL
 * - اعتبارسنجی تاریخ و زمان
 */
class ValidationHelper
{
    /**
     * اعتبارسنجی شناسه کاربر یا گروه (عددی و مثبت)
     */
    public static function validateId($id): bool
    {
        return is_numeric($id) && (int)$id > 0;
    }

    /**
     * اعتبارسنجی یوزرنیم تلگرام
     * قوانین: ۵ تا ۳۲ کاراکتر، فقط حروف، اعداد و خط زیرین
     */
    public static function validateUsername(string $username): bool
    {
        if (empty($username)) {
            return false;
        }
        // حذف @ از ابتدا
        $username = ltrim($username, '@');
        // طول بین ۵ تا ۳۲ کاراکتر
        if (strlen($username) < 5 || strlen($username) > 32) {
            return false;
        }
        // فقط حروف، اعداد و خط زیرین
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $username);
    }

    /**
     * اعتبارسنجی پیام (طول و محتوا)
     */
    public static function validateMessage(string $message, int $minLength = 1, int $maxLength = 4096): bool
    {
        $length = mb_strlen($message);
        return $length >= $minLength && $length <= $maxLength;
    }

    /**
     * اعتبارسنجی پارامترهای دستور (تعداد و نوع)
     */
    public static function validateCommandParams(string $param, int $minParts = 1, int $maxParts = 10): bool
    {
        if (empty($param) && $minParts > 0) {
            return false;
        }
        $parts = preg_split('/\s+/', trim($param));
        $count = count($parts);
        return $count >= $minParts && $count <= $maxParts;
    }

    /**
     * اعتبارسنجی ایمیل
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * اعتبارسنجی URL (لینک)
     */
    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * اعتبارسنجی عدد صحیح در بازه مشخص
     */
    public static function validateInteger($value, int $min = null, int $max = null): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $int = (int)$value;
        if ($min !== null && $int < $min) {
            return false;
        }
        if ($max !== null && $int > $max) {
            return false;
        }
        return true;
    }

    /**
     * اعتبارسنجی تاریخ با فرمت مشخص
     */
    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime !== false && $dateTime->format($format) === $date;
    }

    /**
     * اعتبارسنجی زمان (ثانیه، دقیقه، ساعت، ...)
     * فرمت‌های پشتیبانی‌شده: 30s, 5m, 2h, 1d, 1w
     */
    public static function validateDuration(string $duration): bool
    {
        return (bool)preg_match('/^(\d+)([smhdw])$/i', $duration);
    }

    /**
     * اعتبارسنجی نوع قفل (آیا در لیست قفل‌ها وجود دارد؟)
     */
    public static function validateLockType(string $lockType, array $allowedLocks): bool
    {
        return in_array($lockType, $allowedLocks, true);
    }

    /**
     * اعتبارسنجی سطح دسترسی (admin, super_admin, ...)
     */
    public static function validateAdminLevel(string $level, array $allowedLevels = ['admin', 'super_admin']): bool
    {
        return in_array($level, $allowedLevels, true);
    }

    /**
     * اعتبارسنجی اینکه آیا رشته فقط شامل حروف فارسی است؟
     */
    public static function validatePersian(string $text): bool
    {
        return (bool)preg_match('/^[\x{0600}-\x{06FF}\s]+$/u', $text);
    }

    /**
     * اعتبارسنجی اینکه آیا رشته فقط شامل حروف انگلیسی است؟
     */
    public static function validateEnglish(string $text): bool
    {
        return (bool)preg_match('/^[a-zA-Z\s]+$/', $text);
    }

    /**
     * اعتبارسنجی اینکه آیا رشته فقط شامل اعداد است؟
     */
    public static function validateNumeric(string $text): bool
    {
        return is_numeric($text);
    }

    /**
     * اعتبارسنجی اینکه آیا رشته شامل کاراکترهای خاص نیست؟
     */
    public static function validateSafeString(string $text): bool
    {
        // فقط حروف، اعداد، فاصله و برخی کاراکترهای مجاز
        return (bool)preg_match('/^[a-zA-Z0-9\x{0600}-\x{06FF}\s\-\._]+$/u', $text);
    }

    /**
     * اعتبارسنجی آرایه (غیر خالی بودن و ساختار صحیح)
     */
    public static function validateArray(array $data, array $requiredKeys = []): bool
    {
        if (empty($data)) {
            return false;
        }
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * اعتبارسنجی اینکه آیا کاربر در گروه است یا خیر
     * (بررسی با API تلگرام - نیاز به TelegramApi)
     */
    public static function validateUserInGroup($telegram, int $chatId, int $userId): bool
    {
        try {
            $member = $telegram->getChatMember($chatId, $userId);
            return isset($member['status']) && in_array($member['status'], ['member', 'administrator', 'creator']);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * اعتبارسنجی پارامترهای دستور `/ban` یا `/mute`
     * فرمت: [@username|user_id] [duration] [reason]
     */
    public static function validateBanParams(string $param): array
    {
        $parts = preg_split('/\s+/', trim($param), 3);
        $result = [
            'valid' => false,
            'target' => null,
            'duration' => null,
            'reason' => null,
            'error' => null,
        ];

        if (empty($param)) {
            $result['error'] = 'پارامترهای دستور خالی است.';
            return $result;
        }

        // پارامتر اول: کاربر هدف (اجباری)
        $target = $parts[0] ?? '';
        if (empty($target)) {
            $result['error'] = 'لطفاً کاربر هدف را مشخص کنید.';
            return $result;
        }

        $result['target'] = $target;

        // پارامتر دوم: زمان (اختیاری)
        if (isset($parts[1]) && self::validateDuration($parts[1])) {
            $result['duration'] = $parts[1];
            $result['reason'] = $parts[2] ?? 'تخلف از قوانین گروه';
        } elseif (isset($parts[1])) {
            // اگر duration نبود، ممکن است دلیل باشد
            $result['reason'] = $parts[1] . (isset($parts[2]) ? ' ' . $parts[2] : '');
        } else {
            $result['reason'] = 'تخلف از قوانین گروه';
        }

        $result['valid'] = true;
        return $result;
    }

    /**
     * اعتبارسنجی پارامترهای دستور `/lock` یا `/unlock`
     * فرمت: [lock_type1] [lock_type2] ...
     */
    public static function validateLockParams(string $param, array $allowedLocks): array
    {
        if (empty($param)) {
            return [
                'valid' => false,
                'locks' => [],
                'invalid' => [],
                'error' => 'نوع قفل مشخص نشده است.',
            ];
        }

        $parts = preg_split('/\s+/', trim($param));
        $valid = [];
        $invalid = [];

        foreach ($parts as $lock) {
            $lock = strtolower(trim($lock));
            if (in_array($lock, $allowedLocks, true)) {
                $valid[] = $lock;
            } else {
                $invalid[] = $lock;
            }
        }

        if (empty($valid)) {
            return [
                'valid' => false,
                'locks' => [],
                'invalid' => $invalid,
                'error' => 'همه قفل‌های مشخص شده نامعتبر هستند.',
            ];
        }

        return [
            'valid' => true,
            'locks' => $valid,
            'invalid' => $invalid,
            'error' => null,
        ];
    }

    /**
     * اعتبارسنجی پارامترهای دستور `/clear`
     * فرمت: [count]
     */
    public static function validateClearParams(string $param, int $maxCount = 100): array
    {
        if (empty($param)) {
            return [
                'valid' => true,
                'count' => 1,
                'error' => null,
            ];
        }

        if (!is_numeric($param) || (int)$param < 1) {
            return [
                'valid' => false,
                'count' => 0,
                'error' => 'تعداد باید یک عدد مثبت باشد.',
            ];
        }

        $count = (int)$param;
        if ($count > $maxCount) {
            return [
                'valid' => false,
                'count' => $count,
                'error' => "حداکثر تعداد مجاز {$maxCount} پیام است.",
            ];
        }

        return [
            'valid' => true,
            'count' => $count,
            'error' => null,
        ];
    }

    /**
     * اعتبارسنجی پارامترهای دستور `/setwelcome` یا `/setrules`
     * فرمت: [text]
     */
    public static function validateTextParams(string $param, int $minLength = 1, int $maxLength = 4096): array
    {
        if (empty($param)) {
            return [
                'valid' => false,
                'text' => '',
                'error' => 'متن نمی‌تواند خالی باشد.',
            ];
        }

        $length = mb_strlen($param);
        if ($length < $minLength) {
            return [
                'valid' => false,
                'text' => $param,
                'error' => "متن حداقل باید {$minLength} کاراکتر باشد.",
            ];
        }

        if ($length > $maxLength) {
            return [
                'valid' => false,
                'text' => $param,
                'error' => "متن حداکثر باید {$maxLength} کاراکتر باشد.",
            ];
        }

        return [
            'valid' => true,
            'text' => $param,
            'error' => null,
        ];
    }

    /**
     * اعتبارسنجی سطح لاگ
     */
    public static function validateLogLevel(string $level): bool
    {
        $allowed = ['debug', 'info', 'warning', 'error', 'critical'];
        return in_array($level, $allowed, true);
    }

    /**
     * اعتبارسنجی فرمت JSON
     */
    public static function validateJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * اعتبارسنجی اینکه آیا رشته شامل تگ‌های HTML است یا خیر
     */
    public static function validateNoHtml(string $text): bool
    {
        return strip_tags($text) === $text;
    }
}