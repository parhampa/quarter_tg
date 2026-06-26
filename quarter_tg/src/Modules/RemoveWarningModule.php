<?php

namespace Modules;

class RemoveWarningModule
{
    private $warningManager;
    private $telegram;
    private $db;
    private $logger;

    public function __construct($warningManager, $telegram, $db, $logger)
    {
        $this->warningManager = $warningManager;
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function execute($message, $params)
    {
        $chat_id = $message['chat']['id'];
        $from_id = $message['from']['id'];

        if (!$this->isGroupAdmin($chat_id, $from_id)) {
            $this->telegram->sendMessage($chat_id, "⛔ شما اجازه اجرای این دستور را ندارید.");
            return;
        }

        $target_user = $this->getTargetUser($message);
        if (!$target_user) {
            $this->telegram->sendMessage($chat_id, "⚠️ لطفاً روی پیام کاربر ریپلای کنید یا نام کاربری/آیدی عددی را وارد کنید.");
            return;
        }

        $result = $this->warningManager->removeWarnings($chat_id, $target_user);
        if ($result) {
            $this->telegram->sendMessage($chat_id, "✅ تمام اخطارهای کاربر حذف شد.");
        } else {
            $this->telegram->sendMessage($chat_id, "⚠️ این کاربر اخطاری ندارد.");
        }
    }

    private function getTargetUser($message)
    {
        if (isset($message['reply_to_message']['from']['id'])) {
            return $message['reply_to_message']['from']['id'];
        }
        $text = $message['text'] ?? '';
        $parts = explode(' ', $text);
        if (isset($parts[1])) {
            $target = trim($parts[1]);
            if (strpos($target, '@') === 0) {
                $chat = $this->telegram->getChat($target);
                if (isset($chat['id'])) {
                    return $chat['id'];
                }
            } elseif (is_numeric($target)) {
                return (int)$target;
            }
        }
        return null;
    }

    private function isGroupAdmin($group_id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM bot_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return true;
        }
        $stmt = $this->db->prepare("SELECT id FROM bot_sub_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}