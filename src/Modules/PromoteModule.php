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
 * ماژول ارتقای سطح دسترسی ادمین
 * 
 * دستورات:
 * - /promote [@username|user_id] – ارتقای کاربر به super_admin
 * - /promote (با ریپلی) – ارتقای کاربر ریپلی‌شده به super_admin
 */
class PromoteModule implements ModuleInterface
{
    public const COMMANDS = ['promote'];

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
            'promote' => $this->handlePromote($chatId, $userId, $param, $message),
            default => null,
        };
    }

    /**
     * ارتقای سطح دسترسی
     */
    private function handlePromote(int $chatId, int $adminId, string $param, array $message): array
    {
        // فقط مالک ربات میتواند ارتقا دهد
        if (!$this->authManager->isOwner($adminId)) {
            return $this->sendError($chatId, '⛔ فقط مالک ربات میتواند سطح دسترسی را ارتقا دهد.');
        }

        $result = $this->parseTargetUser($param, $message);
        if ($result === null) {
            return $this->sendError($chatId, '❌ کاربر مورد نظر یافت نشد. لطفاً با @username، ID یا ریپلی مشخص کنید.');
        }

        $targetUserId = $result['user_id'];
        $targetUsername = $result['username'] ?? 'کاربر';

        // جلوگیری از ارتقای مالک
        if ($targetUserId === $this->ownerId) {
            return $this->sendError($chatId, '⛔ مالک ربات همیشه super_admin است.');
        }

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
            $this->logger->error('Promote failed.', [
                'chat' => $chatId,
                'user' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
            return $this->sendError($chatId, '❌ خطا در ارتقای سطح دسترسی.');
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