<?php

namespace QuarterTg\Exceptions;

/**
 * استثنای مربوط به ماژول پیدا نشده
 */
class ModuleNotFoundException extends BotException
{
    private $moduleName;

    /**
     * @param string $moduleName نام ماژول پیدا نشده
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     */
    public function __construct(
        string $moduleName,
        string $message = 'Module not found',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message . ': ' . $moduleName,
            $code,
            $previous,
            ['module_name' => $moduleName]
        );
        $this->moduleName = $moduleName;
    }

    /**
     * دریافت نام ماژول
     */
    public function getModuleName(): string
    {
        return $this->moduleName;
    }
}