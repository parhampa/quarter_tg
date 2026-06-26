<?php
namespace Core;

class CommandLogger
{
    private $db;
    private $cache;
    private $enabled;

    public function __construct(Database $db, Cache $cache, bool $enabled = true)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->enabled = $enabled;
    }

    public function logCommand(array $update, string $command, array $args = []): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return false;
        }

        $chat = $message['chat'] ?? null;
        if (!$chat || ($chat['type'] !== 'group' && $chat['type'] !== 'supergroup')) {
            return false;
        }

        $from = $message['from'] ?? null;
        if (!$from) {
            return false;
        }

        $chatId = (int)$chat['id'];
        $chatTitle = $this->db->escapeString($chat['title'] ?? '');
        $userId = (int)$from['id'];
        $username = $this->db->escapeString($from['username'] ?? '');
        $firstName = $this->db->escapeString($from['first_name'] ?? '');
        $lastName = $this->db->escapeString($from['last_name'] ?? '');
        $argsStr = !empty($args) ? $this->db->escapeString(implode(' ', $args)) : '';

        $sql = "INSERT INTO bot_command_logs (
            chat_id, chat_title, user_id, username, first_name, last_name,
            command, arguments, timestamp_ms
        ) VALUES (
            {$chatId}, '{$chatTitle}', {$userId}, '{$username}', '{$firstName}', '{$lastName}',
            '{$this->db->escapeString($command)}', '{$argsStr}', " . (time() * 1000) . "
        )";

        try {
            $this->db->execute($sql);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getLastClearTime(int $groupId): ?int
    {
        $sql = "SELECT MAX(timestamp_ms) as last_time
                FROM bot_command_logs
                WHERE chat_id = {$groupId} AND command = 'clear'";
        $result = $this->db->fetchOne($sql);
        if ($result && $result['last_time']) {
            return (int)$result['last_time'];
        }
        return null;
    }
}