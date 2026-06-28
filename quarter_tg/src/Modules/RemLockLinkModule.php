<?php

namespace Modules;

class RemLockLinkModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'link';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل لینک / Unlock links";
    }
}