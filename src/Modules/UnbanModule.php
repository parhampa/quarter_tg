<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول آنبن کردن کاربران
 * 
 * دستورات:
 * - /unban [@username|user_id] – آنبن کردن کاربر
 * - /unban (با ریپلی به پیام کاربر) – آنبن کردن کاربر ریپلی‌شده
 */
class UnbanModule implements ModuleInterface
{
    public const COMMANDS = ['unban'];

    private TelegramApi $telegram;
    private AuthorizationManager $authManager;
    private UserManager $userManager;
    private Logger $logger;

    public function __construct(
        TelegramApi $telegram,
        AuthorizationManager $authManager,
        UserManager $userManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->authManager = $authManager;
        $this->userManager = $userManager;
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
            'unban' => $this->handleUnban($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * آنبن کردن کاربر
     */
    private function handleUnban(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی آنبن کردن کاربران را ندارید.');
        }

        // استخراج کاربر هدف
        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            // بررسی اینکه کاربر در گروه وجود دارد یا خیر
            // (میتوانیم از getChatMember استفاده کنیم)
            try {
                $member = $this->telegram->getChatMember($chatId, $targetUserId);
                if (isset($member['status']) && $member['status'] !== 'kicked') {
                    return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} بن نیست.");
                }
            } catch (Throwable $e) {
                // اگر کاربر در گروه نباشد، ممکن است قبلاً بن شده باشد
                // پس ادامه میدهیم برای آنبن کردن
                $this->logger->debug('User not found in chat, trying to unban anyway.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                ]);
            }

            // آنبن کردن کاربر در تلگرام
            $this->telegram->unbanChatMember($chatId, $targetUserId);

            // ثبت در لاگ
            $this->logUnban($chatId, $targetUserId, $adminId);

            $messageText = "✅ کاربر @{$targetUsername} با موفقیت آنبن شد.";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User unbanned.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unban failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در آنبن کردن کاربر: ' . $e->getMessage());
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها یا ریپلی
     */
    private function parseTargetUser(string $param, array $message): ?array
    {
        if (empty($param)) {
            // اگر کاربر ریپلی داده باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id]
        $target = trim($param);
        $userId = null;
        $username = null;

        // اگر با @ شروع شود
        if (strpos($target, '@') === 0) {
            $username = ltrim($target, '@');
            $user = $this->userManager->searchByUsername($username);
            if ($user !== null) {
                $userId = (int)$user['user_id'];
            } else {
                return null;
            }
        } elseif (is_numeric($target)) {
            $userId = (int)$target;
            $user = $this->userManager->getUser($userId);
            if ($user !== null) {
                $username = $user['username'] ?? null;
            }
        } else {
            return null;
        }

        if ($userId === null || $userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'username' => $username,
        ];
    }

    /**
     * ثبت در لاگ
     */
    private function logUnban(int $chatId, int $userId, int $adminId): void
    {
        $this->logger->info('Unban logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
        ]);
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