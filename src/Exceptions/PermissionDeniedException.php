<?php

namespace QuarterTg\Exceptions;

/**
 * استثنای مربوط به عدم دسترسی کاربر
 */
class PermissionDeniedException extends BotException
{
    private $userId;
    private $groupId;
    private $command;

    /**
     * @param int $userId آیدی کاربر
     * @param int $groupId آیدی گروه
     * @param string $command دستور درخواستی
     * @param string $message پیام خطا
     * @param int $code کد خطا
     * @param \Throwable|null $previous استثنای قبلی
     */
    public function __construct(
        int $userId,
        int $groupId,
        string $command = '',
        string $message = 'Permission denied',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            [
                'user_id' => $userId,
                'group_id' => $groupId,
                'command' => $command,
            ]
        );
        $this->userId = $userId;
        $this->groupId = $groupId;
        $this->command = $command;
    }

    /**
     * دریافت آیدی کاربر
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * دریافت آیدی گروه
     */
    public function getGroupId(): int
    {
        return $this->groupId;
    }

    /**
     * دریافت نام دستور
     */
    public function getCommand(): string
    {
        return $this->command;
    }
}