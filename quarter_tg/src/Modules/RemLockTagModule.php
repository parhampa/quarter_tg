<?php

namespace Modules;

class RemLockTagModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'tag';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل تگ (منشن) / Unlock tags (mentions)";
    }
}