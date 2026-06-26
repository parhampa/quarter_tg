<?php

namespace Core;

class Bot
{
    private $db;
    private $telegram;
    private $logger;
    private $moduleManager;
    private $muteManager;
    private $config;

    public function __construct($db, $telegram, $logger, $moduleManager, $muteManager, $config)
    {
        $this->db = $db;
        $this->telegram = $telegram;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->muteManager = $muteManager;
        $this->config = $config;
    }

    /**
     * نقطه ورود پردازش درخواست از تلگرام
     */
    public function handleRequest($update)
    {
        // لاگ کردن update برای دیباگ (اختیاری)
        // $this->logger->log(json_encode($update));

        if (isset($update['message'])) {
            $message = $update['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $message['from']['id'];

            // ========== بررسی سکوت ==========
            // اگر کاربر در این گروه ساکت باشد، پیام را حذف می‌کنیم و ادامه نمی‌دهیم
            if ($this->muteManager->isMuted($chat_id, $user_id)) {
                // حذف پیام
                $this->telegram->deleteMessage($chat_id, $message['message_id']);
                // (اختیاری) ارسال اعلان خصوصی به کاربر
                // $this->telegram->sendMessage($user_id, "شما در این گروه ساکت هستید و نمی‌توانید پیام ارسال کنید.");
                return; // توقف پردازش
            }

            // ========== پردازش دستورات ==========
            if (isset($message['text'])) {
                $text = $message['text'];
                $command = $this->extractCommand($text);
                if ($command) {
                    $params = $this->extractParams($text, $command);
                    // بررسی اینکه آیا دستور در command_map وجود دارد
                    $moduleClass = $this->moduleManager->getModuleForCommand($command);
                    if ($moduleClass) {
                        // اجرای ماژول
                        $module = new $moduleClass(
                            $this->muteManager,
                            $this->telegram,
                            $this->db,
                            $this->logger
                        );
                        $module->execute($message, $params);
                        return;
                    }
                }
            }

            // ========== لاگ پیام (برای حذف هنگام سکوت و سایر کاربردها) ==========
            $this->logMessage($message);
        }

        // سایر نوع‌های update (مثلاً callback_query) را می‌توانید اضافه کنید
    }

    /**
     * استخراج دستور از متن (با پشتیبانی از /command و همچنین دستورات فارسی بدون اسلش)
     */
    private function extractCommand($text)
    {
        // اگر با / شروع شود، دستور است
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            return $parts[0];
        }
        // بررسی دستورات فارسی (که ممکن است بدون / باشند)
        // از command_map برای تشخیص استفاده می‌کنیم
        $allCommands = $this->config['command_map'] ?? [];
        foreach ($allCommands as $cmd => $module) {
            if (strpos($text, $cmd) === 0) {
                return $cmd;
            }
        }
        return null;
    }

    /**
     * استخراج پارامترهای دستور
     */
    private function extractParams($text, $command)
    {
        $params = [];
        $after = trim(substr($text, strlen($command)));
        if ($after) {
            $params['raw'] = $after;
            // می‌توانید پارامترها را تجزیه کنید
            // برای سادگی، تمام متن بعد از دستور را به عنوان reason در نظر می‌گیریم
            $params['reason'] = $after;
        }
        return $params;
    }

    /**
     * ذخیره پیام در دیتابیس برای لاگ
     */
    private function logMessage($message)
    {
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $message_id = $message['message_id'];
        $text = $message['text'] ?? '';
        $type = $this->detectMessageType($message);

        $stmt = $this->db->prepare("INSERT INTO bot_messages (group_id, user_id, message_id, text, type, sent_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiiss", $chat_id, $user_id, $message_id, $text, $type);
        $stmt->execute();
    }

    private function detectMessageType($message)
    {
        if (isset($message['text'])) return 'text';
        if (isset($message['photo'])) return 'photo';
        if (isset($message['video'])) return 'video';
        if (isset($message['document'])) return 'document';
        if (isset($message['sticker'])) return 'sticker';
        if (isset($message['voice'])) return 'voice';
        if (isset($message['video_note'])) return 'video_note';
        if (isset($message['animation'])) return 'animation';
        return 'unknown';
    }
}