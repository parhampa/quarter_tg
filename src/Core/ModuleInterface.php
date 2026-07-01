<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

/**
 * اینترفیس پایه برای همه ماژولهای ربات
 */
interface ModuleInterface
{
    /**
     * متد اصلی اجرای ماژول
     * 
     * @param int $chatId شناسه چت
     * @param int $userId شناسه کاربر اجراکننده
     * @param string $param پارامترهای ارسالشده با دستور
     * @param array $message آرایه کامل پیام دریافتی
     * @return mixed نتیجه اجرا
     */
    public function execute(int $chatId, int $userId, string $param, array $message): mixed;
}