<?php
namespace Core;

class WelcomeManager
{
    private $db;
    private $cache;

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function enableWelcome(int $groupId, string $language = 'en'): void
    {
        $groupId = (int)$groupId;
        $language = $this->db->escapeString($language);
        $sql = "INSERT INTO bot_welcome_settings (group_id, enabled, language)
                VALUES ({$groupId}, 1, '{$language}')
                ON DUPLICATE KEY UPDATE enabled = 1, language = '{$language}'";
        $this->db->execute($sql);
        $this->cache->delete("welcome_{$groupId}");
    }

    public function disableWelcome(int $groupId): void
    {
        $groupId = (int)$groupId;
        $sql = "UPDATE bot_welcome_settings SET enabled = 0 WHERE group_id = {$groupId}";
        $this->db->execute($sql);
        $this->cache->delete("welcome_{$groupId}");
    }

    public function isWelcomeEnabled(int $groupId): bool
    {
        $groupId = (int)$groupId;
        $cacheKey = "welcome_{$groupId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool)$cached;
        }

        $sql = "SELECT enabled FROM bot_welcome_settings WHERE group_id = {$groupId}";
        $result = $this->db->fetchOne($sql);
        $enabled = $result ? (bool)$result['enabled'] : false;
        $this->cache->set($cacheKey, $enabled);
        return $enabled;
    }

    public function getWelcomeLanguage(int $groupId): string
    {
        $groupId = (int)$groupId;
        $sql = "SELECT language FROM bot_welcome_settings WHERE group_id = {$groupId}";
        $result = $this->db->fetchOne($sql);
        return $result ? $result['language'] : 'en';
    }
}