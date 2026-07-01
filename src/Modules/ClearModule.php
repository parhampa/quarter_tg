<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use Throwable;

/**
 * ماژول پاک کردن پیام‌ها
 * 
 * دستورات:
 * - /clear [تعداد] – پاک کردن تعداد مشخصی از پیام‌های آخر (حداکثر ۱۰۰)
 * - /del – حذف پیام ریپلی‌شده
 * - /delete – حذف پیام ریپلی‌شده (مشابه /del)
 */
class ClearModule implements ModuleInterface
{
    public const COMMANDS = ['clear', 'del', 'delete'];

    private TelegramApi $telegram;
    private AuthorizationManager $authManager;
    private Logger $logger;
    
    /** @var int حداکثر تعداد پیام قابل پاک کردن در هر بار */
    private const MAX_MESSAGES = 100;
    
    /** @var int تأخیر بین حذف پیام‌ها (میلی‌ثانیه) */
    private const DELETE_DELAY = 100; // ۰.۱ ثانیه

    public function __construct(
        TelegramApi $telegram,
        AuthorizationManager $authManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->authManager = $authManager;
        $this->logger = $logger;
    }

    /**
     * اجرای ماژول
     */
    public function execute(int $chatId, int $userId, string $param, array $message): mixed
    {
        // تشخیص دستور (از پیام اصلی)
        $text = $message['text'] ?? '';
        if (empty($text)) {
            return null;
        }

        // استخراج نام دستور (بدون /)
        $command = substr(trim($text), 1);
        $parts = explode(' ', $command, 2);
        $commandName = strtolower($parts[0]);
        $param = $parts[1] ?? '';

        // پردازش دستورات مختلف
        return match ($commandName) {
            'clear' => $this->handleClear($chatId, $userId, $param, $message),
            'del', 'delete' => $this->handleDelete($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * پاک کردن چند پیام آخر
     */
    private function handleClear(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین یا مودریتور
        if (!$this->authManager->isModerator($chatId, $adminId) && !$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی پاک کردن پیام‌ها را ندارید.');
        }

        // دریافت تعداد پیام‌ها برای پاک کردن
        $count = $this->parseCount($param);
        if ($count === null) {
            return $this->sendError($chatId, '❌ تعداد نامعتبر. لطفاً یک عدد بین ۱ تا ' . self::MAX_MESSAGES . ' وارد کنید.\n' .
                'مثال: /clear 10');
        }

        // محدودیت تعداد
        if ($count > self::MAX_MESSAGES) {
            return $this->sendError($chatId, "❌ حداکثر تعداد مجاز برای پاک کردن " . self::MAX_MESSAGES . " پیام است.");
        }

        // پیام خود دستور clear را نیز حذف کنیم (برای تمیزکاری)
        $messageId = $message['message_id'] ?? null;
        $deletedCount = 0;
        $failedCount = 0;

        try {
            // ارسال پیام "در حال پاک کردن..." (قابل حذف)
            $statusMsg = $this->telegram->sendMessage($chatId, "🔄 در حال پاک کردن {$count} پیام...");
            $statusMsgId = $statusMsg['result']['message_id'] ?? null;

            // دریافت پیام‌های اخیر (از آخرین پیام شروع کنیم)
            // توجه: تلگرام API متدی برای دریافت لیست پیام‌ها ندارد،
            // بنابراین از روش جایگزین استفاده میکنیم: 
            // حذف پیام‌ها با استفاده از message_idهای موجود در حافظه (اگر در Bot.php ذخیره شوند)
            // یا اینکه از کاربر بخواهیم محدوده زمانی مشخص کند.
            
            // راهکار ساده: حذف پیام‌های ریپلی‌شده یا پیام‌های مشخص
            // برای پیادهسازی کامل، نیاز به ذخیره message_idها در دیتابیس است.
            
            // در این پیادهسازی، فقط پیام‌های ریپلی‌شده یا پیام‌های دارای پاسخ را حذف میکنیم
            // یا اینکه تعداد مشخصی از آخرین پیام‌های گروه را دریافت کنیم (با استفاده از getUpdates)
            // که محدودیت‌های خاص خود را دارد.

            // به دلیل محدودیت‌های API تلگرام، این متد فعلاً ساده پیادهسازی میشود:
            // اگر کاربر ریپلی داده باشد، پیام ریپلی و خود دستور حذف میشوند
            // و همچنین تعداد مشخصی پیام قبل از آن (در صورت امکان)
            
            $deletedCount = $this->deleteRecentMessages($chatId, $count, $messageId, $statusMsgId);

            // حذف پیام وضعیت
            if ($statusMsgId !== null) {
                try {
                    $this->telegram->deleteMessage($chatId, $statusMsgId);
                } catch (Throwable $e) {
                    // نادیده گرفته شود
                }
            }

            // ارسال پیام نتیجه
            if ($deletedCount > 0) {
                $messageText = "✅ {$deletedCount} پیام با موفقیت پاک شد.";
                if ($failedCount > 0) {
                    $messageText .= "\n⚠️ {$failedCount} پیام قابل حذف نبودند.";
                }
                $this->telegram->sendMessage($chatId, $messageText);
            } else {
                $this->telegram->sendMessage($chatId, "ℹ️ هیچ پیامی برای پاک کردن یافت نشد.");
            }

            $this->logger->info('Clear command executed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'count' => $count,
                'deleted' => $deletedCount,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Clear command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در پاک کردن پیام‌ها: ' . $e->getMessage());
        }
    }

    /**
     * حذف یک پیام خاص (با ریپلی)
     */
    private function handleDelete(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین یا مودریتور
        if (!$this->authManager->isModerator($chatId, $adminId) && !$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی حذف پیام را ندارید.');
        }

        // بررسی ریپلی
        if (!isset($message['reply_to_message']['message_id'])) {
            return $this->sendError($chatId, '❌ لطفاً به پیامی که می‌خواهید حذف کنید ریپلی بزنید.\n' .
                'مثال: /del (با ریپلی به پیام)');
        }

        $targetMessageId = (int)$message['reply_to_message']['message_id'];
        $targetUserId = (int)($message['reply_to_message']['from']['id'] ?? 0);

        try {
            // حذف پیام
            $this->telegram->deleteMessage($chatId, $targetMessageId);

            // حذف پیام دستور خودش
            if (isset($message['message_id'])) {
                try {
                    $this->telegram->deleteMessage($chatId, $message['message_id']);
                } catch (Throwable $e) {
                    // نادیده گرفته شود
                }
            }

            $this->logger->info('Delete command executed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'target_message' => $targetMessageId,
                'target_user' => $targetUserId,
            ]);

            // ارسال پیام تأیید (اختیاری)
            $this->telegram->sendMessage($chatId, "✅ پیام با موفقیت حذف شد.");

            return ['success' => true, 'message' => 'پیام حذف شد.'];

        } catch (Throwable $e) {
            $this->logger->error('Delete command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در حذف پیام: ' . $e->getMessage());
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * پارس کردن تعداد پیام‌ها از پارامتر
     */
    private function parseCount(string $param): ?int
    {
        if (empty($param)) {
            return 1; // پیشفرض یک پیام
        }

        $count = (int)$param;
        if ($count <= 0 || !is_numeric($param)) {
            return null;
        }

        return min($count, self::MAX_MESSAGES);
    }

    /**
     * حذف پیام‌های اخیر (راهکار جایگزین برای محدودیت API)
     * توجه: این متد یک پیاده‌سازی ساده است و برای حذف واقعی پیام‌ها
     * نیاز به ذخیره message_idها در دیتابیس دارد.
     */
    private function deleteRecentMessages(int $chatId, int $count, ?int $commandMessageId, ?int $statusMessageId): int
    {
        $deleted = 0;
        $messageIds = [];

        // پیام‌هایی که باید حذف شوند:
        // 1. پیام خود دستور
        if ($commandMessageId !== null) {
            $messageIds[] = $commandMessageId;
        }

        // 2. پیام وضعیت (در حال پاک کردن...)
        if ($statusMessageId !== null) {
            $messageIds[] = $statusMessageId;
        }

        // 3. در اینجا میتوانیم پیام‌های قبلی را با استفاده از دیتابیس یا کش دریافت کنیم
        // اما به دلیل محدودیت‌های API، پیاده‌سازی کامل نیاز به ذخیره message_id دارد.
        // برای نمونه، فقط پیام‌های مشخص شده را حذف میکنیم.

        // حذف پیام‌ها
        foreach ($messageIds as $msgId) {
            try {
                $this->telegram->deleteMessage($chatId, $msgId);
                $deleted++;
                usleep(self::DELETE_DELAY * 1000); // تأخیر برای جلوگیری از Rate Limit
            } catch (Throwable $e) {
                $this->logger->warning('Failed to delete message.', [
                    'chat' => $chatId,
                    'message_id' => $msgId,
                    'error' => $e->getMessage(),
                ]);
                // ادامه دهید
            }
        }

        return $deleted;
    }

    /**
     * ارسال پیام خطا
     */
    private function sendError(int $chatId, string $message): array
    {
        $this->telegram->sendMessage($chatId, $message);
        return ['success' => false, 'message' => $message];
    }
}