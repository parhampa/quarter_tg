<?php

declare(strict_types=1);

namespace QuarterTg\Exceptions;

use Throwable;

/**
 * کلاس پایه برای همه استثناهای سفارشی پروژه
 * 
 * ویژگی‌ها:
 * - پیام خطا با قابلیت Context
 * - کد خطا (Error Code)
 * - اطلاعات اضافی (Context) برای دیباگ بهتر
 */
class BaseException extends \RuntimeException
{
    /** @var array اطلاعات اضافی برای دیباگ */
    protected array $context = [];

    /**
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param array $context اطلاعات اضافی
     * @param Throwable|null $previous استثنای قبلی
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * دریافت اطلاعات اضافی (Context)
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * افزودن اطلاعات به Context
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * دریافت پیام کامل خطا (همراه با Context به صورت JSON)
     */
    public function getFullMessage(): string
    {
        $message = $this->getMessage();
        if (!empty($this->context)) {
            $contextJson = json_encode($this->context, JSON_UNESCAPED_UNICODE);
            $message .= " Context: {$contextJson}";
        }
        return $message;
    }

    /**
     * تبدیل استثنا به آرایه (برای لاگ یا پاسخ API)
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}