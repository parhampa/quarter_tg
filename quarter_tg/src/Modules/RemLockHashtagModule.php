<?php

namespace Modules;

class RemLockHashtagModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'hashtag';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل هشتگ / Unlock hashtags";
    }
}