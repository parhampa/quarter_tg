<?php
namespace Helpers;

class LanguageHelper
{
    public static function isPersianText(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        return preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text) === 1;
    }

    public static function getLanguageFromCommand(string $command): string
    {
        $persianCommands = [
            'ست ادمین',
            'حذف ادمین',
            'لیست ادمین‌ها',
            'خوش آمد بگو',
            'حذف خوش آمدگویی',
            'پین',
            'حذف پین',
            'آیدی',
            'حذف',
            'پاکسازی',
            'قفل پیام',
            'حذف قفل پیام',
            'قفل استیکر',
            'حذف قفل استیکر',
            'قفل عکس',
            'حذف قفل عکس',
            'قفل فیلم',
            'حذف قفل فیلم',
            'قفل گیف',
            'حذف قفل گیف',
            'قفل ویس',
            'حذف قفل ویس',
            'قفل ویدئو مسیج',
            'حذف قفل ویدئو مسیج',
            'بن',
            'حذف بن',
            'لیست بن‌ها',
        ];
        if (in_array($command, $persianCommands)) {
            return 'fa';
        }
        return 'en';
    }
}