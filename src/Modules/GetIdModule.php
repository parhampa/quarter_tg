<?php

namespace Modules;

use QuarterTg\Core\Database;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;
use QuarterTg\Core\AuthorizationManager;

/**
 * ماژول دریافت آیدی عددی کاربر یا گروه
 * همه کاربران می‌توانند از این دستور استفاده کنند
 */
class GetIdModule
{
    private $telegram;
    private $db;
    private $logger;

    public function __construct(
        TelegramApi $telegram,
        Database $db,
        Logger $logger
    ) {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
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

        $targetUserId = null;
        $targetChatId = null;
        $targetUsername = null;
        $isGroup = false;

        // 1. بررسی ریپلای به پیام کاربر
        if (isset($message['reply_to_message']) && isset($message['reply_to_message']['from'])) {
            $from = $message['reply_to_message']['from'];
            $targetUserId = $from['id'];
            $targetUsername = $from['username'] ?? null;
            $firstName = $from['first_name'] ?? '';
            $lastName = $from['last_name'] ?? '';
            $displayName = $firstName . (!empty($lastName) ? " $lastName" : '');
            
            if (empty($displayName) && $targetUsername) {
                $displayName = '@' . $targetUsername;
            } elseif (empty($displayName)) {
                $displayName = 'کاربر ناشناس';
            }

            $this->sendUserIdResponse($chatId, $targetUserId, $displayName, $targetUsername, $message);
            return;
        }

        // 2. بررسی پارامتر (یوزرنیم یا آیدی)
        if (!empty($params)) {
            $usernameOrId = trim($params);
            
            // اگر عدد است، به عنوان آیدی در نظر بگیر
            if (is_numeric($usernameOrId)) {
                $targetUserId = (int)$usernameOrId;
                $this->sendUserIdResponse($chatId, $targetUserId, "کاربر با آیدی", null, $message);
                return;
            }
            
            // اگر با @ شروع می‌شود، یوزرنیم است
            if (strpos($usernameOrId, '@') === 0) {
                try {
                    $chatMember = $this->telegram->getChatMember($chatId, $usernameOrId);
                    if ($chatMember && isset($chatMember['user'])) {
                        $user = $chatMember['user'];
                        $targetUserId = $user['id'];
                        $targetUsername = $user['username'] ?? null;
                        $firstName = $user['first_name'] ?? '';
                        $lastName = $user['last_name'] ?? '';
                        $displayName = $firstName . (!empty($lastName) ? " $lastName" : '');
                        
                        if (empty($displayName) && $targetUsername) {
                            $displayName = '@' . $targetUsername;
                        } elseif (empty($displayName)) {
                            $displayName = 'کاربر ناشناس';
                        }
                        
                        $this->sendUserIdResponse($chatId, $targetUserId, $displayName, $targetUsername, $message);
                        return;
                    }
                } catch (\Exception $e) {
                    // کاربر در گروه نیست
                    $this->telegram->sendMessage(
                        $chatId,
                        "❌ کاربر {$usernameOrId} در گروه یافت نشد.",
                        $message['message_id'] ?? null
                    );
                    return;
                }
            }
        }

        // 3. نمایش اطلاعات گروه (در صورت عدم وجود ریپلای یا پارامتر)
        $chatInfo = $message['chat'] ?? [];
        $chatType = $chatInfo['type'] ?? 'unknown';
        
        if ($chatType === 'group' || $chatType === 'supergroup') {
            $chatTitle = $chatInfo['title'] ?? 'گروه ناشناس';
            $chatId = $chatInfo['id'] ?? 0;
            $chatUsername = $chatInfo['username'] ?? null;

            $response = "📋 <b>اطلاعات گروه</b>\n";
            $response .= "━━━━━━━━━━━━━━━━━━━━\n";
            $response .= "📌 نام: <b>{$chatTitle}</b>\n";
            $response .= "🆔 آیدی: <code>{$chatId}</code>\n";
            if ($chatUsername) {
                $response .= "🔗 لینک: t.me/{$chatUsername}\n";
            }
            $response .= "📋 نوع: " . ($chatType === 'supergroup' ? 'سوپرگروه' : 'گروه عادی') . "\n";
            $response .= "━━━━━━━━━━━━━━━━━━━━\n";
            $response .= "👤 <b>اطلاعات شما</b>\n";
            $response .= "🆔 آیدی شما: <code>{$userId}</code>\n";

            $this->telegram->sendMessage(
                $chatId,
                $response,
                $message['message_id'] ?? null,
                'HTML'
            );
            return;
        }

        // 4. پیام خصوصی - فقط آیدی خود کاربر
        $this->telegram->sendMessage(
            $chatId,
            "🆔 آیدی شما: <code>{$userId}</code>",
            $message['message_id'] ?? null,
            'HTML'
        );

        // لاگ
        $this->logger->debug("ID requested by user $userId in chat $chatId");
    }

    /**
     * ارسال پاسخ آیدی کاربر
     */
    private function sendUserIdResponse(
        int $chatId,
        int $targetUserId,
        string $displayName,
        ?string $username,
        array $message
    ): void {
        $response = "🆔 <b>آیدی کاربر</b>\n";
        $response .= "━━━━━━━━━━━━━━━━━━━━\n";
        $response .= "👤 نام: <b>{$displayName}</b>\n";
        $response .= "🆔 آیدی: <code>{$targetUserId}</code>\n";
        
        if ($username) {
            $response .= "🔗 یوزرنیم: @{$username}\n";
        }

        // اگر کاربر در گروه است، اطلاعات اضافی نمایش داده شود
        try {
            $chatMember = $this->telegram->getChatMember($chatId, $targetUserId);
            if ($chatMember && isset($chatMember['status'])) {
                $statusMap = [
                    'creator' => '👑 مالک',
                    'administrator' => '🔹 ادمین',
                    'member' => '👤 عضو',
                    'restricted' => '🔒 محدودشده',
                    'left' => '🚪 خارج‌شده',
                    'kicked' => '🚫 بن‌شده',
                ];
                $status = $statusMap[$chatMember['status']] ?? $chatMember['status'];
                $response .= "📋 وضعیت: {$status}\n";
            }
        } catch (\Exception $e) {
            // نادیده گرفته می‌شود
        }

        $this->telegram->sendMessage(
            $chatId,
            $response,
            $message['message_id'] ?? null,
            'HTML'
        );
    }

    /**
     * توضیحات ماژول
     */
    public static function getDescription(): string
    {
        return "دریافت آیدی عددی کاربر یا گروه / Get user or group ID";
    }
}