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
-- Table structure for table `bot_admins`
-- --------------------------------------------------------

CREATE TABLE `bot_admins` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_sub_admins`
-- --------------------------------------------------------

CREATE TABLE `bot_sub_admins` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `added_by` bigint(20) DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_permissions`
-- --------------------------------------------------------

CREATE TABLE `bot_permissions` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_welcome_settings`
-- --------------------------------------------------------

CREATE TABLE `bot_welcome_settings` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `message` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_group_locks`
-- --------------------------------------------------------

CREATE TABLE `bot_group_locks` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `lock_text` tinyint(1) NOT NULL DEFAULT '0',
  `lock_photo` tinyint(1) NOT NULL DEFAULT '0',
  `lock_video` tinyint(1) NOT NULL DEFAULT '0',
  `lock_gif` tinyint(1) NOT NULL DEFAULT '0',
  `lock_sticker` tinyint(1) NOT NULL DEFAULT '0',
  `lock_voice` tinyint(1) NOT NULL DEFAULT '0',
  `lock_video_note` tinyint(1) NOT NULL DEFAULT '0',
  `lock_link` tinyint(1) NOT NULL DEFAULT '0',   -- ✅ قفل لینک
  `lock_tag` tinyint(1) NOT NULL DEFAULT '0'     -- ✅ قفل تگ (جدید)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_messages`
-- --------------------------------------------------------

CREATE TABLE `bot_messages` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `message_id` int(11) NOT NULL,
  `message_text` text,
  `message_type` varchar(50) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_command_logs`
-- --------------------------------------------------------

CREATE TABLE `bot_command_logs` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `admin_id` bigint(20) NOT NULL,
  `command` varchar(100) NOT NULL,
  `params` text,
  `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_bans`
-- --------------------------------------------------------

CREATE TABLE `bot_bans` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `banned_by` bigint(20) NOT NULL,
  `reason` text,
  `banned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_mutes`
-- --------------------------------------------------------

CREATE TABLE `bot_mutes` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `muted_by` bigint(20) NOT NULL,
  `muted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `bot_warnings`
-- --------------------------------------------------------

CREATE TABLE `bot_warnings` (
  `id` int(11) NOT NULL,
  `group_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `warned_by` bigint(20) NOT NULL,
  `reason` text,
  `warned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Indexes and AUTO_INCREMENT
-- --------------------------------------------------------

ALTER TABLE `bot_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

ALTER TABLE `bot_sub_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_user` (`group_id`,`user_id`);

ALTER TABLE `bot_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_command` (`group_id`,`command`);

ALTER TABLE `bot_welcome_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_id` (`group_id`);

ALTER TABLE `bot_group_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_id` (`group_id`);

ALTER TABLE `bot_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

ALTER TABLE `bot_command_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

ALTER TABLE `bot_bans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_user` (`group_id`,`user_id`);

ALTER TABLE `bot_mutes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `group_user` (`group_id`,`user_id`);

ALTER TABLE `bot_warnings`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `bot_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_sub_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_welcome_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_group_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_command_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_mutes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `bot_warnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;