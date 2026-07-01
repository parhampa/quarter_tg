-- ============================================================
-- دیتابیس: quarter_tg
-- توضیحات: ساختار اولیه دیتابیس برای ربات تلگرام
-- تاریخ: 2026-07-01
-- ============================================================

-- ============================================================
-- جدول users (کاربران)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` BIGINT NOT NULL PRIMARY KEY COMMENT 'شناسه کاربر در تلگرام',
    `first_name` VARCHAR(255) NOT NULL COMMENT 'نام',
    `last_name` VARCHAR(255) DEFAULT NULL COMMENT 'نام خانوادگی',
    `username` VARCHAR(255) DEFAULT NULL COMMENT 'یوزرنیم تلگرام',
    `language_code` VARCHAR(10) DEFAULT NULL COMMENT 'کد زبان',
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'آیا ربات است؟',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    INDEX `idx_username` (`username`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اطلاعات کاربران';

-- ============================================================
-- جدول groups (گروه‌ها)
-- ============================================================
CREATE TABLE IF NOT EXISTS `groups` (
    `group_id` BIGINT NOT NULL PRIMARY KEY COMMENT 'شناسه گروه در تلگرام',
    `title` VARCHAR(255) NOT NULL COMMENT 'نام گروه',
    `username` VARCHAR(255) DEFAULT NULL COMMENT 'یوزرنیم گروه',
    `type` VARCHAR(50) NOT NULL DEFAULT 'supergroup' COMMENT 'نوع: group, supergroup, channel',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اطلاعات گروه‌ها';

-- ============================================================
-- جدول admins (ادمین‌های گروه)
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `user_id` BIGINT NOT NULL COMMENT 'شناسه کاربر',
    `level` ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin' COMMENT 'سطح دسترسی',
    `added_by` BIGINT NOT NULL DEFAULT 0 COMMENT 'افزاینده (شناسه کاربر)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'وضعیت فعال/غیرفعال',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_level` (`level`),
    INDEX `idx_is_active` (`is_active`),
    CONSTRAINT `fk_admins_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ادمین‌های گروه';

-- ============================================================
-- جدول group_members (اعضای گروه)
-- ============================================================
CREATE TABLE IF NOT EXISTS `group_members` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `user_id` BIGINT NOT NULL COMMENT 'شناسه کاربر',
    `joined_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'زمان پیوستن',
    `left_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'زمان خروج',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'آیا عضو است؟',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_active` (`is_active`),
    CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اعضای گروه';

-- ============================================================
-- جدول group_locks (قفل‌های گروه)
-- ============================================================
CREATE TABLE IF NOT EXISTS `group_locks` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `lock_type` VARCHAR(50) NOT NULL COMMENT 'نوع قفل',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'وضعیت فعال/غیرفعال',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    UNIQUE KEY `uk_group_lock` (`group_id`, `lock_type`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_lock_type` (`lock_type`),
    INDEX `idx_is_active` (`is_active`),
    CONSTRAINT `fk_group_locks_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='قفل‌های گروه';

-- ============================================================
-- جدول warns (اخطارهای کاربران)
-- ============================================================
CREATE TABLE IF NOT EXISTS `warns` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `user_id` BIGINT NOT NULL COMMENT 'شناسه کاربر',
    `admin_id` BIGINT NOT NULL DEFAULT 0 COMMENT 'شناسه ادمین صادرکننده',
    `reason` TEXT DEFAULT NULL COMMENT 'دلیل اخطار',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `expires_at` TIMESTAMP NOT NULL COMMENT 'زمان انقضا',
    INDEX `idx_group_user` (`group_id`, `user_id`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_warns_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_warns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='اخطارهای کاربران';

-- ============================================================
-- جدول bans (کاربران بن‌شده)
-- ============================================================
CREATE TABLE IF NOT EXISTS `bans` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `user_id` BIGINT NOT NULL COMMENT 'شناسه کاربر',
    `admin_id` BIGINT NOT NULL DEFAULT 0 COMMENT 'شناسه ادمین بن‌کننده',
    `reason` TEXT DEFAULT NULL COMMENT 'دلیل بن',
    `is_permanent` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'آیا دائمی است؟',
    `until_date` TIMESTAMP NULL DEFAULT NULL COMMENT 'زمان پایان بن',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    INDEX `idx_group_user` (`group_id`, `user_id`),
    INDEX `idx_until_date` (`until_date`),
    CONSTRAINT `fk_bans_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='کاربران بن‌شده';

-- ============================================================
-- جدول group_settings (تنظیمات گروه)
-- ============================================================
CREATE TABLE IF NOT EXISTS `group_settings` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `setting_key` VARCHAR(100) NOT NULL COMMENT 'کلید تنظیمات',
    `setting_value` TEXT NOT NULL COMMENT 'مقدار تنظیمات',
    `updated_by` BIGINT NOT NULL DEFAULT 0 COMMENT 'شناسه کاربر به‌روزرسانی‌کننده',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    UNIQUE KEY `uk_group_key` (`group_id`, `setting_key`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_setting_key` (`setting_key`),
    CONSTRAINT `fk_group_settings_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تنظیمات گروه';

-- ============================================================
-- جدول moderators (مدیران گروه - سطح پایین‌تر از ادمین)
-- ============================================================
CREATE TABLE IF NOT EXISTS `moderators` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `group_id` BIGINT NOT NULL COMMENT 'شناسه گروه',
    `user_id` BIGINT NOT NULL COMMENT 'شناسه کاربر',
    `added_by` BIGINT NOT NULL DEFAULT 0 COMMENT 'افزاینده',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'وضعیت فعال/غیرفعال',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان ثبت',
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'زمان به‌روزرسانی',
    UNIQUE KEY `uk_group_user` (`group_id`, `user_id`),
    INDEX `idx_group_id` (`group_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_active` (`is_active`),
    CONSTRAINT `fk_moderators_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`group_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_moderators_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مدیران گروه (سطح پایین‌تر از ادمین)';

-- ============================================================
-- جدول migrations (مدیریت مهاجرت‌های دیتابیس) - اختیاری
-- ============================================================
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'شناسه یکتا',
    `migration` VARCHAR(255) NOT NULL COMMENT 'نام فایل مهاجرت',
    `batch` INT NOT NULL DEFAULT 1 COMMENT 'شماره بچ',
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'زمان اجرا',
    UNIQUE KEY `uk_migration` (`migration`),
    INDEX `idx_batch` (`batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مدیریت مهاجرت‌های دیتابیس';

-- ============================================================
-- درج داده‌های اولیه (Sample Data)
-- ============================================================
INSERT IGNORE INTO `groups` (`group_id`, `title`, `type`) VALUES
(0, 'گروه پیش‌فرض', 'supergroup');