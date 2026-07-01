<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Helpers\LanguageHelper;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\LockManager;

/**
 * کلاس پایه برای تمام ماژول‌های قفل و رفع قفل
 * ماژول‌های فرزند فقط باید متدهای getLockType() و getAction() را پیاده‌سازی کنند
 * این کلاس از سیستم ترجمه برای نمایش پیام‌ها استفاده می‌کند
 */
abstract class BaseLockModule
{
    protected $telegram;
    protected $db;
    protected $logger;
    protected $langHelper;
    protected $authManager;
    protected $lockManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        LanguageHelper $langHelper,
        AuthorizationManager $authManager,
        LockManager $lockManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->langHelper = $langHelper;
        $this->authManager = $authManager;
        $this->lockManager = $lockManager;
    }

    /**
     * اجرای ماژول
     * @param array $message پیام دریافتی
     * @param string $params پارامترهای دستور
     * @param int $chatId آیدی گروه
     * @param int $userId آیدی کاربر
     */
    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // تشخیص زبان
        $text = $message['text'] ?? '';
        $lang = $this->langHelper->detectLanguageFromCommand($text);

        // فقط ادمین‌ها می‌توانند قفل را تغییر دهند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                $this->langHelper->t('admin_only', [], $lang),
                $message['message_id'] ?? null
            );
            return;
        }

        $lockType = $this->getLockType();
        $action = $this->getAction(); // true = قفل, false = رفع قفل

        // بررسی اینکه نوع قفل معتبر است
        if (!$this->lockManager->isValidLockType($lockType)) {
            $this->telegram->sendMessage(
                $chatId,
                $this->langHelper->t('invalid_input', [], $lang),
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی وضعیت فعلی قفل
        $currentStatus = $this->lockManager->isLocked($chatId, $lockType);
        
        // اگر وضعیت فعلی با وضعیت مورد نظر یکسان است
        if ($currentStatus === $action) {
            $statusText = $action ? 'lock_already_enabled' : 'lock_already_disabled';
            $typeName = $this->getPersianLockName($lockType, $lang);
            $msg = $this->langHelper->t($statusText, ['{type}' => $typeName], $lang);
            $this->telegram->sendMessage(
                $chatId,
                $msg,
                $message['message_id'] ?? null
            );
            return;
        }

        // تغییر وضعیت قفل
        $result = $this->lockManager->toggleLock($chatId, $lockType, $action);

        if ($result) {
            $statusText = $action ? 'lock_enabled' : 'lock_disabled';
            $typeName = $this->getPersianLockName($lockType, $lang);
            $msg = $this->langHelper->t($statusText, ['{type}' => $typeName], $lang);
            
            $this->telegram->sendMessage(
                $chatId,
                $msg,
                $message['message_id'] ?? null
            );

            $this->logger->info("Lock $lockType " . ($action ? 'enabled' : 'disabled') . " in group $chatId by $userId");
        } else {
            $this->telegram->sendMessage(
                $chatId,
                $this->langHelper->t('operation_failed', [], $lang),
                $message['message_id'] ?? null
            );

            $this->logger->error("Failed to toggle lock $lockType in group $chatId by $userId");
        }
    }

    /**
     * دریافت نوع قفل (متن، عکس، ویدئو، ...)
     * باید توسط کلاس فرزند پیاده‌سازی شود
     */
    abstract protected function getLockType(): string;

    /**
     * دریافت اقدام (true = قفل, false = رفع قفل)
     * باید توسط کلاس فرزند پیاده‌سازی شود
     */
    abstract protected function getAction(): bool;

    /**
     * دریافت نام فارسی نوع قفل با استفاده از سیستم ترجمه
     */
    protected function getPersianLockName(string $lockType, string $lang = 'fa'): string
    {
        $names = [
            'text' => $this->langHelper->t('text', [], $lang),
            'photo' => $this->langHelper->t('photo', [], $lang),
            'video' => $this->langHelper->t('video', [], $lang),
            'gif' => $this->langHelper->t('gif', [], $lang),
            'sticker' => $this->langHelper->t('sticker', [], $lang),
            'voice' => $this->langHelper->t('voice', [], $lang),
            'video_note' => $this->langHelper->t('video_note', [], $lang),
            'link' => $this->langHelper->t('link', [], $lang),
            'tag' => $this->langHelper->t('tag', [], $lang),
            'hashtag' => $this->langHelper->t('hashtag', [], $lang),
        ];

        return $names[$lockType] ?? $lockType;
    }

    /**
     * توضیحات ماژول (برای استفاده در ModuleManager)
     */
    public static function getDescription(): string
    {
        return "مدیریت قفل محتوا / Content lock management";
    }
}