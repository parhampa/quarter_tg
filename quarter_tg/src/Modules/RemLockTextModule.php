<?php

namespace Modules;

class RemLockTextModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'text';
    }

    protected function getAction(): bool
    {
        return false; // رفع قفل
    }

    public static function getDescription(): string
    {
        return "رفع قفل پیام متنی / Unlock text messages";
    }
}