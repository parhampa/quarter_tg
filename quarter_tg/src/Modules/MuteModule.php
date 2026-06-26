<?php

namespace Modules;

class MuteModule
{
    private $muteManager;
    private $telegram;
    private $db;
    private $logger;

    public function __construct($muteManager, $telegram, $db, $logger)
    {
        $this->muteManager = $muteManager;
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function execute($message, $params)
    {
        $chat_id = $message['chat']['id'];
        $from_id = $message['from']['id'];

        // بررسی مجوز ادمین (بر اساس سیستم موجود)
        if (!$this->isGroupAdmin($chat_id, $from_id)) {
            $this->telegram->sendMessage($chat_id, "⛔ شما اجازه اجرای این دستور را ندارید.");
            return;
        }

        // دریافت کاربر هدف
        $target_user = $this->getTargetUser($message);
        if (!$target_user) {
            $this->telegram->sendMessage($chat_id, "⚠️ لطفاً روی پیام کاربر ریپلای کنید یا نام کاربری/آیدی عددی را وارد کنید.\n⚠️ Please reply to user's message or enter username/numeric ID.");
            return;
        }

        // جلوگیری از سکوت ادمین‌ها
        if ($this->isGroupAdmin($chat_id, $target_user)) {
            $this->telegram->sendMessage($chat_id, "❌ نمی‌توانید ادمین را ساکت کنید.");
            return;
        }

        $reason = $params['reason'] ?? '';
        $result = $this->muteManager->muteUser($chat_id, $target_user, $from_id, $reason);
        if ($result) {
            // حذف ۵۰ پیام اخیر کاربر
            $deleted = $this->muteManager->deleteUserMessages($chat_id, $target_user, 50);
            $this->telegram->sendMessage($chat_id, "✅ کاربر ساکت شد. تعداد $deleted پیام حذف شد.");
            $this->logger->log("Mute executed by $from_id on $target_user in group $chat_id");
        } else {
            $this->telegram->sendMessage($chat_id, "⚠️ این کاربر قبلاً ساکت شده است.");
        }
    }

    /**
     * استخراج کاربر هدف از ریپلای یا متن
     */
    private function getTargetUser($message)
    {
        // اگر ریپلای شده باشد
        if (isset($message['reply_to_message']['from']['id'])) {
            return $message['reply_to_message']['from']['id'];
        }

        // بررسی متن برای @username یا ID عددی
        $text = $message['text'] ?? '';
        $parts = explode(' ', $text);
        if (isset($parts[1])) {
            $target = trim($parts[1]);
            if (strpos($target, '@') === 0) {
                // دریافت اطلاعات کاربر با username
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

    /**
     * بررسی ادمین بودن کاربر در گروه
     */
    private function isGroupAdmin($group_id, $user_id)
    {
        // از جدول bot_admins یا bot_sub_admins
        $stmt = $this->db->prepare("SELECT id FROM bot_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return true;
        }
        // همچنین می‌توانید sub_admins را نیز بررسی کنید
        $stmt = $this->db->prepare("SELECT id FROM bot_sub_admins WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
}