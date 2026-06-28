<?php

namespace Modules;

class LockLinkModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'link';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل لینک / Lock links";
    }
}