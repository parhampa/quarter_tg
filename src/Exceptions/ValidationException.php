<?php

namespace QuarterTg\Exceptions;

/**
 * استثنای مربوط به خطاهای اعتبارسنجی
 */
class ValidationException extends BotException
{
    private $errors;

    /**
     * @param array $errors لیست خطاهای اعتبارسنجی
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     */
    public function __construct(
        array $errors = [],
        string $message = 'Validation error occurred',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            ['errors' => $errors]
        );
        $this->errors = $errors;
    }

    /**
     * دریافت لیست خطاها
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * دریافت اولین خطا
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}