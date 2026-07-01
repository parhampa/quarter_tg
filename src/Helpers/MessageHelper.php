<?php

declare(strict_types=1);

namespace QuarterTg\Helpers;

use Throwable;

/**
 * کلاس کمکی برای پردازش پیام‌های تلگرام
 * 
 * مسئولیت‌ها:
 * - استخراج اطلاعات از پیام‌ها (کاربر، چت، متن، ریپلی)
 * - تشخیص دستورات (با پشتیبانی از فارسی و انگلیسی)
 * - استخراج کاربر هدف از ریپلی یا متن
 * - اعتبارسنجی پیام‌ها
 * - فرمت‌دهی و پاکسازی متون
 */
class MessageHelper
{
    /**
     * استخراج اطلاعات کاربر از پیام
     */
    public static function getUserFromMessage(array $message): ?array
    {
        if (!isset($message['from']) || !is_array($message['from'])) {
            return null;
        }

        return [
            'id' => (int)($message['from']['id'] ?? 0),
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? null,
            'username' => $message['from']['username'] ?? null,
            'language_code' => $message['from']['language_code'] ?? null,
            'is_bot' => (bool)($message['from']['is_bot'] ?? false),
        ];
    }

    /**
     * استخراج اطلاعات چت از پیام
     */
    public static function getChatFromMessage(array $message): ?array
    {
        if (!isset($message['chat']) || !is_array($message['chat'])) {
            return null;
        }

        return [
            'id' => (int)($message['chat']['id'] ?? 0),
            'type' => $message['chat']['type'] ?? 'unknown',
            'title' => $message['chat']['title'] ?? null,
            'username' => $message['chat']['username'] ?? null,
        ];
    }

    /**
     * استخراج متن پیام (با پاکسازی)
     */
    public static function getText(array $message): string
    {
        $text = $message['text'] ?? $message['caption'] ?? '';
        return self::cleanText($text);
    }

    /**
     * پاکسازی متن (حذف فضاهای اضافی، خطوط خالی و ...)
     */
    public static function cleanText(string $text): string
    {
        // حذف فضاهای اضافی از ابتدا و انتها
        $text = trim($text);
        
        // حذف فضاهای اضافی بین کلمات
        $text = preg_replace('/\s+/', ' ', $text);
        
        // حذف خطوط خالی
        $text = preg_replace("/\n\s*\n/", "\n", $text);
        
        return trim($text);
    }

    /**
     * تشخیص اینکه آیا پیام یک دستور است یا خیر
     */
    public static function isCommand(array $message): bool
    {
        $text = self::getText($message);
        return !empty($text) && (strpos($text, '/') === 0 || strpos($text, '.') === 0);
    }

    /**
     * تشخیص اینکه آیا پیام یک دستور فارسی است یا خیر
     */
    public static function isPersianCommand(array $message): bool
    {
        $text = self::getText($message);
        if (empty($text)) {
            return false;
        }
        // کاراکترهای فارسی: از \x{0600} تا \x{06FF}
        return preg_match('/^[\/\.][\x{0600}-\x{06FF}\w]+/u', $text) === 1;
    }

    /**
     * استخراج نام دستور از متن پیام (بدون /)
     */
    public static function getCommandName(array $message): ?string
    {
        $text = self::getText($message);
        if (empty($text) || !self::isCommand($message)) {
            return null;
        }

        // حذف / یا . از ابتدا
        $command = substr($text, 1);
        $parts = explode(' ', $command, 2);
        return strtolower(trim($parts[0]));
    }

    /**
     * استخراج پارامترهای دستور (بعد از نام دستور)
     */
    public static function getCommandParams(array $message): string
    {
        $text = self::getText($message);
        if (empty($text) || !self::isCommand($message)) {
            return '';
        }

        // حذف / یا . از ابتدا
        $command = substr($text, 1);
        $parts = explode(' ', $command, 2);
        return $parts[1] ?? '';
    }

    /**
     * استخراج پیام ریپلی‌شده (اگر وجود داشته باشد)
     */
    public static function getReplyMessage(array $message): ?array
    {
        return $message['reply_to_message'] ?? null;
    }

    /**
     * استخراج کاربر هدف از ریپلی (اگر وجود داشته باشد)
     */
    public static function getReplyUser(array $message): ?array
    {
        $reply = self::getReplyMessage($message);
        if ($reply === null) {
            return null;
        }
        return self::getUserFromMessage($reply);
    }

    /**
     * استخراج کاربر هدف از متن (با @username یا ID)
     */
    public static function extractTargetUser(string $text): ?array
    {
        if (empty($text)) {
            return null;
        }

        // اگر با @ شروع شود
        if (strpos($text, '@') === 0) {
            $username = ltrim($text, '@');
            return [
                'type' => 'username',
                'value' => $username,
            ];
        }

        // اگر عدد باشد (ID)
        if (is_numeric($text)) {
            return [
                'type' => 'id',
                'value' => (int)$text,
            ];
        }

        return null;
    }

    /**
     * استخراج کاربر هدف از پیام (با اولویت: ریپلی > متن)
     */
    public static function getTargetUser(array $message): ?array
    {
        // اولویت اول: ریپلی
        $replyUser = self::getReplyUser($message);
        if ($replyUser !== null) {
            return $replyUser;
        }

        // اولویت دوم: پارامترهای دستور
        $params = self::getCommandParams($message);
        if (!empty($params)) {
            $extracted = self::extractTargetUser($params);
            if ($extracted !== null) {
                return [
                    'id' => $extracted['type'] === 'id' ? $extracted['value'] : 0,
                    'username' => $extracted['type'] === 'username' ? $extracted['value'] : null,
                    'type' => $extracted['type'],
                ];
            }
        }

        return null;
    }

    /**
     * دریافت شناسه کاربر هدف از پیام
     */
    public static function getTargetUserId(array $message): ?int
    {
        $target = self::getTargetUser($message);
        if ($target === null) {
            return null;
        }

        // اگر ID باشد
        if (isset($target['id']) && $target['id'] > 0) {
            return $target['id'];
        }

        // اگر فقط username باشد (نیاز به جستجو در دیتابیس دارد)
        // اینجا فقط ID را برمیگردانیم، جستجو در دیتابیس باید توسط UserManager انجام شود
        return null;
    }

    /**
     * بررسی اینکه پیام حاوی لینک است یا خیر
     */
    public static function hasLink(string $text): bool
    {
        return (bool)preg_match('/https?:\/\/[^\s]+/', $text);
    }

    /**
     * بررسی اینکه پیام حاوی منشن (tag) است یا خیر
     */
    public static function hasTag(string $text): bool
    {
        return (bool)preg_match('/@[\w_]+/', $text);
    }

    /**
     * بررسی اینکه پیام حاوی هشتگ است یا خیر
     */
    public static function hasHashtag(string $text): bool
    {
        return (bool)preg_match('/#[\w\x{0600}-\x{06FF}]+/u', $text);
    }

    /**
     * بررسی اینکه پیام حاوی متن عربی است یا خیر
     */
    public static function hasArabic(string $text): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}]+/u', $text);
    }

    /**
     * بررسی اینکه پیام حاوی متن انگلیسی است یا خیر
     */
    public static function hasEnglish(string $text): bool
    {
        return (bool)preg_match('/[A-Za-z]+/', $text);
    }

    /**
     * بررسی اینکه پیام حاوی متن فارسی است یا خیر
     */
    public static function hasPersian(string $text): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}]+/u', $text);
    }

    /**
     * بررسی اینکه پیام اسپم است یا خیر (ساده)
     */
    public static function isSpam(string $text): bool
    {
        // بررسی تعداد کلمات
        $words = explode(' ', $text);
        if (count($words) > 50) {
            return true;
        }

        // بررسی تعداد لینک‌ها
        if (preg_match_all('/https?:\/\/[^\s]+/', $text, $matches) > 5) {
            return true;
        }

        // بررسی تکرار کاراکترها
        if (preg_match('/(.)\1{10,}/', $text)) {
            return true;
        }

        return false;
    }

    /**
     * دریافت شناسه پیام
     */
    public static function getMessageId(array $message): int
    {
        return (int)($message['message_id'] ?? 0);
    }

    /**
     * دریافت شناسه پیام ریپلی‌شده
     */
    public static function getReplyMessageId(array $message): ?int
    {
        $reply = self::getReplyMessage($message);
        if ($reply === null) {
            return null;
        }
        return (int)($reply['message_id'] ?? 0);
    }

    /**
     * بررسی اینکه پیام از یک کاربر خاص است یا خیر
     */
    public static function isFromUser(array $message, int $userId): bool
    {
        $user = self::getUserFromMessage($message);
        return $user !== null && $user['id'] === $userId;
    }

    /**
     * بررسی اینکه پیام در یک چت خاص است یا خیر
     */
    public static function isInChat(array $message, int $chatId): bool
    {
        $chat = self::getChatFromMessage($message);
        return $chat !== null && $chat['id'] === $chatId;
    }

    /**
     * دریافت نوع پیام (text, photo, video, ...)
     */
    public static function getMessageType(array $message): string
    {
        $types = ['text', 'photo', 'video', 'audio', 'document', 'voice', 'sticker', 'animation', 'location', 'contact'];
        foreach ($types as $type) {
            if (isset($message[$type])) {
                return $type;
            }
        }
        return 'unknown';
    }

    /**
     * تشخیص اینکه آیا پیام حاوی رسانه است یا خیر
     */
    public static function hasMedia(array $message): bool
    {
        $mediaTypes = ['photo', 'video', 'audio', 'document', 'voice', 'sticker', 'animation'];
        foreach ($mediaTypes as $type) {
            if (isset($message[$type])) {
                return true;
            }
        }
        return false;
    }

    /**
     * تشخیص اینکه آیا پیام حاوی متن است یا خیر
     */
    public static function hasText(array $message): bool
    {
        return isset($message['text']) || isset($message['caption']);
    }

    /**
     * تشخیص اینکه پیام در گروه است یا خیر
     */
    public static function isGroupMessage(array $message): bool
    {
        $chat = self::getChatFromMessage($message);
        if ($chat === null) {
            return false;
        }
        return in_array($chat['type'], ['group', 'supergroup']);
    }

    /**
     * تشخیص اینکه پیام در کانال است یا خیر
     */
    public static function isChannelMessage(array $message): bool
    {
        $chat = self::getChatFromMessage($message);
        if ($chat === null) {
            return false;
        }
        return $chat['type'] === 'channel';
    }

    /**
     * تشخیص اینکه پیام در پیوی (Private) است یا خیر
     */
    public static function isPrivateMessage(array $message): bool
    {
        $chat = self::getChatFromMessage($message);
        if ($chat === null) {
            return false;
        }
        return $chat['type'] === 'private';
    }

    /**
     * دریافت نام کامل کاربر (first_name + last_name)
     */
    public static function getFullName(array $user): string
    {
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        return !empty($fullName) ? $fullName : 'کاربر';
    }

    /**
     * دریافت نمایش کاربر (با اولویت: username > full_name > id)
     */
    public static function getUserDisplay(array $user): string
    {
        if (!empty($user['username'])) {
            return '@' . $user['username'];
        }
        
        $fullName = self::getFullName($user);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        return 'ID: ' . ($user['id'] ?? 'نامشخص');
    }

    /**
     * تبدیل شناسه کاربر به رشته (برای نمایش در لاگ)
     */
    public static function userIdToString(int $userId): string
    {
        return (string)$userId;
    }

    /**
     * بررسی اینکه یک آرایه پیام معتبر است یا خیر
     */
    public static function isValidMessage(array $message): bool
    {
        return isset($message['message_id']) && isset($message['chat']) && isset($message['from']);
    }
}