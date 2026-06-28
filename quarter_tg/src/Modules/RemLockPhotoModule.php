<?php

namespace Modules;

class RemLockPhotoModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'photo';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل عکس / Unlock photos";
    }
}