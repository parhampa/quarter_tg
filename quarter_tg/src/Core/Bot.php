<?php
namespace Core;

use Helpers\TelegramApi;
use Exceptions\ModuleNotFoundException;

class Bot
{
    private $api;
    private $auth;
    private $moduleManager;
    private $requestHandler;
    private $logger;
    private $config;
    private $welcomeManager;
    private $lockManager;
    private $messageLogger;
    private $commandLogger;

    public function __construct(
        TelegramApi $api,
        AuthorizationManager $auth,
        ModuleManager $moduleManager,
        RequestHandler $requestHandler,
        Logger $logger,
        array $config,
        WelcomeManager $welcomeManager,
        LockManager $lockManager,
        MessageLogger $messageLogger,
        CommandLogger $commandLogger
    ) {
        $this->api = $api;
        $this->auth = $auth;
        $this->moduleManager = $moduleManager;
        $this->requestHandler = $requestHandler;
        $this->logger = $logger;
        $this->config = $config;
        $this->welcomeManager = $welcomeManager;
        $this->lockManager = $lockManager;
        $this->messageLogger = $messageLogger;
        $this->commandLogger = $commandLogger;
    }

    public function handleRequest(string $input): void
    {
        $update = json_decode($input, true);
        if (!$update) {
            $this->logger->debug('Invalid update', ['update' => $update]);
            return;
        }

        $this->logger->debug('Update received', ['update' => $update]);

        // Log all messages (except bot's own)
        if (isset($update['message'])) {
            $botInfo = $this->api->request('getMe');
            $botId = $botInfo && isset($botInfo['result']['id']) ? (int)$botInfo['result']['id'] : 0;
            $senderId = isset($update['message']['from']['id']) ? (int)$update['message']['from']['id'] : 0;
            if ($senderId !== $botId) {
                $this->messageLogger->logMessage($update);
            }
        }

        // Handle new members for welcome
        if (isset($update['message']['new_chat_members'])) {
            $this->handleNewMembers($update);
            return;
        }

        // If no text, it might be a media message – check locks
        if (!isset($update['message']['text'])) {
            $this->handleNonCommandMessage($update);
            return;
        }

        // Parse command
        $parsed = $this->requestHandler->parseCommand($update);
        if ($parsed) {
            $command = $parsed['command'];
            $args = $parsed['args'];

            $moduleInfo = $this->moduleManager->getModuleInfo($command);
            if (!$moduleInfo) {
                $this->sendUnknownCommand($update, $command);
                return;
            }

            $allowedInPrivate = $moduleInfo['allowed_in_private'] ?? false;
            $chatType = $update['message']['chat']['type'] ?? 'private';
            if (!$allowedInPrivate && $chatType === 'private') {
                $this->sendError($update, $this->getLocalizedError($update, 'private_not_allowed'));
                return;
            }

            $authorizedOnly = $moduleInfo['authorized_only'] ?? true;
            if ($authorizedOnly) {
                $requiredRole = $moduleInfo['required_role'] ?? 'group_admin';
                if (!$this->auth->authorize($update, $command, $requiredRole)) {
                    $this->sendUnauthorized($update);
                    return;
                }
            }

            // Log command execution (only for authorized users)
            if ($authorizedOnly) {
                $this->commandLogger->logCommand($update, $command, $args);
            }

            try {
                $this->moduleManager->execute($command, $args, $update, $this->api);
                $this->logger->info("Command '{$command}' executed", ['user' => $update['message']['from']['id']]);
            } catch (ModuleNotFoundException $e) {
                $this->logger->error($e->getMessage());
                $this->sendError($update, $this->getLocalizedError($update, 'module_not_found'));
            } catch (\Exception $e) {
                $this->logger->error('Module execution error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $this->sendError($update, $this->getLocalizedError($update, 'internal_error'));
            }
        } else {
            // Not a command – handle as locked content
            $this->handleNonCommandMessage($update);
        }
    }

    private function handleNewMembers(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;
        $chat = $message['chat'];
        $chatId = $chat['id'];
        $chatType = $chat['type'] ?? '';
        if ($chatType !== 'group' && $chatType !== 'supergroup') return;
        if (!$this->welcomeManager->isWelcomeEnabled($chatId)) return;

        $newMembers = $message['new_chat_members'] ?? [];
        if (empty($newMembers)) return;

        $language = $this->welcomeManager->getWelcomeLanguage($chatId);
        foreach ($newMembers as $member) {
            $botInfo = $this->api->request('getMe');
            if ($botInfo && isset($botInfo['result']['id']) && $botInfo['result']['id'] == $member['id']) continue;

            $firstName = $member['first_name'] ?? '';
            $username = $member['username'] ?? '';
            $userId = $member['id'] ?? 0;
            $mention = $username ? "@{$username}" : "<a href='tg://user?id={$userId}'>{$firstName}</a>";

            if ($language === 'fa') {
                $welcomeText = "🎉 به گروه خوش آمدید {$mention}!\nامیدواریم از حضور در این گروه لذت ببرید.";
            } else {
                $welcomeText = "🎉 Welcome to the group {$mention}!\nWe hope you enjoy being here.";
            }
            $this->api->sendMessage($chatId, $welcomeText, null, 'HTML');
        }
    }

    private function handleNonCommandMessage(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!$message) return;
        $chat = $message['chat'];
        $chatId = $chat['id'];
        $chatType = $chat['type'] ?? '';
        if ($chatType !== 'group' && $chatType !== 'supergroup') return;

        if ($this->auth->isAtLeastGroupAdmin($update)) {
            return;
        }

        $messageId = $message['message_id'];
        // Text
        if (isset($message['text']) && $message['text'] !== '') {
            if ($this->lockManager->isLocked($chatId, 'messages')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted text from non-admin in locked group {$chatId}");
            }
        }
        // Sticker
        if (isset($message['sticker'])) {
            if ($this->lockManager->isLocked($chatId, 'stickers')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted sticker from non-admin in locked group {$chatId}");
            }
        }
        // Photo
        if (isset($message['photo'])) {
            if ($this->lockManager->isLocked($chatId, 'photos')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted photo from non-admin in locked group {$chatId}");
            }
        }
        // Video
        if (isset($message['video'])) {
            if ($this->lockManager->isLocked($chatId, 'videos')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted video from non-admin in locked group {$chatId}");
            }
        }
        // GIF / Animation
        if (isset($message['animation'])) {
            if ($this->lockManager->isLocked($chatId, 'gifs')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted GIF from non-admin in locked group {$chatId}");
            }
        }
        // NEW: Voice
        if (isset($message['voice'])) {
            if ($this->lockManager->isLocked($chatId, 'voice')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted voice from non-admin in locked group {$chatId}");
            }
        }
        // NEW: Video note
        if (isset($message['video_note'])) {
            if ($this->lockManager->isLocked($chatId, 'video_notes')) {
                $this->api->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
                $this->logger->info("Deleted video note from non-admin in locked group {$chatId}");
            }
        }
    }

    private function getLocalizedError(array $update, string $key): string
    {
        $lang = 'en';
        $text = $update['message']['text'] ?? '';
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) $lang = 'fa';
        $errors = [
            'private_not_allowed' => ['en' => "❌ This command can only be used in groups.", 'fa' => "❌ این دستور فقط در گروه قابل اجراست."],
            'module_not_found' => ['en' => "❌ Internal error: Module not found.", 'fa' => "❌ خطای داخلی: ماژول یافت نشد."],
            'internal_error' => ['en' => "❌ An internal error occurred. Please try again later.", 'fa' => "❌ خطای داخلی رخ داده است. لطفاً بعداً تلاش کنید."],
        ];
        return $errors[$key][$lang] ?? $errors[$key]['en'];
    }

    private function sendUnknownCommand(array $update, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $persian = [
            'ست ادمین', 'حذف ادمین', 'لیست ادمین‌ها',
            'خوش آمد بگو', 'حذف خوش آمدگویی',
            'پین', 'حذف پین', 'آیدی',
            'حذف', 'پاکسازی',
            'قفل پیام', 'حذف قفل پیام',
            'قفل استیکر', 'حذف قفل استیکر',
            'قفل عکس', 'حذف قفل عکس',
            'قفل فیلم', 'حذف قفل فیلم',
            'قفل گیف', 'حذف قفل گیف',
            'قفل ویس', 'حذف قفل ویس',              // NEW
            'قفل ویدئو مسیج', 'حذف قفل ویدئو مسیج' // NEW
        ];
        $text = in_array($command, $persian) ? "❌ دستور <b>{$command}</b> تعریف نشده است." : "❌ Command <b>{$command}</b> is not defined.";
        $this->api->sendMessage($chatId, $text, $msgId);
    }

    private function sendUnauthorized(array $update): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $lang = 'en';
        $text = $update['message']['text'] ?? '';
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) $lang = 'fa';
        $msg = $lang === 'fa' ? "⛔ شما مجوز اجرای این دستور را ندارید." : "⛔ You don't have permission to execute this command.";
        $this->api->sendMessage($chatId, $msg, $msgId);
    }

    private function sendError(array $update, string $message): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $this->api->sendMessage($chatId, $message, $msgId);
    }
}