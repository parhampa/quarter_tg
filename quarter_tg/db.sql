CREATE DATABASE IF NOT EXISTS `bot_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bot_db`;

-- جدول کاربران (اختیاری)
CREATE TABLE IF NOT EXISTS `bot_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `language_code` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول ادمین‌ها
CREATE TABLE IF NOT EXISTS `bot_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `group_id` bigint(20) DEFAULT NULL,
  `role` enum('owner','admin') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_group` (`user_id`, `group_id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول مدیران زیرمجموعه
CREATE TABLE IF NOT EXISTS `bot_sub_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول دسترسی‌های مستقیم
CREATE TABLE IF NOT EXISTS `bot_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `commands` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول تنظیمات خوش‌آمدگویی
CREATE TABLE IF NOT EXISTS `bot_welcome_settings` (
  `group_id` bigint(20) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `language` varchar(2) NOT NULL DEFAULT 'en',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول قفل‌های گروه
CREATE TABLE IF NOT EXISTS `bot_group_locks` (
  `group_id` bigint(20) NOT NULL,
  `lock_messages` tinyint(1) NOT NULL DEFAULT 0,
  `lock_stickers` tinyint(1) NOT NULL DEFAULT 0,
  `lock_photos` tinyint(1) NOT NULL DEFAULT 0,
  `lock_videos` tinyint(1) NOT NULL DEFAULT 0,
  `lock_gifs` tinyint(1) NOT NULL DEFAULT 0,
  `lock_voice` tinyint(1) NOT NULL DEFAULT 0,
  `lock_video_notes` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول ذخیره پیام‌ها (لاگ)
CREATE TABLE IF NOT EXISTS `bot_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `chat_title` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `message_id` int(11) NOT NULL,
  `text` text DEFAULT NULL,
  `timestamp_ms` bigint(20) NOT NULL,
  `reply_to_user_id` bigint(20) DEFAULT NULL,
  `reply_to_username` varchar(255) DEFAULT NULL,
  `reply_to_first_name` varchar(255) DEFAULT NULL,
  `reply_to_last_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  KEY `user_id` (`user_id`),
  KEY `timestamp_ms` (`timestamp_ms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول لاگ دستورات
CREATE TABLE IF NOT EXISTS `bot_command_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `chat_id` bigint(20) NOT NULL,
  `chat_title` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `command` varchar(100) NOT NULL,
  `arguments` text DEFAULT NULL,
  `timestamp_ms` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_id` (`chat_id`),
  KEY `user_id` (`user_id`),
  KEY `command` (`command`),
  KEY `timestamp_ms` (`timestamp_ms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول بن‌ها (جدید)
CREATE TABLE IF NOT EXISTS `bot_bans` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `banned_by` bigint(20) NOT NULL,
  `banned_by_username` varchar(255) DEFAULT NULL,
  `banned_by_name` varchar(255) DEFAULT NULL,
  `group_id` bigint(20) NOT NULL,
  `group_title` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `banned_at` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_group` (`user_id`, `group_id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- داده‌های نمونه
INSERT INTO `bot_admins` (user_id, group_id, role) VALUES (123456789, NULL, 'owner');
INSERT INTO `bot_sub_admins` (user_id, created_by) VALUES (987654321, 123456789);