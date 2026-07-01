<?php

namespace Modules;

class LockGifModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'gif';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل گیف / Lock GIFs";
    }
}