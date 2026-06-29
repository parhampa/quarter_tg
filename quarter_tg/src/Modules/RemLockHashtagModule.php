<?php

namespace Modules;

/**
 * ماژول رفع قفل هشتگ
 * با غیرفعال‌سازی این قفل، کاربران می‌توانند هشتگ ارسال کنند
 * دستورات: /remlockhashtag یا رفع قفل هشتگ
 */
class RemLockHashtagModule extends BaseLockModule
{
    /**
     * نوع قفل را برمی‌گرداند
     */
    protected function getLockType(): string
    {
        return 'hashtag';
    }

    /**
     * اقدام مورد نظر: false = قفل غیرفعال شود
     */
    protected function getAction(): bool
    {
        return false;
    }

    /**
     * توضیحات ماژول برای نمایش در راهنما
     */
    public static function getDescription(): string
    {
        return "رفع قفل هشتگ / Unlock hashtags";
    }
}