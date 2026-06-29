<?php

namespace QuarterTg\Exceptions;

/**
 * استثنای مربوط به خطاهای Telegram API
 */
class ApiException extends BotException
{
    private $apiMethod;
    private $apiParams;

    /**
     * @param string $message پیام خطا
     * @param string $apiMethod متد API که خطا داده
     * @param array $apiParams پارامترهای ارسالی
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     */
    public function __construct(
        string $message = 'Telegram API error occurred',
        string $apiMethod = '',
        array $apiParams = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            [
                'api_method' => $apiMethod,
                'api_params' => $apiParams,
            ]
        );
        $this->apiMethod = $apiMethod;
        $this->apiParams = $apiParams;
    }

    /**
     * دریافت متد API
     */
    public function getApiMethod(): string
    {
        return $this->apiMethod;
    }

    /**
     * دریافت پارامترهای API
     */
    public function getApiParams(): array
    {
        return $this->apiParams;
    }
}