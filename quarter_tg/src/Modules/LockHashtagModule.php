<?php

namespace Modules;

class LockHashtagModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'hashtag';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل هشتگ / Lock hashtags";
    }
}