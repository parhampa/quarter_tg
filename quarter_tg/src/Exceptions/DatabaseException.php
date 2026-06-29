<?php

namespace QuarterTg\Exceptions;

/**
 * استثنای مربوط به خطاهای دیتابیس
 */
class DatabaseException extends BotException
{
    /**
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     * @param array $context اطلاعات اضافی
     */
    public function __construct(
        string $message = 'Database error occurred',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}