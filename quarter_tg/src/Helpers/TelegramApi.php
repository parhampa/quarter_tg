<?php

namespace QuarterTg\Helpers;

/**
 * کلاس ارتباط با Telegram Bot API
 * با پشتیبانی از متدهای رایج و مدیریت خطا
 */
class TelegramApi
{
    private $token;
    private $baseUrl;
    private $timeout = 10;
    private $lastError = null;

    /**
     * @param string $token توکن ربات از @BotFather
     */
    public function __construct(string $token)
    {
        $this->token = $token;
        $this->baseUrl = "https://api.telegram.org/bot{$token}/";
    }

    /**
     * ارسال درخواست به API تلگرام با استفاده از cURL
     * @param string $method نام متد API
     * @param array $params پارامترهای درخواست
     * @return array|null پاسخ API (در صورت موفقیت)
     */
    public function request(string $method, array $params = []): ?array
    {
        $url = $this->baseUrl . $method;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = "cURL error: $error";
            return null;
        }

        if ($httpCode !== 200) {
            $this->lastError = "HTTP error: $httpCode";
            return null;
        }

        $data = json_decode($response, true);
        if ($data === null) {
            $this->lastError = "Invalid JSON response";
            return null;
        }

        if (!isset($data['ok']) || $data['ok'] !== true) {
            $this->lastError = $data['description'] ?? 'Unknown API error';
            return null;
        }

        $this->lastError = null;
        return $data;
    }

    /**
     * ارسال پیام متنی
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        ?int $replyToMessageId = null,
        string $parseMode = 'HTML',
        ?array $replyMarkup = null
    ): ?array {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
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
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * پین کردن پیام
     */
    public function pinChatMessage(
        int|string $chatId,
        int $messageId,
        bool $disableNotification = false
    ): ?array {
        return $this->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    /**
     * برداشتن پین
     */
    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): ?array
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
    public function getChatMember(int|string $chatId, int|string $userId): ?array
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
     * دریافت اطلاعات ربات (getMe)
     */
    public function getMe(): ?array
    {
        $result = $this->request('getMe', []);
        return $result ? $result['result'] : null;
    }

    /**
     * بن کردن کاربر (اخراج از گروه)
     */
    public function banChatMember(
        int|string $chatId,
        int $userId,
        ?int $untilDate = null,
        bool $revokeMessages = true
    ): ?array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
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
    public function unbanChatMember(
        int|string $chatId,
        int $userId,
        bool $onlyIfBanned = true
    ): ?array {
        return $this->request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => $onlyIfBanned,
        ]);
    }

    /**
     * محدود کردن کاربر (برای میوت)
     */
    public function restrictChatMember(
        int|string $chatId,
        int $userId,
        array $permissions,
        ?int $untilDate = null
    ): ?array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
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
            'action' => $action,
        ]);
    }

    /**
     * پاسخ به Callback Query
     */
    public function answerCallbackQuery(array $params): ?array
    {
        return $this->request('answerCallbackQuery', $params);
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
     * دریافت فایل
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
     * ارسال پیام با دکمه‌های اینلاین
     */
    public function sendMessageWithInlineKeyboard(
        int|string $chatId,
        string $text,
        array $keyboard,
        ?int $replyToMessageId = null,
        string $parseMode = 'HTML'
    ): ?array {
        $replyMarkup = ['inline_keyboard' => $keyboard];
        return $this->sendMessage($chatId, $text, $replyToMessageId, $parseMode, $replyMarkup);
    }

    /**
     * دریافت آخرین خطا
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * تنظیم Timeout درخواست‌ها
     */
    public function setTimeout(int $seconds): void
    {
        $this->timeout = $seconds;
    }

    /**
     * دریافت توکن ربات (برای استفاده در سایر کلاس‌ها)
     */
    public function getToken(): string
    {
        return $this->token;
    }
}