<?php

namespace Modules;

class RemLockVideoModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'video';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل فیلم / Unlock videos";
    }
}