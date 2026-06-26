-- ============================================================
-- دیتابیس ربات مدیریت گروه تلگرام (Quarter TG)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- جدول ادمین‌های اصلی ربات (صاحبان)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group` (`user_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول ادمین‌های فرعی (مدیران گروه)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_sub_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `added_by` bigint(20) NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group` (`user_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول مجوزهای دسترسی (اختیاری)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `command` varchar(50) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group_command` (`user_id`, `group_id`, `command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول تنظیمات خوش‌آمدگویی
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_welcome_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `message_fa` text DEFAULT NULL,
  `message_en` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول قفل‌های گروه
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_group_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `lock_type` varchar(30) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_lock` (`group_id`, `lock_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول لاگ پیام‌ها
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message_id` int(11) NOT NULL,
  `text` text DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_user` (`group_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول لاگ دستورات ادمین
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_command_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `target` varchar(100) DEFAULT NULL,
  `executed_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول کاربران بن‌شده
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `banned_by` bigint(20) NOT NULL,
  `banned_at` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول اطلاعات کاربران (اختیاری)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول کاربران ساکت‌شده (Mute)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_mutes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `muted_by` bigint(20) NOT NULL,
  `muted_at` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- جدول اخطارهای کاربران (Warning)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bot_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `warned_by` bigint(20) NOT NULL,
  `warned_at` datetime NOT NULL,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_user` (`group_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;