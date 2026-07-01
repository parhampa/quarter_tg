<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول حذف پین از گروه
 * فقط ادمین‌ها می‌توانند پین را حذف کنند
 * اگر به پیامی ریپلای شود، فقط آن پیام خاص را آن‌پین می‌کند
 * در غیر این صورت، تمام پین‌ها را حذف می‌کند
 */
class UnpinModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
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

        // فقط ادمین‌ها می‌توانند پین را حذف کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه حذف پین را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی وجود ریپلای (اختیاری)
        $targetMessageId = null;
        if (isset($message['reply_to_message']) && isset($message['reply_to_message']['message_id'])) {
            $targetMessageId = $message['reply_to_message']['message_id'];
        }

        // انجام عملیات حذف پین
        try {
            $result = $this->telegram->unpinChatMessage($chatId, $targetMessageId);

            if ($result && isset($result['result']) && $result['result'] === true) {
                if ($targetMessageId !== null) {
                    $messageText = "🔓 پین پیام مورد نظر با موفقیت حذف شد.";
                } else {
                    $messageText = "🔓 تمام پین‌های گروه با موفقیت حذف شدند.";
                }

                $this->telegram->sendMessage(
                    $chatId,
                    $messageText,
                    $message['message_id'] ?? null
                );

                $this->logger->info("Pin removed in group $chatId by $userId", [
                    'message_id' => $targetMessageId,
                ]);
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ حذف پین با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to unpin message in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // بررسی خطاهای خاص
            if (strpos($errorMessage, 'message_not_found') !== false) {
                $errorMessage = "پیام مورد نظر یافت نشد. ممکن است حذف شده باشد.";
            } elseif (strpos($errorMessage, 'not enough rights') !== false) {
                $errorMessage = "ربات دسترسی کافی برای حذف پین ندارد. لطفاً ربات را ادمین کنید.";
            } elseif (strpos($errorMessage, 'CHAT_ADMIN_REQUIRED') !== false) {
                $errorMessage = "ربات باید ادمین گروه باشد تا بتواند پین را حذف کند.";
            }

            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در حذف پین: " . $errorMessage,
                $message['message_id'] ?? null
            );

            $this->logger->error("Unpin exception: " . $e->getMessage());
        }
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "حذف پین از گروه (ریپلای = حذف پین خاص، بدون ریپلای = حذف تمام پین‌ها) / Unpin message from group";
    }
}