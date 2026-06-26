<?php
namespace Core;

use Helpers\TelegramApi;

class AuthorizationManager
{
    private $db;
    private $api;
    private $cache;
    private $permissionManager;
    private $adminManager;
    private $adminsCache = null;

    public function __construct(
        Database $db,
        TelegramApi $api,
        Cache $cache,
        PermissionManager $permissionManager,
        AdminManager $adminManager
    ) {
        $this->db = $db;
        $this->api = $api;
        $this->cache = $cache;
        $this->permissionManager = $permissionManager;
        $this->adminManager = $adminManager;
    }

    private function loadAdminsFromDb(): array
    {
        if ($this->adminsCache !== null) {
            return $this->adminsCache;
        }

        $sql = "SELECT user_id, group_id, role FROM bot_admins";
        $rows = $this->db->fetchAll($sql);
        $owners = [];
        $admins = [];
        foreach ($rows as $row) {
            $userId = (int)$row['user_id'];
            if ($row['role'] === 'owner') {
                $owners[] = $userId;
            } elseif ($row['role'] === 'admin') {
                $groupId = $row['group_id'] !== null ? (int)$row['group_id'] : null;
                if (!isset($admins[$userId])) {
                    $admins[$userId] = [];
                }
                $admins[$userId][] = $groupId;
            }
        }
        $this->adminsCache = ['owners' => $owners, 'admins' => $admins];
        return $this->adminsCache;
    }

    public function authorize(array $update, string $command, string $requiredRole): bool
    {
        if ($requiredRole === 'public') {
            return true;
        }

        $message = $update['message'] ?? null;
        if (!$message) {
            return false;
        }

        $userId = (int)$message['from']['id'];
        $chat = $message['chat'];
        $chatId = (int)$chat['id'];
        $chatType = $chat['type'] ?? 'private';

        $hasPermission = $this->permissionManager->hasPermission($userId, $command);
        if ($hasPermission === true) {
            return true;
        }
        if ($hasPermission === false) {
            return false;
        }

        $adminsData = $this->loadAdminsFromDb();
        $owners = $adminsData['owners'];
        $adminGroupMap = $adminsData['admins'];

        if (in_array($userId, $owners)) {
            return true;
        }
        if ($requiredRole === 'owner') {
            return false;
        }

        if ($requiredRole === 'admin_manager') {
            return $this->adminManager->canManageAdmins($userId);
        }

        if ($chatType === 'private') {
            return false;
        }

        $isBotAdmin = false;
        if (isset($adminGroupMap[$userId])) {
            $allowedGroups = $adminGroupMap[$userId];
            foreach ($allowedGroups as $allowedGroup) {
                if ($allowedGroup === null || $allowedGroup === $chatId) {
                    $isBotAdmin = true;
                    break;
                }
            }
        }

        if ($requiredRole === 'admin') {
            return $isBotAdmin;
        }

        if ($requiredRole === 'group_admin') {
            if ($isBotAdmin) {
                return true;
            }
            return $this->isGroupAdmin($chatId, $userId);
        }

        return false;
    }

    public function isAtLeastGroupAdmin(array $update): bool
    {
        $message = $update['message'] ?? null;
        if (!$message) {
            return false;
        }

        $userId = (int)$message['from']['id'];
        $chat = $message['chat'];
        $chatId = (int)$chat['id'];
        $chatType = $chat['type'] ?? 'private';

        $adminsData = $this->loadAdminsFromDb();
        $owners = $adminsData['owners'];
        if (in_array($userId, $owners)) {
            return true;
        }

        $sql = "SELECT 1 FROM bot_sub_admins WHERE user_id = {$userId}";
        if ($this->db->fetchOne($sql)) {
            return true;
        }

        $adminGroupMap = $adminsData['admins'];
        if (isset($adminGroupMap[$userId])) {
            $allowedGroups = $adminGroupMap[$userId];
            foreach ($allowedGroups as $allowedGroup) {
                if ($allowedGroup === null || $allowedGroup === $chatId) {
                    return true;
                }
            }
        }

        if ($chatType === 'group' || $chatType === 'supergroup') {
            return $this->isGroupAdmin($chatId, $userId);
        }

        return false;
    }

    private function isGroupAdmin(int $chatId, int $userId): bool
    {
        $cacheKey = "chat_member_{$chatId}_{$userId}";
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $member = $this->api->getChatMember($chatId, $userId);
        $isAdmin = $member && in_array($member['status'], ['creator', 'administrator']);
        $this->cache->set($cacheKey, $isAdmin);
        return $isAdmin;
    }
}