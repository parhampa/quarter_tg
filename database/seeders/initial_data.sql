-- ============================================================
-- داده‌های اولیه برای راه‌اندازی ربات
-- ============================================================

-- تنظیمات پیش‌فرض گروه
INSERT IGNORE INTO `group_settings` (`group_id`, `setting_key`, `setting_value`) VALUES
(0, 'welcome_message', '👋 به گروه خوش آمدید!'),
(0, 'rules', '1. احترام به همه اعضا\n2. بدون اسپم\n3. بدون تبلیغات');

-- قفل‌های پیش‌فرض (همه غیرفعال)
INSERT IGNORE INTO `group_locks` (`group_id`, `lock_type`, `is_active`) VALUES
(0, 'links', 0),
(0, 'tags', 0),
(0, 'hashtags', 0),
(0, 'commands', 0),
(0, 'arabic', 0),
(0, 'english', 0),
(0, 'persian', 0),
(0, 'spam', 0),
(0, 'sticker', 0),
(0, 'video', 0),
(0, 'audio', 0),
(0, 'document', 0),
(0, 'voice', 0),
(0, 'photo', 0),
(0, 'gif', 0);