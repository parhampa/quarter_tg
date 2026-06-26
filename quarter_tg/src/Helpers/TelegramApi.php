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

    public function getChatMember(int|string $chatId, int $userId): ?array
    {
        $result = $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
        return $result ? $result['result'] : null;
    }

    public function getChat(int|string $chatId): ?array
    {
        $result = $this->request('getChat', ['chat_id' => $chatId]);
        return $result ? $result['result'] : null;
    }

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
}