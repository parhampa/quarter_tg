<?php

namespace QuarterTg\Exceptions;

/**
 * کلاس پایه استثناهای ربات
 * تمام استثناهای سفارشی از این کلاس ارث‌بری می‌کنند
 */
class BotException extends \Exception
{
    protected $context = [];

    /**
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     * @param array $context اطلاعات اضافی
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * دریافت اطلاعات اضافی
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * دریافت نمایش آرایه‌ای از استثنا
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->getContext(),
            'trace' => $this->getTrace(),
        ];
    }
}