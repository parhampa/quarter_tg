<?php

namespace Modules;

class LockVideoNoteModule extends BaseLockModule
{
    protected function getLockType(): string
    {
        return 'video_note';
    }

    protected function getAction(): bool
    {
        return true;
    }

    public static function getDescription(): string
    {
        return "قفل ویدئو مسیج / Lock video notes";
    }
}