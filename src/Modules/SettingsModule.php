<?php

declare(strict_types=1);

namespace QuarterTg\Modules;

use QuarterTg\Core\Cache;
use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Core\TelegramApi;
use QuarterTg\Managers\AuthorizationManager;
use Throwable;

/**
 * ماژول مدیریت تنظیمات گروه
 * 
 * دستورات:
 * - /settings – نمایش تنظیمات فعلی گروه
 * - /setwelcome [متن] – تنظیم پیام خوش‌آمدگویی
 * - /setrules [متن] – تنظیم قوانین گروه
 * - /removewelcome – حذف پیام خوش‌آمدگویی
 * - /removerules – حذف قوانین گروه
 */
class SettingsModule implements ModuleInterface
{
    public const COMMANDS = [
        'settings', 'setwelcome', 'setrules',
        'removewelcome', 'removerules'
    ];

    private TelegramApi $telegram;
    private Database $database;
    private Cache $cache;
    private AuthorizationManager $authManager;
    private Logger $logger;

    public function __construct(
        TelegramApi $telegram,
        Database $database,
        Cache $cache,
        AuthorizationManager $authManager,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->database = $database;
        $this->cache = $cache;
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
            'settings' => $this->handleSettings($chatId, $userId, $message),
            'setwelcome' => $this->handleSetWelcome($chatId, $userId, $param, $message),
            'setrules' => $this->handleSetRules($chatId, $userId, $param, $message),
            'removewelcome' => $this->handleRemoveWelcome($chatId, $userId, $message),
            'removerules' => $this->handleRemoveRules($chatId, $userId, $message),
            default => null,
        };
    }

    // ============================================================
    // هندلر دستورات
    // ============================================================

    /**
     * نمایش تنظیمات فعلی گروه
     */
    private function handleSettings(int $chatId, int $userId, array $message): array
    {
        // همه کاربران میتوانند تنظیمات را ببینند
        try {
            $settings = $this->getGroupSettings($chatId);

            $messageText = "⚙️ **تنظیمات گروه**\n";
            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

            // پیام خوش‌آمدگویی
            if (!empty($settings['welcome_message'])) {
                $messageText .= "📝 **پیام خوش‌آمدگویی:**\n";
                $messageText .= "{$settings['welcome_message']}\n\n";
            } else {
                $messageText .= "❌ پیام خوش‌آمدگویی تنظیم نشده است.\n\n";
            }

            // قوانین گروه
            if (!empty($settings['rules'])) {
                $messageText .= "📋 **قوانین گروه:**\n";
                $messageText .= "{$settings['rules']}\n\n";
            } else {
                $messageText .= "❌ قوانین گروه تنظیم نشده است.\n\n";
            }

            $messageText .= "━━━━━━━━━━━━━━━━━━━━━\n";
            $messageText .= "💡 برای تغییر تنظیمات از دستورات زیر استفاده کنید:\n";
            $messageText .= "/setwelcome [متن] – تنظیم پیام خوش‌آمدگویی\n";
            $messageText .= "/setrules [متن] – تنظیم قوانین گروه\n";
            $messageText .= "/removewelcome – حذف پیام خوش‌آمدگویی\n";
            $messageText .= "/removerules – حذف قوانین گروه";

            $this->telegram->sendMessage($chatId, $messageText, ['parse_mode' => 'Markdown']);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Settings command failed.', [
                'chat' => $chatId,
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در دریافت تنظیمات گروه.');
        }
    }

    /**
     * تنظیم پیام خوش‌آمدگویی
     */
    private function handleSetWelcome(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند پیام خوش‌آمدگویی را تنظیم کنند.');
        }

        if (empty($param)) {
            return $this->sendError($chatId, '❌ لطفاً متن پیام خوش‌آمدگویی را وارد کنید.\n' .
                'مثال: /setwelcome به گروه خوش آمدید!');
        }

        try {
            // ذخیره در دیتابیس
            $result = $this->database->insert('group_settings', [
                'group_id' => $chatId,
                'setting_key' => 'welcome_message',
                'setting_value' => $param,
                'updated_by' => $adminId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($result === false) {
                // اگر قبلاً وجود داشت، به‌روزرسانی
                $this->database->update(
                    'group_settings',
                    [
                        'setting_value' => $param,
                        'updated_by' => $adminId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    ['group_id' => $chatId, 'setting_key' => 'welcome_message']
                );
            }

            // پاک کردن کش
            $this->cache->delete("group_settings_{$chatId}");

            $messageText = "✅ پیام خوش‌آمدگویی با موفقیت تنظیم شد.\n\n📝 متن جدید:\n{$param}";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Welcome message set.', [
                'chat' => $chatId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Set welcome failed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در تنظیم پیام خوش‌آمدگویی.');
        }
    }

    /**
     * تنظیم قوانین گروه
     */
    private function handleSetRules(int $chatId, int $adminId, string $param, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قوانین گروه را تنظیم کنند.');
        }

        if (empty($param)) {
            return $this->sendError($chatId, '❌ لطفاً متن قوانین گروه را وارد کنید.\n' .
                'مثال: /setrules ۱. احترام به همه اعضا\n۲. بدون اسپم');
        }

        try {
            // ذخیره در دیتابیس
            $result = $this->database->insert('group_settings', [
                'group_id' => $chatId,
                'setting_key' => 'rules',
                'setting_value' => $param,
                'updated_by' => $adminId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($result === false) {
                // اگر قبلاً وجود داشت، به‌روزرسانی
                $this->database->update(
                    'group_settings',
                    [
                        'setting_value' => $param,
                        'updated_by' => $adminId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    ['group_id' => $chatId, 'setting_key' => 'rules']
                );
            }

            // پاک کردن کش
            $this->cache->delete("group_settings_{$chatId}");

            $messageText = "✅ قوانین گروه با موفقیت تنظیم شد.\n\n📋 متن جدید:\n{$param}";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Rules set.', [
                'chat' => $chatId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Set rules failed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در تنظیم قوانین گروه.');
        }
    }

    /**
     * حذف پیام خوش‌آمدگویی
     */
    private function handleRemoveWelcome(int $chatId, int $adminId, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند پیام خوش‌آمدگویی را حذف کنند.');
        }

        try {
            $this->database->delete(
                'group_settings',
                ['group_id' => $chatId, 'setting_key' => 'welcome_message']
            );

            // پاک کردن کش
            $this->cache->delete("group_settings_{$chatId}");

            $messageText = "✅ پیام خوش‌آمدگویی با موفقیت حذف شد.";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Welcome message removed.', [
                'chat' => $chatId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Remove welcome failed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در حذف پیام خوش‌آمدگویی.');
        }
    }

    /**
     * حذف قوانین گروه
     */
    private function handleRemoveRules(int $chatId, int $adminId, array $message): array
    {
        // بررسی دسترسی ادمین
        if (!$this->authManager->isAdmin($chatId, $adminId)) {
            return $this->sendError($chatId, '⛔ فقط ادمین‌ها میتوانند قوانین گروه را حذف کنند.');
        }

        try {
            $this->database->delete(
                'group_settings',
                ['group_id' => $chatId, 'setting_key' => 'rules']
            );

            // پاک کردن کش
            $this->cache->delete("group_settings_{$chatId}");

            $messageText = "✅ قوانین گروه با موفقیت حذف شد.";
            $this->telegram->sendMessage($chatId, $messageText);

            $this->logger->info('Rules removed.', [
                'chat' => $chatId,
                'admin' => $adminId,
            ]);

            return ['success' => true, 'message' => $messageText];

        } catch (Throwable $e) {
            $this->logger->error('Remove rules failed.', [
                'chat' => $chatId,
                'admin' => $adminId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در حذف قوانین گروه.');
        }
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * دریافت تنظیمات گروه از دیتابیس (با کش)
     */
    private function getGroupSettings(int $chatId): array
    {
        $cacheKey = "group_settings_{$chatId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        try {
            $settings = $this->database->query(
                'SELECT setting_key, setting_value FROM group_settings WHERE group_id = ?',
                [$chatId]
            );

            $result = [];
            if (is_array($settings)) {
                foreach ($settings as $setting) {
                    $result[$setting['setting_key']] = $setting['setting_value'];
                }
            }

            // ذخیره در کش (۱۰ دقیقه)
            $this->cache->set($cacheKey, $result, 600);

            return $result;

        } catch (Throwable $e) {
            $this->logger->error('Failed to get group settings.', [
                'chat' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
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