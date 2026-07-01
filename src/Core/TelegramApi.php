<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use CURLFile;
use Throwable;

/**
 * کلاس ارتباط با API تلگرام
 * 
 * ویژگیها:
 * - استفاده از cURL با Timeout و Retry
 * - مدیریت Rate Limiting (خودکار Sleep)
 * - پشتیبانی از انواع پیام (متن، عکس، فایل، کیبورد)
 * - مدیریت کامل خطاها با لاگ و پرتاب استثنا
 */
class TelegramApi
{
    private string $token;
    private string $apiUrl;
    private Logger $logger;
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private int $maxRetries = 3;
    private int $retryDelay = 1000000; // 1 ثانیه (میکروثانیه)
    
    /** @var array|null تنظیمات پروکسی (host, port, type) */
    private ?array $proxy = null;
    
    /** @var array کاربران در حال انتظار برای Rate Limit */
    private array $rateLimitQueue = [];

    public function __construct(string $token, Logger $logger, ?array $proxy = null)
    {
        $this->token = $token;
        $this->logger = $logger;
        $this->proxy = $proxy;
        $this->apiUrl = 'https://api.telegram.org/bot' . $token . '/';
    }

    // ============================================================
    // متدهای عمومی ارسال پیام
    // ============================================================

    /**
     * ارسال پیام متنی ساده
     */
    public function sendMessage(
        int $chatId,
        string $text,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
            'disable_notification' => false,
        ], $options);

        return $this->call('sendMessage', $params);
    }

    /**
     * ارسال پیام با Reply Keyboard (دکمههای زیر پیام)
     */
    public function sendReplyKeyboard(
        int $chatId,
        string $text,
        array $keyboard,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
                'selective' => false,
            ]),
        ], $options);

        return $this->call('sendMessage', $params);
    }

    /**
     * ارسال پیام با Inline Keyboard (دکمههای داخل پیام)
     */
    public function sendInlineKeyboard(
        int $chatId,
        string $text,
        array $inlineKeyboard,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'text'    => $text,
            'reply_markup' => json_encode([
                'inline_keyboard' => $inlineKeyboard,
            ]),
        ], $options);

        return $this->call('sendMessage', $params);
    }

    /**
     * ارسال عکس
     */
    public function sendPhoto(
        int $chatId,
        string $photo,
        ?string $caption = null,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'photo'   => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendPhoto', $params);
    }

    /**
     * ارسال عکس از فایل محلی
     */
    public function sendPhotoFile(
        int $chatId,
        string $filePath,
        ?string $caption = null,
        array $options = []
    ): array {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'photo'   => new CURLFile($filePath),
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendPhoto', $params, true); // multipart
    }

    /**
     * ارسال فایل (سند)
     */
    public function sendDocument(
        int $chatId,
        string $document,
        ?string $caption = null,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendDocument', $params);
    }

    /**
     * ارسال فایل از فایل محلی
     */
    public function sendDocumentFile(
        int $chatId,
        string $filePath,
        ?string $caption = null,
        array $options = []
    ): array {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $params = array_merge([
            'chat_id' => $chatId,
            'document' => new CURLFile($filePath),
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendDocument', $params, true);
    }

    /**
     * ارسال استیکر
     */
    public function sendSticker(int $chatId, string $sticker, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'sticker' => $sticker,
        ], $options);

        return $this->call('sendSticker', $params);
    }

    /**
     * ارسال Voice
     */
    public function sendVoice(int $chatId, string $voice, ?string $caption = null, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
            'caption' => $caption,
        ], $options);

        return $this->call('sendVoice', $params);
    }

    /**
     * ارسال Video
     */
    public function sendVideo(int $chatId, string $video, ?string $caption = null, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendVideo', $params);
    }

    /**
     * ارسال انیمیشن (GIF)
     */
    public function sendAnimation(int $chatId, string $animation, ?string $caption = null, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'animation' => $animation,
            'caption' => $caption,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('sendAnimation', $params);
    }

    /**
     * ارسال Action (Typing, Upload Photo, ...)
     */
    public function sendChatAction(int $chatId, string $action = 'typing'): array
    {
        return $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    // ============================================================
    // متدهای مدیریت پیام
    // ============================================================

    /**
     * حذف پیام
     */
    public function deleteMessage(int $chatId, int $messageId): array
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * ویرایش پیام متنی
     */
    public function editMessageText(
        int $chatId,
        int $messageId,
        string $text,
        array $options = []
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        return $this->call('editMessageText', $params);
    }

    /**
     * ویرایش Inline Keyboard پیام
     */
    public function editMessageReplyMarkup(
        int $chatId,
        int $messageId,
        array $inlineKeyboard
    ): array {
        return $this->call('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    /**
     * پاسخ به Callback Query (برای دکمههای شیشهای)
     */
    public function answerCallbackQuery(
        string $callbackQueryId,
        string $text = '',
        bool $showAlert = false,
        ?string $url = null,
        int $cacheTime = 0
    ): array {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
            'cache_time' => $cacheTime,
        ];
        if ($url !== null) {
            $params['url'] = $url;
        }

        return $this->call('answerCallbackQuery', $params);
    }

    // ============================================================
    // متدهای مدیریت گروه و کاربر
    // ============================================================

    /**
     * بن کردن کاربر در گروه
     */
    public function banChatMember(int $chatId, int $userId, ?int $untilDate = null): array
    {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ];
        if ($untilDate !== null) {
            $params['until_date'] = $untilDate;
        }

        return $this->call('banChatMember', $params);
    }

    /**
     * خارج کردن بن کاربر
     */
    public function unbanChatMember(int $chatId, int $userId): array
    {
        return $this->call('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * محدود کردن کاربر (Mute)
     */
    public function restrictChatMember(
        int $chatId,
        int $userId,
        array $permissions,
        ?int $untilDate = null
    ): array {
        $params = [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'permissions' => json_encode($permissions),
        ];
        if ($untilDate !== null) {
            $params['until_date'] = $untilDate;
        }

        return $this->call('restrictChatMember', $params);
    }

    /**
     * ارتقا به ادمین
     */
    public function promoteChatMember(
        int $chatId,
        int $userId,
        array $privileges
    ): array {
        $params = array_merge([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ], $privileges);

        return $this->call('promoteChatMember', $params);
    }

    /**
     * تنزل از ادمین
     */
    public function demoteChatMember(int $chatId, int $userId): array
    {
        return $this->call('promoteChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'can_change_info' => false,
            'can_post_messages' => false,
            'can_edit_messages' => false,
            'can_delete_messages' => false,
            'can_invite_users' => false,
            'can_restrict_members' => false,
            'can_pin_messages' => false,
            'can_promote_members' => false,
        ]);
    }

    /**
     * دریافت اطلاعات کاربر
     */
    public function getChatMember(int $chatId, int $userId): array
    {
        return $this->call('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * دریافت اطلاعات چت
     */
    public function getChat(int $chatId): array
    {
        return $this->call('getChat', [
            'chat_id' => $chatId,
        ]);
    }

    /**
     * دریافت اطلاعات کاربر (از طریق API)
     */
    public function getUserProfilePhotos(int $userId, int $limit = 1): array
    {
        return $this->call('getUserProfilePhotos', [
            'user_id' => $userId,
            'limit' => $limit,
        ]);
    }

    // ============================================================
    // متدهای داخلی برای فراخوانی API
    // ============================================================

    /**
     * فراخوانی متد API تلگرام
     * 
     * @param string $method نام متد (مثلاً sendMessage)
     * @param array $params پارامترهای متد
     * @param bool $multipart آیا درخواست multipart/form-data است؟ (برای آپلود فایل)
     * @return array پاسخ JSON از تلگرام
     * @throws \RuntimeException در صورت خطای API
     */
    private function call(string $method, array $params = [], bool $multipart = false): array
    {
        $url = $this->apiUrl . $method;
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;
            
            try {
                $response = $this->sendRequest($url, $params, $multipart);
                
                // بررسی پاسخ
                if (!isset($response['ok']) || $response['ok'] !== true) {
                    $errorCode = $response['error_code'] ?? 0;
                    $errorDesc = $response['description'] ?? 'Unknown error';
                    
                    // Rate Limit (429)
                    if ($errorCode === 429) {
                        $retryAfter = (int)($response['parameters']['retry_after'] ?? 5);
                        $this->logger->warning("Rate limit hit. Retry after {$retryAfter}s.", [
                            'method' => $method,
                            'attempt' => $attempt,
                            'retry_after' => $retryAfter,
                        ]);
                        sleep($retryAfter);
                        continue; // تلاش مجدد
                    }
                    
                    // خطاهای موقتی (500, 502, 503, 504)
                    if ($errorCode >= 500 && $errorCode < 600) {
                        $this->logger->warning("Temporary API error {$errorCode}. Retrying...", [
                            'method' => $method,
                            'attempt' => $attempt,
                        ]);
                        usleep($this->retryDelay * $attempt);
                        continue;
                    }
                    
                    // سایر خطاها
                    $this->logger->error("Telegram API error: {$errorCode} - {$errorDesc}", [
                        'method' => $method,
                        'params' => $params,
                    ]);
                    throw new \RuntimeException("Telegram API error: {$errorDesc} (Code: {$errorCode})");
                }
                
                // موفقیت
                return $response;
                
            } catch (Throwable $e) {
                $this->logger->error("Request failed: " . $e->getMessage(), [
                    'method' => $method,
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                ]);
                
                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelay * $attempt);
                    continue;
                }
                throw new \RuntimeException("Failed after {$this->maxRetries} attempts: " . $e->getMessage(), 0, $e);
            }
        }

        throw new \RuntimeException("Unreachable code: Request failed after retries.");
    }

    /**
     * ارسال درخواست HTTP با cURL
     * 
     * @return array پاسخ JSON
     */
    private function sendRequest(string $url, array $params, bool $multipart = false): array
    {
        $ch = curl_init();
        
        if ($multipart) {
            // برای ارسال فایل
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        } else {
            // برای ارسال JSON معمولی
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'QuarterTG Bot v1.0');
        
        // پروکسی
        if ($this->proxy !== null) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['host'] . ':' . $this->proxy['port']);
            if (isset($this->proxy['type'])) {
                curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy['type']);
            }
            if (isset($this->proxy['username']) && isset($this->proxy['password'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['username'] . ':' . $this->proxy['password']);
            }
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new \RuntimeException("cURL error: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new \RuntimeException("HTTP error {$httpCode}: {$response}");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }

    // ============================================================
    // متدهای تنظیمات
    // ============================================================

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setConnectTimeout(int $connectTimeout): self
    {
        $this->connectTimeout = $connectTimeout;
        return $this;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function setRetryDelay(int $microseconds): self
    {
        $this->retryDelay = $microseconds;
        return $this;
    }

    /**
     * تنظیم پروکسی
     * 
     * @param string $host آدرس پروکسی
     * @param int $port پورت
     * @param int|null $type CURLPROXY_HTTP, CURLPROXY_SOCKS5, ...
     * @param string|null $username نام کاربری (اختیاری)
     * @param string|null $password رمز عبور (اختیاری)
     */
    public function setProxy(
        string $host,
        int $port,
        ?int $type = null,
        ?string $username = null,
        ?string $password = null
    ): self {
        $this->proxy = [
            'host' => $host,
            'port' => $port,
            'type' => $type ?? CURLPROXY_HTTP,
        ];
        if ($username !== null && $password !== null) {
            $this->proxy['username'] = $username;
            $this->proxy['password'] = $password;
        }
        return $this;
    }
}