<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول پین کردن پیام در گروه
 * فقط ادمین‌ها می‌توانند پین کنند
 * نیاز به ریپلای به پیام مورد نظر دارد
 */
class PinModule
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

        // فقط ادمین‌ها می‌توانند پین کنند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه پین کردن پیام را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // بررسی وجود ریپلای
        if (!isset($message['reply_to_message']) || !isset($message['reply_to_message']['message_id'])) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ لطفاً روی پیام مورد نظر ریپلای کنید و سپس دستور `/pin` را ارسال کنید.",
                $message['message_id'] ?? null
            );
            return;
        }

        $targetMessageId = $message['reply_to_message']['message_id'];

        // بررسی پارامترها برای حالت بی‌صدا
        $disableNotification = false;
        if (!empty($params)) {
            $paramsLower = strtolower(trim($params));
            if ($paramsLower === 'silent' || $paramsLower === 'بی‌صدا' || $paramsLower === 's') {
                $disableNotification = true;
            }
        }

        // انجام عملیات پین
        try {
            $result = $this->telegram->pinChatMessage(
                $chatId,
                $targetMessageId,
                $disableNotification
            );

            if ($result && isset($result['result']) && $result['result'] === true) {
                $messageText = "📌 پیام با موفقیت پین شد.";
                if ($disableNotification) {
                    $messageText .= "\n🔇 حالت بی‌صدا فعال است.";
                }

                $this->telegram->sendMessage(
                    $chatId,
                    $messageText,
                    $message['message_id'] ?? null
                );

                $this->logger->info("Message $targetMessageId pinned in group $chatId by $userId", [
                    'silent' => $disableNotification,
                ]);
            } else {
                $this->telegram->sendMessage(
                    $chatId,
                    "❌ پین کردن پیام با خطا مواجه شد. لطفاً دوباره تلاش کنید.",
                    $message['message_id'] ?? null
                );

                $this->logger->error("Failed to pin message $targetMessageId in group $chatId by $userId");
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // بررسی خطاهای خاص
            if (strpos($errorMessage, 'message_not_found') !== false) {
                $errorMessage = "پیام مورد نظر یافت نشد. ممکن است حذف شده باشد.";
            } elseif (strpos($errorMessage, 'not enough rights') !== false) {
                $errorMessage = "ربات دسترسی کافی برای پین کردن پیام ندارد. لطفاً ربات را ادمین کنید.";
            } elseif (strpos($errorMessage, 'CHAT_ADMIN_REQUIRED') !== false) {
                $errorMessage = "ربات باید ادمین گروه باشد تا بتواند پیام را پین کند.";
            }

            $this->telegram->sendMessage(
                $chatId,
                "❌ خطا در پین کردن پیام: " . $errorMessage,
                $message['message_id'] ?? null
            );

            $this->logger->error("Pin exception: " . $e->getMessage());
        }
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "پین کردن پیام در گروه (نیاز به ریپلای) / Pin message in group (requires reply)";
    }
}