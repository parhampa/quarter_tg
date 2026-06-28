<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\LockManager;

/**
 * کلاس پایه برای تمام ماژول‌های قفل و رفع قفل
 * ماژول‌های فرزند فقط باید متدهای getLockType() و getAction() را پیاده‌سازی کنند
 */
abstract class BaseLockModule
{
    protected $telegram;
    protected $db;
    protected $logger;
    protected $authManager;
    protected $lockManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        LockManager $lockManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->lockManager = $lockManager;
    }

    /**
     * اجرای ماژول
     */
    public function execute(array $message, string $params = '', int $chatId = 0, int $userId = 0): void
    {
        if ($chatId === 0) {
            $chatId = $message['chat']['id'] ?? 0;
        }
        if ($userId === 0) {
            $userId = $message['from']['id'] ?? 0;
        }

        // فقط ادمین‌ها می‌توانند قفل را تغییر دهند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه تغییر قفل‌ها را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        $lockType = $this->getLockType();
        $action = $this->getAction(); // true = قفل, false = رفع قفل

        // تغییر وضعیت قفل
        $result = $this->lockManager->toggleLock($chatId, $lockType, $action);

        if ($result) {
            $statusText = $action ? 'فعال' : 'غیرفعال';
            $emoji = $action ? '🔒' : '🔓';
            
            // دریافت نام فارسی نوع قفل
            $persianName = $this->getPersianLockName($lockType);
            
            $this->telegram->sendMessage(
                $chatId,
                "{$emoji} قفل {$persianName} با موفقیت {$statusText} شد.",
                $message['message_id'] ?? null
            );

            $this->logger->info("Lock $lockType " . ($action ? 'enabled' : 'disabled') . " in group $chatId by $userId");
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "❌ تغییر وضعیت قفل با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
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
     * دریافت نام فارسی نوع قفل
     */
    protected function getPersianLockName(string $lockType): string
    {
        $names = [
            'text' => 'پیام متنی',
            'photo' => 'عکس',
            'video' => 'فیلم',
            'gif' => 'گیف',
            'sticker' => 'استیکر',
            'voice' => 'ویس',
            'video_note' => 'ویدئو مسیج',
            'link' => 'لینک',
            'tag' => 'تگ',
            'hashtag' => 'هشتگ',
        ];

        return $names[$lockType] ?? $lockType;
    }
}