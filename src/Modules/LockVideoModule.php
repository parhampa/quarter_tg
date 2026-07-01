<?php

namespace Modules;

class LockVideoModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'video';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل فیلم / Lock videos";
    }
}