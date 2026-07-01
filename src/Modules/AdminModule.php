<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\UserManager;
use Throwable;

/**
 * ماژول مدیریت ادمین‌های گروه
 * 
 * دستورات:
 * - /admins – نمایش لیست ادمین‌های گروه
 * - /setadmin [@username|user_id] [level] – افزودن ادمین جدید (سطح: admin, super_admin)
 * - /removeadmin [@username|user_id] – حذف ادمین
 * - /promote [@username|user_id] – ارتقای سطح دسترسی به super_admin
 * - /demote [@username|user_id] – تنزل سطح دسترسی به admin
 */
class AdminModule implements ModuleInterface
{
    public const COMMANDS = ['admins', 'setadmin', 'removeadmin', 'promote', 'demote'];

    private TelegramApi $telegram;
    private AdminManager $adminManager;
    private AuthorizationManager $authManager;
    private UserManager $userManager;
    private Logger $logger;
    private int $ownerId;

    public function __construct(
        TelegramApi $telegram,
        AdminManager $adminManager,
        AuthorizationManager $authManager,
        UserManager $userManager,
        Logger $logger,
        int $ownerId
    ) {
        $this->telegram = $telegram;
        $this->adminManager = $adminManager;
        $this->authManager = $authManager;
        $this->userManager = $userManager;
        $this->logger = $logger;
        $this->ownerId = $ownerId;
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
            'admins' => $this->handleAdmins($chatId, $userId, $message),
            'setadmin' => $this->handleSetAdmin($chatId, $userId, $param, $message),
            'removeadmin' => $this->handleRemoveAdmin($chatId, $userId, $param, $message),
            'promote' => $this->handlePromote($chatId, $userId, $param, $message),
            'demote' => $this->handleDemote($chatId, $userId, $param, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش لیست ادمین‌ها
     */
    private function handleAdmins(int $chatId, int $userId, array $message): array
    {
        // بررسی دسترسی: همه کاربران میتوانند لیست ادمین‌ها را ببینند
        try {
            $admins = $this->adminManager->getAdmins($chatId);
            
            if (empty($admins)) {
                $messageText = "ℹ️ هیچ ادمینی برای این گروه تعیین نشده است.";
                $this->telegram->sendMessage($chatId, $messageText);
                return ['success' => true, 'message' => $messageText];
            }

            $messageText = "👑 **لیست ادمین‌های گروه**\n";
            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

            foreach ($admins as $admin) {
                $userId = (int)$admin['user_id'];
                $level = $admin['level'] ?? 'admin';
                
                // دریافت اطلاعات کاربر
                $user = $this->userManager->getUser($userId);
                $username = $user['username'] ?? $admin['username'] ?? 'نامشخص';
                $firstName = $user['first_name'] ?? 'کاربر';
                
                // نمایش سطح دسترسی به فارسی
                $levelName = match ($level) {
                    'owner' => '👑 مالک',
                    'super_admin' => '⭐ ادمین ارشد',
                    'admin' => '🔹 ادمین',
                    default => '🔹 ' . $level,
                };

                $messageText .= "{$levelName}: @{$username} (ID: {$userId})\n";
                if ($firstName !== 'کاربر') {
                    $messageText .= "   نام: {$firstName}\n";
                }
                $messageText .= "\n";
            }

            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $messageText .= "📊 تعداد کل: " . count($admins) . " ادمین";

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);
            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Admins command failed.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت لیست ادمین‌ها.');
        }
    }

    /**
     * افزودن ادمین جدید
     */
    private function handleSetAdmin(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند ادمین اضافه کند
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند ادمین جدید اضافه کند.');
        }

        // استخراج کاربر هدف و سطح دسترسی
        $result = $this->parseTargetUserWithLevel($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.\n' .
                'لطفاً با @username یا ID مشخص کنید.\n' .
                'مثال: /setadmin @user admin');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';
        $level = $result['level'] ?? 'admin';

        // اعتبارسنجی سطح دسترسی
        $validLevels = ['admin', 'super_admin'];
        if (!in_array($level, $validLevels, true)) {
            return $this->sendError($chatId, '❌ سطح دسترسی نامعتبر.\n' .
                'سطوح مجاز: admin, super_admin');
        }

        // جلوگیری از افزودن مالک به عنوان ادمین معمولی
        if ($targetUserId === $this->ownerId) {
            return $this->sendError($chatId, '⛔ مالک ربات همیشه ادمین است.');
        }

        try {
            // بررسی اینکه کاربر قبلاً ادمین است یا خیر
            if ($this->adminManager->isAdmin($chatId, $targetUserId)) {
                $currentLevel = $this->adminManager->getAdminLevel($chatId, $targetUserId);
                if ($currentLevel === $level) {
                    return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} هم‌اکنون با سطح {$level} ادمین است.");
                }
                
                // اگر سطح متفاوت است، سطح را به‌روزرسانی کنیم
                $this->adminManager->removeAdmin($chatId, $targetUserId);
                $this->adminManager->addAdmin($chatId, $targetUserId, $level, $adminId);
                $messageText = "✅ سطح دسترسی کاربر @{$targetUsername} به {$level} به‌روزرسانی شد.";
                $this->telegram->sendMessage($chatId, $messageText);
                
                $this->logger->info('Admin level updated.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                    'level' => $level,
                    'admin' => $adminId,
                ]);
                
                return ['success' => true, 'message' => $messageText];
            }

            // افزودن ادمین جدید
            $result = $this->adminManager->addAdmin($chatId, $targetUserId, $level, $adminId);
            
            if ($result) {
                $levelName = $level === 'super_admin' ? 'ادمین ارشد' : 'ادمین';
                $messageText = "✅ کاربر @{$targetUsername} با موفقیت به عنوان {$levelName} اضافه شد.";
                $this->telegram->sendMessage($chatId, $messageText);
                
                $this->logger->info('Admin added.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                    'level' => $level,
                    'admin' => $adminId,
                ]);
                
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در افزودن ادمین.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Set admin command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در افزودن ادمین: ' . $e->getMessage());
        }
    }

    /**
     * حذف ادمین
     */
    private function handleRemoveAdmin(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند ادمین حذف کند
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند ادمین حذف کند.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.\n' .
                'لطفاً با @username یا ID مشخص کنید.\n' .
                'مثال: /removeadmin @user');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        // جلوگیری از حذف مالک
        if ($targetUserId === $this->ownerId) {
            return $this->sendError($chatId, '⛔ نمی‌توانید مالک ربات را حذف کنید.');
        }

        try {
            // بررسی اینکه کاربر ادمین است یا خیر
            if (!$this->adminManager->isAdmin($chatId, $targetUserId)) {
                return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} ادمین نیست.");
            }

            $result = $this->adminManager->removeAdmin($chatId, $targetUserId);
            
            if ($result) {
                $messageText = "✅ کاربر @{$targetUsername} از لیست ادمین‌ها حذف شد.";
                $this->telegram->sendMessage($chatId, $messageText);
                
                $this->logger->info('Admin removed.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                    'admin' => $adminId,
                ]);
                
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در حذف ادمین.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Remove admin command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در حذف ادمین: ' . $e->getMessage());
        }
    }

    /**
     * ارتقای سطح دسترسی ادمین به super_admin
     */
    private function handlePromote(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند ارتقا دهد
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند سطح دسترسی را ارتقا دهد.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.\n' .
                'لطفاً با @username یا ID مشخص کنید.\n' .
                'مثال: /promote @user');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        try {
            // بررسی اینکه کاربر ادمین است یا خیر
            if (!$this->adminManager->isAdmin($chatId, $targetUserId)) {
                return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} ادمین نیست.");
            }

            // بررسی سطح فعلی
            $currentLevel = $this->adminManager->getAdminLevel($chatId, $targetUserId);
            if ($currentLevel === 'super_admin') {
                return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} هم‌اکنون super_admin است.");
            }

            // ارتقا
            $this->adminManager->removeAdmin($chatId, $targetUserId);
            $result = $this->adminManager->addAdmin($chatId, $targetUserId, 'super_admin', $adminId);
            
            if ($result) {
                $messageText = "⭐ کاربر @{$targetUsername} به سطح super_admin ارتقا یافت.";
                $this->telegram->sendMessage($chatId, $messageText);
                
                $this->logger->info('Admin promoted.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                    'admin' => $adminId,
                ]);
                
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در ارتقای سطح دسترسی.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Promote command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در ارتقای سطح دسترسی.');
        }
    }

    /**
     * تنزل سطح دسترسی ادمین به admin
     */
    private function handleDemote(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند تنزل دهد
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند سطح دسترسی را تنزل دهد.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد.\n' .
                'لطفاً با @username یا ID مشخص کنید.\n' .
                'مثال: /demote @user');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        // جلوگیری از تنزل مالک
        if ($targetUserId === $this->ownerId) {
            return $this->sendError($chatId, '⛔ نمی‌توانید مالک ربات را تنزل دهید.');
        }

        try {
            // بررسی اینکه کاربر ادمین است یا خیر
            if (!$this->adminManager->isAdmin($chatId, $targetUserId)) {
                return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} ادمین نیست.");
            }

            // بررسی سطح فعلی
            $currentLevel = $this->adminManager->getAdminLevel($chatId, $targetUserId);
            if ($currentLevel === 'admin') {
                return $this->sendError($chatId, "ℹ️ کاربر @{$targetUsername} هم‌اکنون admin است.");
            }

            // تنزل
            $this->adminManager->removeAdmin($chatId, $targetUserId);
            $result = $this->adminManager->addAdmin($chatId, $targetUserId, 'admin', $adminId);
            
            if ($result) {
                $messageText = "🔽 کاربر @{$targetUsername} به سطح admin تنزل یافت.";
                $this->telegram->sendMessage($chatId, $messageText);
                
                $this->logger->info('Admin demoted.', [
                    'chat' => $chatId,
                    'user' => $targetUserId,
                    'admin' => $adminId,
                ]);
                
                return ['success' => true, 'message' => $messageText];
            } else {
                return $this->sendError($chatId, '❌ خطا در تنزل سطح دسترسی.');
            }

        } catch (Throwable $e) {
            $this->logger->error('Demote command failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در تنزل سطح دسترسی.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * استخراج کاربر هدف از پارامترها (بدون سطح دسترسی)
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
     * استخراج کاربر هدف با سطح دسترسی (برای دستور setadmin)
     */
    private function parseTargetUserWithLevel(string $param, array $message): ?array
    {
        if (empty($param)) {
            // اگر کاربر ریپلی داده باشد، سطح پیشفرض admin
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                    'level' => 'admin',
                ];
            }
            return null;
        }

        // پارامترها: [@username|user_id] [level]
        $parts = preg_split('/\s+/', $param, 2);
        $target = $parts[0] ?? '';
        $level = $parts[1] ?? 'admin';

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
            // اگر پارامتر اول عدد یا @ نبود، ممکن است کاربر ریپلی داده باشد و پارامتر فقط سطح باشد
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                $userId = (int)$target['id'];
                $username = $target['username'] ?? null;
                $level = $param; // کل پارامتر به عنوان سطح
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
            'level' => strtolower(trim($level)),
        ];
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