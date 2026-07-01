<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول آنمیوت کردن کاربران
 * 
 * دستورات:
 * - /unmute [@username|user_id] – آنمیوت کردن کاربر
 * - /unmute (با ریپلی به پیام کاربر) – آنمیوت کردن کاربر ریپلی‌شده
 */
class UnmuteModule implements ModuleInterface
{
    public const COMMANDS = ['unmute'];

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
            'unmute' => $this->handleUnmute($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * آنمیوت کردن کاربر
     */
    private function handleUnmute(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی آنمیوت کردن کاربران را ندارید.');
        }

        // استخراج کاربر هدف
        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            // بررسی وضعیت کاربر در گروه
            try {
                $member = $this->telegram->getChatMember($chatId, $targetUserId);
                
                // اگر کاربر ادمین یا سازنده باشد، نمی‌توانیم میوتش کنیم
                if (in_array($member['status'] ?? '', ['administrator', 'creator'])) {
                    return $this->sendError($chatId, '⛔ کاربر ادمین یا سازنده گروه است و نمی‌توان آنمیوت کرد.');
                }

                // اگر کاربر قبلاً میوت نبوده باشد
                if (isset($member['status']) && $member['status'] !== 'restricted') {
                    return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} میوت نیست.");
                }

                // اگر کاربر میوت است اما محدودیت‌های ارسال پیام را ندارد
                if (isset($member['can_send_messages']) && $member['can_send_messages'] === true) {
                    return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} میوت نیست (می‌تواند پیام ارسال کند).");
                }
            } catch (Throwable $e) {
                // اگر کاربر در گروه نباشد، خطا میدهیم
                $this->logger->warning('User not found in chat for unmute.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                ]);
                return $this->sendError($chatId, "❌ کاربر @{$targetUsername} در گروه یافت نشد.");
            }

            // تنظیمات دسترسی کامل برای آنمیوت
            $permissions = [
                'can_send_messages' => true,
                'can_send_media_messages' => true,
                'can_send_polls' => true,
                'can_send_other_messages' => true,
                'can_add_web_page_previews' => true,
                'can_change_info' => true,
                'can_invite_users' => true,
                'can_pin_messages' => true,
            ];

            // آنمیوت کردن کاربر در تلگرام
            $this->telegram->restrictChatMember($chatId, $targetUserId, $permissions);

            // ثبت در لاگ
            $this->logUnmute($chatId, $targetUserId, $adminId);

            $messageText = "✅ کاربر @{$targetUsername} با موفقیت آنمیوت شد.";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User unmuted.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Unmute failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در آنمیوت کردن کاربر: ' . $e->getMessage());
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
    private function logUnmute(int $chatId, int $userId, int $adminId): void
    {
        $this->logger->info('Unmute logged.', [
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