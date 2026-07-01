<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول کیک کردن کاربران
 * 
 * دستورات:
 * - /kick [@username|user_id] [دلیل] – کیک کردن کاربر از گروه
 * - /kick (با ریپلی به پیام کاربر) – کیک کردن کاربر ریپلی‌شده
 */
class KickModule implements ModuleInterface
{
    public const COMMANDS = ['kick'];

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
            'kick' => $this->handleKick($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * کیک کردن کاربر
     */
    private function handleKick(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین یا مودریتور
        if (!$this->authManager->isModerator($chatId, $adminId) && !$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ شما دسترسی کیک کردن کاربران را ندارید.');
        }

        // استخراج کاربر هدف و دلیل
        $result = $this->parseTargetUserWithReason($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';
        $reason = $result['reason'] ?? 'تخلف از قوانین گروه';

        // جلوگیری از کیک کردن خود کاربر
        if ($targetUserId === $adminId) {
            return $this->sendError($chatId, '⛔ نمی‌توانید خودتان را کیک کنید.');
        }

        // جلوگیری از کیک کردن ادمین‌ها و مالک
        if ($this->authManager->isAdmin($chatId, $targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید ادمین را کیک کنید.');
        }
        if ($this->authManager->isOwner($targetUserId)) {
            return $this->sendError($chatId, '⛔ نمی‌توانید مالک ربات را کیک کنید.');
        }

        try {
            // بررسی اینکه کاربر در گروه وجود دارد یا خیر
            try {
                $member = $this->telegram->getChatMember($chatId, $targetUserId);
                if (!isset($member['status']) || in_array($member['status'], ['left', 'kicked'])) {
                    return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} در گروه نیست.");
                }
            } catch (Throwable $e) {
                // اگر کاربر در گروه نباشد، خطا میدهیم
                return $this->sendError($chatId, "❌ کاربر @{$targetUsername} در گروه یافت نشد.");
            }

            // کیک کردن کاربر (بن و سپس آنبن سریع)
            $this->telegram->banChatMember($chatId, $targetUserId);
            
            // تأخیر کوتاه برای اطمینان از اجرای بن
            usleep(500000); // ۰.۵ ثانیه
            
            // آنبن کردن برای کیک
            $this->telegram->unbanChatMember($chatId, $targetUserId);

            // ثبت در لاگ
            $this->logKick($chatId, $targetUserId, $adminId, $reason);

            $messageText = "👢 کاربر @{$targetUsername} با موفقیت کیک شد.\n📝 دلیل: {$reason}";
            $this->telegram->sendMessage($chatId, $messageText);
            $this->logger->info('User kicked.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'admin' => $adminId,
                'reason' => $reason,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Kick failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در کیک کردن کاربر: ' . $e->getMessage());
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها یا ریپلی (با دلیل)
     */
    private function parseTargetUserWithReason(string $param, array $message): ?array
    {
        if (empty($param)) {
            // اگر کاربر ریپلی داده باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                    'reason' => 'تخلف از قوانین گروه',
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id] [reason...]
        $parts = preg_split('/\s+/', $param, 2);
        $target = $parts[0] ?? '';
        $reason = $parts[1] ?? 'تخلف از قوانین گروه';

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
            // اگر پارامتر اول عدد یا @ نبود، ممکن است کاربر ریپلی داده باشد و پارامتر فقط دلیل باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                $userId = (int)$target['id'];
                $username = $target['username'] ?? null;
                $reason = $param; // کل پارامتر به عنوان دلیل
            } else {
                return null;
            }
        }

        if ($userId === null || $userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'username' => $username,
            'reason' => $reason,
        ];
    }

    /**
     * ثبت در لاگ
     */
    private function logKick(int $chatId, int $userId, int $adminId, string $reason): void
    {
        $this->logger->info('Kick logged.', [
            'chat' => $chatId,
            'user' => $userId,
            'admin' => $adminId,
            'reason' => $reason,
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