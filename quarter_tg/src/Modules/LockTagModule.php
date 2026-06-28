<?php

namespace Modules;

class LockTagModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'tag';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل تگ (منشن) / Lock tags (mentions)";
    }
}