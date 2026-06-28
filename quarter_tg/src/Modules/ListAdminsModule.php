<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;
use QuarterTg\Core\AdminManager;

/**
 * ماژول نمایش لیست ادمین‌های گروه
 * شامل مالک اصلی، ادمین‌های اصلی و ساب‌ادمین‌ها
 * فقط ادمین‌ها می‌توانند لیست را مشاهده کنند
 */
class ListAdminsModule
{
    private $telegram;
    private $db;
    private $logger;
    private $authManager;
    private $adminManager;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger,
        AuthorizationManager $authManager,
        AdminManager $adminManager
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
        $this->authManager = $authManager;
        $this->adminManager = $adminManager;
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

        // فقط ادمین‌ها می‌توانند لیست را ببینند
        if (!$this->authManager->isAdmin($chatId, $userId)) {
            $this->telegram->sendMessage(
                $chatId,
                "⛔ شما اجازه دسترسی به لیست ادمین‌ها را ندارید.",
                $message['message_id'] ?? null
            );
            return;
        }

        // دریافت لیست تمام مدیران
        $admins = $this->adminManager->getAllAdmins($chatId, $this->authManager->getOwnerId());

        if (empty($admins)) {
            $this->telegram->sendMessage(
                $chatId,
                "📋 لیست ادمین‌ها خالی است.",
                $message['message_id'] ?? null
            );
            return;
        }

        // ساخت متن لیست
        $listText = $this->formatAdminList($admins);

        $this->telegram->sendMessage(
            $chatId,
            $listText,
            $message['message_id'] ?? null,
            'HTML'
        );

        // لاگ
        $this->logger->info("Admin list shown to user $userId in group $chatId");
    }

    /**
     * فرمت کردن لیست ادمین‌ها
     */
    private function formatAdminList(array $admins): string
    {
        $text = "📋 <b>لیست ادمین‌های گروه</b>\n";
        $text .= str_repeat('━', 30) . "\n\n";

        $levels = [
            'owner' => '👑 مالک',
            'admin' => '🔹 ادمین اصلی',
            'subadmin' => '🔸 ساب‌ادمین',
        ];

        $levelIcons = [
            'owner' => '👑',
            'admin' => '🔹',
            'subadmin' => '🔸',
        ];

        // دسته‌بندی بر اساس سطح
        $grouped = [];
        foreach ($admins as $admin) {
            $level = $admin['level'] ?? 'user';
            if (!isset($grouped[$level])) {
                $grouped[$level] = [];
            }
            $grouped[$level][] = $admin;
        }

        // نمایش به ترتیب: owner, admin, subadmin
        $order = ['owner', 'admin', 'subadmin'];
        foreach ($order as $level) {
            if (!isset($grouped[$level]) || empty($grouped[$level])) {
                continue;
            }

            $text .= "<b>{$levels[$level]}</b>\n";

            foreach ($grouped[$level] as $admin) {
                $username = $admin['username'] ?? '';
                $firstName = $admin['first_name'] ?? '';
                $lastName = $admin['last_name'] ?? '';
                $userId = $admin['user_id'] ?? 0;

                // ساخت نام نمایشی
                $displayName = $firstName;
                if (!empty($lastName)) {
                    $displayName .= ' ' . $lastName;
                }
                if (empty($displayName)) {
                    $displayName = $username ? '@' . $username : "کاربر ناشناس";
                }

                // ساخت لینک به کاربر
                if (!empty($username)) {
                    $userLink = '@' . $username;
                } else {
                    $userLink = "<a href=\"tg://user?id={$userId}\">{$displayName}</a>";
                }

                $addedBy = $admin['added_by'] ?? null;
                $addedAt = $admin['added_at'] ?? null;

                $text .= "  • {$levelIcons[$level]} {$userLink}";
                
                // نمایش آیدی به‌صورت کوچک
                $text .= " <i>(ID: {$userId})</i>\n";

                // نمایش اطلاعات اضافی (اختیاری)
                if ($addedAt) {
                    $date = date('Y/m/d H:i', strtotime($addedAt));
                    $text .= "    📅 افزوده‌شده: {$date}\n";
                }
            }
            $text .= "\n";
        }

        $text .= str_repeat('━', 30) . "\n";
        $text .= "📊 مجموع: <b>" . count($admins) . "</b> نفر";

        return $text;
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "نمایش لیست ادمین‌های گروه / Show group admins list";
    }
}