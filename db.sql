-- --------------------------------------------------------
-- Host: localhost
-- Generation Time: 
-- Server version: 5.7.33
-- PHP Version: 7.4.33
-- --------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `bot_admins` (مدیران اصلی گروه)
-- --------------------------------------------------------

CREATE TABLE `bot_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_sub_admins` (ساب‌ادمین‌ها)
-- --------------------------------------------------------

CREATE TABLE `bot_sub_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_permissions` (دسترسی‌های دستورات)
-- --------------------------------------------------------

CREATE TABLE `bot_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_command` (`group_id`,`command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_group_locks` (قفل‌های محتوایی گروه)
-- --------------------------------------------------------

CREATE TABLE `bot_group_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `lock_text` tinyint(1) NOT NULL DEFAULT '0',
  `lock_photo` tinyint(1) NOT NULL DEFAULT '0',
  `lock_video` tinyint(1) NOT NULL DEFAULT '0',
  `lock_gif` tinyint(1) NOT NULL DEFAULT '0',
  `lock_sticker` tinyint(1) NOT NULL DEFAULT '0',
  `lock_voice` tinyint(1) NOT NULL DEFAULT '0',
  `lock_video_note` tinyint(1) NOT NULL DEFAULT '0',
  `lock_link` tinyint(1) NOT NULL DEFAULT '0',
  `lock_tag` tinyint(1) NOT NULL DEFAULT '0',
  `lock_hashtag` tinyint(1) NOT NULL DEFAULT '0', -- ✅ قفل هشتگ (جدید)
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_bans` (کاربران بن‌شده)
-- --------------------------------------------------------

CREATE TABLE `bot_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `banned_by` bigint(20) NOT NULL,
  `reason` text,
  `banned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_mutes` (کاربران سکوت‌شده)
-- --------------------------------------------------------

CREATE TABLE `bot_mutes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `muted_by` bigint(20) NOT NULL,
  `reason` text,
  `until` timestamp NULL DEFAULT NULL, -- تاریخ انقضای سکوت (NULL = دائمی)
  `muted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_warnings` (اخطارهای کاربران)
-- --------------------------------------------------------

CREATE TABLE `bot_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `warned_by` bigint(20) NOT NULL,
  `reason` text,
  `count` int(11) NOT NULL DEFAULT '1', -- تعداد کل اخطارها
  `warned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_welcome_settings` (تنظیمات پیام خوش‌آمدگویی)
-- --------------------------------------------------------

CREATE TABLE `bot_welcome_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `message` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_messages` (لاگ پیام‌ها)
-- --------------------------------------------------------

CREATE TABLE `bot_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message_id` int(11) NOT NULL,
  `message_text` text,
  `message_type` varchar(50) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_command_logs` (لاگ دستورات ادمین‌ها)
-- --------------------------------------------------------

CREATE TABLE `bot_command_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `admin_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `params` text,
  `target_user` bigint(20) DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_clear_cooldown` (کول‌داون دستور پاکسازی)
-- --------------------------------------------------------

CREATE TABLE `bot_clear_cooldown` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `last_clear` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_user` (`group_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- مقداردهی اولیه (اختیاری)
-- --------------------------------------------------------

-- درج یک رکورد پیش‌فرض برای هر گروه جدید در جدول قفل‌ها
-- این کار باعث می‌شود که لاک‌ها بدون خطا کار کنند
INSERT INTO `bot_group_locks` (
  `group_id`, 
  `lock_text`, 
  `lock_photo`, 
  `lock_video`, 
  `lock_gif`, 
  `lock_sticker`, 
  `lock_voice`, 
  `lock_video_note`, 
  `lock_link`, 
  `lock_tag`, 
  `lock_hashtag`
) VALUES (
  0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
) ON DUPLICATE KEY UPDATE `group_id` = `group_id`;

-- --------------------------------------------------------
-- ایندکس‌های اضافی برای بهبود عملکرد (اختیاری)
-- --------------------------------------------------------

-- ایندکس برای جستجوی سریع‌تر در لاگ پیام‌ها
ALTER TABLE `bot_messages` ADD INDEX `idx_sent_at` (`sent_at`);

-- ایندکس برای جستجوی سریع‌تر در لاگ دستورات
ALTER TABLE `bot_command_logs` ADD INDEX `idx_executed_at` (`executed_at`);

-- ایندکس برای جستجوی سریع‌تر در جدول بن‌ها
ALTER TABLE `bot_bans` ADD INDEX `idx_banned_at` (`banned_at`);

COMMIT;