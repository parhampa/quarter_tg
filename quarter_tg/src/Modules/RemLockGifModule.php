<?php

namespace Modules;

class RemLockGifModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'gif';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل گیف / Unlock GIFs";
    }
}