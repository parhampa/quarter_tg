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
 * ماژول تنزل سطح دسترسی ادمین
 * 
 * دستورات:
 * - /demote [@username|user_id] – تنزل کاربر به admin
 * - /demote (با ریپلی) – تنزل کاربر ریپلی‌شده به admin
 */
class DemoteModule implements ModuleInterface
{
    public const COMMANDS = ['demote'];

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
        $text = $message['text'] ?? '';
        if (empty($text)) {
            return null;
        }

        $command = substr(trim($text), 1);
        $parts = explode(' ', $command, 2);
        $commandName = strtolower($parts[0]);
        $param = $parts[1] ?? '';

        return match ($commandName) {
            'demote' => $this->handleDemote($chatId, $userId, $param, $message),
            default => null,
        };
    }

    /**
     * تنزل سطح دسترسی
     */
    private function handleDemote(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند تنزل دهد
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند سطح دسترسی را تنزل دهد.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
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
            $this->logger->error('Demote failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در تنزل سطح دسترسی.');
        }
    }

    private function parseTargetUser(string $param, array $message): ?array
    {
        if (empty($param)) {
            if (isset($message['reply_to_message']['from']['id'])) {
                $target = $message['reply_to_message']['from'];
                return [
                    'user_id' => (int)$target['id'],
                    'username' => $target['username'] ?? null,
                ];
            }
            return null;
        }

        $target = trim($param);
        $userId = null;
        $username = null;

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

    private function sendError(int $chatId, string $message): array
    {
        $this->telegram->sendMessage($chatId, $message);
        return ['success' => false, 'message' => $message];
    }
}