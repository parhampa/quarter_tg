<?php

namespace Helpers;

class TelegramApi
{
    private $token;
    private $baseUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->baseUrl = "https://api.telegram.org/bot{$token}/";
    }

    /**
     * ارسال درخواست به API تلگرام
     */
    public function request(string $method, array $params = []): ?array
    {
        $url = $this->baseUrl . $method;
        $options = [
            'http' => [
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($params),
                'timeout' => 10,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        $data = json_decode($response, true);
        return $data && $data['ok'] ? $data : null;
    }

    /**
     * ارسال پیام متنی
     */
    public function sendMessage(int|string $chatId, string $text, int $replyToMessageId = null, string $parseMode = 'HTML', array $replyMarkup = null): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => $parseMode,
        ];
        if ($replyToMessageId) {
            $params['reply_to_message_id'] = $replyToMessageId;
        }
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->request('sendMessage', $params);
    }

    /**
     * حذف پیام
     */
    public function deleteMessage(int|string $chatId, int $messageId): ?array
    {
        return $this->request('deleteMessage', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * پین کردن پیام
     */
    public function pinChatMessage(int|string $chatId, int $messageId, bool $disableNotification = false): ?array
    {
        return $this->request('pinChatMessage', [
            'chat_id'              => $chatId,
            'message_id'           => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    /**
     * برداشتن پین
     */
    public function unpinChatMessage(int|string $chatId, int $messageId = null): ?array
    {
        $params = ['chat_id' => $chatId];
        if ($messageId) {
            $params['message_id'] = $messageId;
        }
        return $this->request('unpinChatMessage', $params);
    }

    /**
     * دریافت اطلاعات یک عضو گروه
     */
    public function getChatMember(int|string $chatId, int $userId): ?array
    {
        $result = $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
        return $result ? $result['result'] : null;
    }

    /**
     * دریافت اطلاعات گروه
     */
    public function getChat(int|string $chatId): ?array
    {
        $result = $this->request('getChat', ['chat_id' => $chatId]);
        return $result ? $result['result'] : null;
    }

    /**
     * تبدیل یوزرنیم یا آیدی به آیدی عددی
     */
    public function resolveUserId(string $usernameOrId): ?int
    {
        if (is_numeric($usernameOrId)) {
            return (int)$usernameOrId;
        }
        if (strpos($usernameOrId, '@') === 0) {
            $result = $this->request('getChat', ['chat_id' => $usernameOrId]);
            if ($result && isset($result['result']['id'])) {
                return (int)$result['result']['id'];
            }
            return null;
        }
        return null;
    }

    /**
     * دریافت آیدی کاربر از پیام ریپلای شده
     */
    public function getUserIdFromReply(array $update): ?int
    {
        $message = $update['message'] ?? null;
        if (!$message) {
            return null;
        }
        $replyToMessage = $message['reply_to_message'] ?? null;
        if (!$replyToMessage) {
            return null;
        }
        $from = $replyToMessage['from'] ?? null;
        if (!$from) {
            return null;
        }
        return (int)$from['id'];
    }

    /**
     * بن کردن کاربر (اخراج از گروه)
     */
    public function banChatMember(int|string $chatId, int $userId, int $untilDate = null, bool $revokeMessages = true): ?array
    {
        $params = [
            'chat_id'         => $chatId,
            'user_id'         => $userId,
            'revoke_messages' => $revokeMessages,
        ];
        if ($untilDate) {
            $params['until_date'] = $untilDate;
        }
        return $this->request('banChatMember', $params);
    }

    /**
     * رفع بن کاربر
     */
    public function unbanChatMember(int|string $chatId, int $userId, bool $onlyIfBanned = true): ?array
    {
        return $this->request('unbanChatMember', [
            'chat_id'       => $chatId,
            'user_id'       => $userId,
            'only_if_banned' => $onlyIfBanned,
        ]);
    }

    /**
     * محدود کردن کاربر (مثلاً برای میوت)
     */
    public function restrictChatMember(int|string $chatId, int $userId, array $permissions, int $untilDate = null): ?array
    {
        $params = [
            'chat_id'     => $chatId,
            'user_id'     => $userId,
            'permissions' => json_encode($permissions),
        ];
        if ($untilDate) {
            $params['until_date'] = $untilDate;
        }
        return $this->request('restrictChatMember', $params);
    }

    /**
     * ارسال وضعیت تایپ یا آپلود
     */
    public function sendChatAction(int|string $chatId, string $action): ?array
    {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action'  => $action,
        ]);
    }

    /**
     * ارسال پیام با دکمه‌های اینلاین (اختیاری)
     */
    public function sendMessageWithInlineKeyboard(int|string $chatId, string $text, array $keyboard, int $replyToMessageId = null): ?array
    {
        $replyMarkup = [
            'inline_keyboard' => $keyboard,
        ];
        return $this->sendMessage($chatId, $text, $replyToMessageId, 'HTML', $replyMarkup);
    }

    /**
     * دریافت فایل (برای دانلود عکس/فیلم/...)
     */
    public function getFile(string $fileId): ?array
    {
        $result = $this->request('getFile', ['file_id' => $fileId]);
        return $result ? $result['result'] : null;
    }

    /**
     * دریافت لینک دانلود فایل
     */
    public function getFileUrl(string $fileId): ?string
    {
        $file = $this->getFile($fileId);
        if ($file && isset($file['file_path'])) {
            return "https://api.telegram.org/file/bot{$this->token}/{$file['file_path']}";
        }
        return null;
    }

    /**
     * ارسال پیام به چند کاربر (برای اطلاع‌رسانی همگانی)
     */
    public function sendMessageToMultiple(array $chatIds, string $text, string $parseMode = 'HTML'): array
    {
        $results = [];
        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->sendMessage($chatId, $text, null, $parseMode);
        }
        return $results;
    }
}