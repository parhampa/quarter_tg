<?php

namespace Modules;

class RemLockStickerModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'sticker';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل استیکر / Unlock stickers";
    }
}