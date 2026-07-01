<?php

namespace Modules;

class LockTextModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'text';
    }

    protected function getAction(): bool
    {
        return true; // قفل
    }

    public static function getDescription(): string
    {
        return "قفل پیام متنی / Lock text messages";
    }
}