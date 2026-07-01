<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;
use Throwable;

/**
 * ماژول نمایش اطلاعات کاربر و گروه
 * 
 * دستورات:
 * - /info [@username|user_id] – نمایش اطلاعات کاربر (خود یا کاربر هدف)
 * - /user [@username|user_id] – نمایش اطلاعات کاربر (مشابه /info)
 * - /group – نمایش اطلاعات گروه فعلی
 * - /whoami – نمایش اطلاعات خود کاربر
 */
class InfoModule implements ModuleInterface
{
    public const COMMANDS = ['info', 'user', 'group', 'whoami'];

    private TelegramApi $telegram;
    private UserManager $userManager;
    private AdminManager $adminManager;
    private AuthorizationManager $authManager;
    private WarnManager $warnManager;
    private Logger $logger;

    public function __construct(
        TelegramApi $telegram,
        UserManager $userManager,
        AdminManager $adminManager,
        AuthorizationManager $authManager,
        WarnManager $warnManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->userManager = $userManager;
        $this->adminManager = $adminManager;
        $this->authManager = $authManager;
        $this->warnManager = $warnManager;
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
            'info', 'user' => $this->handleUserInfo($chatId, $userId, $param, $message),
            'whoami' => $this->handleWhoAmI($chatId, $userId, $message),
            'group' => $this->handleGroupInfo($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش اطلاعات یک کاربر
     */
    private function handleUserInfo(int $chatId, int $userId, string $param, array $message): array
    {
        try {
            // استخراج کاربر هدف
            $targetUserId = $userId;
            $targetUser = null;

            if (!empty($param)) {
                $result = $this->parseTargetUser($param, $message);
                if ($result !== null) {
                    $targetUserId = $result['user_id'];
                }
            } elseif (isset($message['reply_to_message']['from']['id'])) {
                // اگر ریپلی داده شده باشد
                $targetUserId = (int)$message['reply_to_message']['from']['id'];
            }

            // دریافت اطلاعات کاربر
            $user = $this->userManager->getUser($targetUserId);
            if ($user === null) {
                // اگر کاربر در دیتابیس نبود، یک رکورد خالی ایجاد کنیم
                $user = [
                    'user_id' => $targetUserId,
                    'first_name' => 'نامشخص',
                    'last_name' => null,
                    'username' => null,
                    'is_bot' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            // دریافت اطلاعات اضافی
            $isAdmin = $this->authManager->isAdmin($chatId, $targetUserId);
            $isOwner = $this->authManager->isOwner($targetUserId);
            $warnCount = $this->warnManager->getWarnCount($chatId, $targetUserId);
            $role = $this->authManager->getRole($chatId, $targetUserId);

            // دریافت اطلاعات از API تلگرام (برای اطلاعات دقیق‌تر)
            $chatMember = null;
            try {
                $chatMember = $this->telegram->getChatMember($chatId, $targetUserId);
            } catch (Throwable $e) {
                // اگر کاربر در گروه نباشد، نادیده گرفته شود
            }

            // تولید پیام اطلاعات
            $messageText = $this->generateUserInfoMessage(
                $user,
                $targetUserId,
                $chatId,
                $isAdmin,
                $isOwner,
                $warnCount,
                $role,
                $chatMember
            );

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);
            
            $this->logger->debug('User info command executed.', [
                'chat' => $chatId,
                'user' => $userId,
                'target' => $targetUserId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('User info command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت اطلاعات کاربر.');
        }
    }

    /**
     * نمایش اطلاعات خود کاربر (whoami)
     */
    private function handleWhoAmI(int $chatId, int $userId, array $message): array
    {
        try {
            // دریافت اطلاعات کاربر
            $user = $this->userManager->getUser($userId);
            if ($user === null) {
                $user = [
                    'user_id' => $userId,
                    'first_name' => 'کاربر',
                    'last_name' => null,
                    'username' => null,
                ];
            }

            // دریافت اطلاعات اضافی
            $isAdmin = $this->authManager->isAdmin($chatId, $userId);
            $isOwner = $this->authManager->isOwner($userId);
            $warnCount = $this->warnManager->getWarnCount($chatId, $userId);
            $role = $this->authManager->getRole($chatId, $userId);

            $messageText = "👤 **اطلاعات شما**\n";
            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
            $messageText .= "🆔 شناسه: `{$userId}`\n";
            $messageText .= "📛 نام: {$user['first_name']}" . ($user['last_name'] ? " {$user['last_name']}" : '') . "\n";
            $messageText .= "👤 یوزرنیم: @" . ($user['username'] ?? 'ندارد') . "\n";
            $messageText .= "🤖 ربات: " . ($user['is_bot'] ? 'بله' : 'خیر') . "\n";
            $messageText .= "👑 نقش: " . $this->getRoleName($role) . "\n";
            $messageText .= "⚠️ اخطارها: {$warnCount}\n";
            $messageText .= "📅 تاریخ عضویت: " . ($user['created_at'] ?? 'نامشخص') . "\n";

            if ($isAdmin) {
                $level = $this->adminManager->getAdminLevel($chatId, $userId);
                $messageText .= "⭐ سطح ادمین: " . ($level === 'super_admin' ? 'ادمین ارشد' : 'ادمین') . "\n";
            }

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Whoami command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت اطلاعات شما.');
        }
    }

    /**
     * نمایش اطلاعات گروه
     */
    private function handleGroupInfo(int $chatId, int $userId, array $message): array
    {
        try {
            // دریافت اطلاعات گروه از API تلگرام
            $chatInfo = $this->telegram->getChat($chatId);

            // دریافت آمار گروه
            $memberCount = $this->userManager->getGroupMemberCount($chatId);
            $adminCount = $this->adminManager->getAdminCount($chatId);
            $totalWarns = $this->warnManager->getGroupStats($chatId)['total_warns'] ?? 0;

            // تولید پیام اطلاعات گروه
            $messageText = "👥 **اطلاعات گروه**\n";
            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
            $messageText .= "📛 نام: " . ($chatInfo['title'] ?? 'نامشخص') . "\n";
            $messageText .= "🆔 شناسه: `{$chatId}`\n";
            $messageText .= "👤 نوع: " . ($chatInfo['type'] ?? 'گروه') . "\n";
            
            if (isset($chatInfo['username'])) {
                $messageText .= "🔗 لینک: @{$chatInfo['username']}\n";
            }
            
            $messageText .= "👥 تعداد اعضا: {$memberCount}\n";
            $messageText .= "🔑 تعداد ادمین‌ها: {$adminCount}\n";
            $messageText .= "⚠️ تعداد کل اخطارها: {$totalWarns}\n";
            $messageText .= "📅 تاریخ ایجاد: " . ($chatInfo['date'] ?? 'نامشخص') . "\n";

            // قفل‌های فعال (اگر LockManager در دسترس باشد)
            // این بخش اختیاری است

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Group info command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت اطلاعات گروه.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها
     */
    private function parseTargetUser(string $param, array $message): ?array
    {
        if (empty($param)) {
            return null;
        }

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
     * تولید پیام اطلاعات کاربر
     */
    private function generateUserInfoMessage(
        array $user,
        int $userId,
        int $chatId,
        bool $isAdmin,
        bool $isOwner,
        int $warnCount,
        string $role,
        ?array $chatMember
    ): string {
        $username = $user['username'] ?? 'ندارد';
        $firstName = $user['first_name'] ?? 'کاربر';
        $lastName = $user['last_name'] ?? '';
        $fullName = $firstName . ($lastName ? " {$lastName}" : '');

        $messageText = "👤 **اطلاعات کاربر**\n";
        $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
        $messageText .= "🆔 شناسه: `{$userId}`\n";
        $messageText .= "📛 نام: {$fullName}\n";
        $messageText .= "👤 یوزرنیم: @" . ($username !== 'ندارد' ? $username : 'ندارد') . "\n";
        $messageText .= "🤖 ربات: " . ($user['is_bot'] ? '✅ بله' : '❌ خیر') . "\n";
        $messageText .= "👑 نقش: " . $this->getRoleName($role) . "\n";
        $messageText .= "⚠️ اخطارها: {$warnCount}\n";

        if ($isAdmin) {
            $level = $this->adminManager->getAdminLevel($chatId, $userId);
            $levelName = $level === 'super_admin' ? '⭐ ادمین ارشد' : '🔹 ادمین';
            $messageText .= "🔑 وضعیت: {$levelName}\n";
        }

        if ($isOwner) {
            $messageText .= "👑 این کاربر **مالک** ربات است.\n";
        }

        // اطلاعات از API تلگرام
        if ($chatMember !== null && isset($chatMember['status'])) {
            $status = match ($chatMember['status']) {
                'creator' => '👑 سازنده گروه',
                'administrator' => '🔑 ادمین',
                'member' => '👤 عضو',
                'restricted' => '🔒 محدود شده',
                'left' => '🚪 خارج شده',
                'kicked' => '🚫 بن شده',
                default => $chatMember['status'],
            };
            $messageText .= "📌 وضعیت در گروه: {$status}\n";

            if (isset($chatMember['joined_date'])) {
                $messageText .= "📅 تاریخ پیوستن: " . date('Y-m-d H:i', $chatMember['joined_date']) . "\n";
            }
        }

        $messageText .= "📅 تاریخ ثبت: " . ($user['created_at'] ?? 'نامشخص') . "\n";
        $messageText .= "🔄 آخرین به‌روزرسانی: " . ($user['updated_at'] ?? 'نامشخص') . "\n";

        return $messageText;
    }

    /**
     * دریافت نام نقش به فارسی
     */
    private function getRoleName(string $role): string
    {
        return match ($role) {
            'owner' => '👑 مالک',
            'super_admin' => '⭐ ادمین ارشد',
            'admin' => '🔹 ادمین',
            'moderator' => '🛡️ مودریتور',
            default => '👤 عضو',
        };
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