<?php

namespace Modules;

class LockPhotoModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'photo';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل عکس / Lock photos";
    }
}