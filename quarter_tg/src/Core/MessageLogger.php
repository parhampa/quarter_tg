<?php
namespace Core;

class MessageLogger
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

    public function logMessage(array $update): bool
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
        $messageId = (int)$message['message_id'];
        $text = isset($message['text']) ? $this->db->escapeString($message['text']) : null;
        $timestamp = (int)($message['date'] ?? time()) * 1000;

        $replyTo = $message['reply_to_message'] ?? null;
        $replyUserId = null;
        $replyUsername = null;
        $replyFirstName = null;
        $replyLastName = null;

        if ($replyTo) {
            $replyFrom = $replyTo['from'] ?? null;
            if ($replyFrom) {
                $replyUserId = (int)$replyFrom['id'];
                $replyUsername = $this->db->escapeString($replyFrom['username'] ?? '');
                $replyFirstName = $this->db->escapeString($replyFrom['first_name'] ?? '');
                $replyLastName = $this->db->escapeString($replyFrom['last_name'] ?? '');
            }
        }

        $sql = "INSERT INTO bot_messages (
            chat_id, chat_title, user_id, username, first_name, last_name,
            message_id, text, timestamp_ms,
            reply_to_user_id, reply_to_username, reply_to_first_name, reply_to_last_name
        ) VALUES (
            {$chatId}, '{$chatTitle}', {$userId}, '{$username}', '{$firstName}', '{$lastName}',
            {$messageId}, " . ($text !== null ? "'{$text}'" : "NULL") . ", {$timestamp},
            " . ($replyUserId !== null ? $replyUserId : "NULL") . ",
            " . ($replyUsername !== null ? "'{$replyUsername}'" : "NULL") . ",
            " . ($replyFirstName !== null ? "'{$replyFirstName}'" : "NULL") . ",
            " . ($replyLastName !== null ? "'{$replyLastName}'" : "NULL") . "
        )";

        try {
            $this->db->execute($sql);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}