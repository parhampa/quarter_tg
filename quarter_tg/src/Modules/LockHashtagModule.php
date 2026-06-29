<?php

namespace Modules;

/**
 * ماژول قفل هشتگ
 * با فعال‌سازی این قفل، کاربران غیرادمین نمی‌توانند هشتگ ارسال کنند
 * دستورات: /lockhashtag یا قفل هشتگ
 */
class LockHashtagModule extends BaseLockModule
{
    /**
     * نوع قفل را برمی‌گرداند
     */
    protected function getLockType(): string
    {
        return 'hashtag';
    }

    /**
     * اقدام مورد نظر: true = قفل فعال شود
     */
    protected function getAction(): bool
    {
        return true;
    }

    /**
     * توضیحات ماژول برای نمایش در راهنما
     */
    public static function getDescription(): string
    {
        return "قفل هشتگ / Lock hashtags";
    }
}