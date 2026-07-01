<?php

namespace Modules;

class RemLockVoiceModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'voice';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل ویس / Unlock voice messages";
    }
}