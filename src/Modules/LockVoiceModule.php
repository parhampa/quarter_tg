<?php

namespace Modules;

class LockVoiceModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'voice';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل ویس / Lock voice messages";
    }
}