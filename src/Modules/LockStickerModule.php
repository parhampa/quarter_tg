<?php

namespace Modules;

class LockStickerModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'sticker';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل استیکر / Lock stickers";
    }
}