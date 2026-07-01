<?php

namespace Modules;

class RemLockVideoNoteModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'video_note';
    }

    protected function getAction(): bool
    {
        return false;
    }

    public static function getDescription(): string
    {
        return "رفع قفل ویدئو مسیج / Unlock video notes";
    }
}