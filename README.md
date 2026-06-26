# 🚀 Quarter TG – Telegram Group Management Bot

**A modular, database-driven Telegram group management bot built with PHP 7 and MySQL.**
[![PHP Version](https://img.shields.io/badge/PHP-7.0%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.6%2B-orange.svg)](https://mysql.com)
[![Telegram Bot API](https://img.shields.io/badge/Telegram%20Bot%20API-6.0%2B-blueviolet.svg)](https://core.telegram.org/bots/api)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

---

## 📖 Overview

**Quarter TG** is a powerful and extensible Telegram group management bot designed for full control over your groups. It supports **bilingual commands** (Persian 🇮🇷 and English 🇬🇧) and comes with a rich set of features including admin management, content locks, ban/unban, message logging, welcome messages, pin/unpin, and more.

Built with a **modular architecture**, you can easily add new commands without touching the core code – perfect for developers and group admins alike.

---

## ✨ Key Features

| Category | Features |
|----------|----------|
| **👤 Admin Management** | Add/remove/list admins (by mention or reply) |
| **🔒 Content Locks** | Lock text, stickers, photos, videos, GIFs, voice, and video messages |
| **🚫 User Moderation** | Ban/unban users with full logging, list banned users |
| **📌 Pin Management** | Pin/unpin messages by reply |
| **💬 Welcome Messages** | Enable/disable welcome messages per group (bilingual) |
| **🗑️ Message Control** | Delete single messages, clear with 24h cooldown |
| **🆔 User Info** | Get user ID by reply |
| **📊 Full Logging** | Log all messages (sender, group, reply info) and admin commands |
| **🌍 Bilingual** | Responds in Persian or English based on command language |
| **🧩 Modular** | Add new commands as separate modules without touching core code |
| **💾 Database Driven** | All settings stored in MySQL with caching for performance |

---

## 📋 Table of Contents

- [System Requirements](#-system-requirements)
- [Installation](#-installation)
- [Available Commands](#-available-commands)
- [Database Schema](#-database-schema)
- [Project Structure](#-project-structure)
- [Adding New Commands](#-adding-new-commands)
- [Configuration](#-configuration)
- [Security](#-security)
- [License](#-license)

---

## 🔧 System Requirements

- PHP 7.0 or higher (recommended: PHP 7.4+)
- MySQL 5.6 or higher (or MariaDB)
- Web server (Apache/Nginx) with mod_rewrite
- cURL extension enabled
- SSL certificate for HTTPS (recommended for webhook)
- Write permissions for `logs/` and `cache/` directories

---

## 📦 Installation

### 1. Clone the repository
```bash
git clone https://github.com/yourusername/quarter_tg.git
cd quarter_tg
```

### 2. Create database
Import the provided `db.sql` file:
```bash
mysql -u root -p < db.sql
```

### 3. Configure the bot
Edit `config/config.php`:
```php
'bot_token' => 'YOUR_BOT_TOKEN_HERE',  // Get from @BotFather
'db' => [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'bot_db',
],
```

### 4. Set up webhook
```bash
curl -F "url=https://your-domain.com/index.php" \
     https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook
```

### 5. Disable privacy mode
Send `/setprivacy` to [@BotFather](https://t.me/BotFather) and select **Disable** so the bot can read all messages.

### 6. Set permissions
```bash
chmod 755 logs cache
```

### 7. Add owner
Insert your Telegram user ID as `owner` in the `bot_admins` table:
```sql
INSERT INTO bot_admins (user_id, group_id, role) VALUES (123456789, NULL, 'owner');
```

---

## 🎮 Available Commands

### English Commands

| Command | Description |
|---------|-------------|
| `/start` | Start the bot |
| `/help` | Show help menu |
| `/addadmin @user` | Add user as admin (mention or reply) |
| `/remadmin @user` | Remove admin (mention or reply) |
| `/listadmin` | List all admins |
| `/pin` | Pin replied message |
| `/rempin` | Unpin replied message |
| `/id` | Get user ID of replied message |
| `/del` | Delete replied message |
| `/clear` | Clear last 5000 messages (24h cooldown) |
| `/ban @user` | Ban user (mention or reply) |
| `/unban @user` | Unban user (mention or reply) |
| `/listbans` | List banned users |
| `/lockmsg` | Lock text messages |
| `/dislockmsg` | Unlock text messages |
| `/locksticker` | Lock stickers |
| `/dislocksticker` | Unlock stickers |
| `/lockpic` | Lock photos |
| `/dislockpic` | Unlock photos |
| `/lockfilm` | Lock videos |
| `/dislockfilm` | Unlock videos |
| `/lockgif` | Lock GIFs |
| `/dislockgif` | Unlock GIFs |
| `/lockvoice` | Lock voice messages |
| `/remlockvoice` | Unlock voice messages |
| `/lockvm` | Lock video messages |
| `/remlockvm` | Unlock video messages |
| `/sayhello` | Enable welcome message |
| `/remsayhello` | Disable welcome message |

### Persian Commands 🇮🇷

| Command | Description |
|---------|-------------|
| `ست ادمین @user` | Add admin |
| `حذف ادمین @user` | Remove admin |
| `لیست ادمین‌ها` | List admins |
| `پین` | Pin replied message |
| `حذف پین` | Unpin replied message |
| `آیدی` | Get user ID |
| `حذف` | Delete replied message |
| `پاکسازی` | Clear messages (24h cooldown) |
| `بن @user` | Ban user |
| `حذف بن @user` | Unban user |
| `لیست بن‌ها` | List banned users |
| `قفل پیام` | Lock text messages |
| `حذف قفل پیام` | Unlock text messages |
| `قفل استیکر` | Lock stickers |
| `حذف قفل استیکر` | Unlock stickers |
| `قفل عکس` | Lock photos |
| `حذف قفل عکس` | Unlock photos |
| `قفل فیلم` | Lock videos |
| `حذف قفل فیلم` | Unlock videos |
| `قفل گیف` | Lock GIFs |
| `حذف قفل گیف` | Unlock GIFs |
| `قفل ویس` | Lock voice messages |
| `حذف قفل ویس` | Unlock voice messages |
| `قفل ویدئو مسیج` | Lock video messages |
| `حذف قفل ویدئو مسیج` | Unlock video messages |
| `خوش آمد بگو` | Enable welcome message |
| `حذف خوش آمدگویی` | Disable welcome message |

> **Note:** Commands marked with "mention or reply" can be used by replying to the user's message OR by typing `@username` or numeric ID.

---

## 🗄️ Database Schema

The bot uses the following tables:

| Table | Description |
|-------|-------------|
| `bot_admins` | Stores owners and bot admins |
| `bot_sub_admins` | Stores users who can manage admins |
| `bot_permissions` | Fine‑grained command permissions per user |
| `bot_welcome_settings` | Welcome message settings per group |
| `bot_group_locks` | Lock statuses per group |
| `bot_messages` | Full message logs (sender, group, reply, timestamp) |
| `bot_command_logs` | Logs all admin commands executed |
| `bot_bans` | Stores banned users per group with full details |
| `bot_users` | (Optional) Extended user info |

Full schema is available in `db.sql`.

---

## 📁 Project Structure

```
quarter_tg/
├── config/
│   └── config.php                 # Main configuration
├── src/
│   ├── Core/
│   │   ├── Bot.php                # Main bot orchestrator
│   │   ├── AuthorizationManager.php # Role-based access control
│   │   ├── AdminManager.php        # Admin DB operations
│   │   ├── WelcomeManager.php      # Welcome message management
│   │   ├── LockManager.php         # Lock management
│   │   ├── MessageLogger.php       # Message logging
│   │   ├── CommandLogger.php       # Command logging
│   │   ├── ModuleManager.php       # Module loader
│   │   ├── RequestHandler.php      # Command parser
│   │   ├── Database.php            # MySQLi wrapper
│   │   ├── Cache.php               # File‑based caching
│   │   └── Logger.php              # Logging system
│   ├── Helpers/
│   │   ├── TelegramApi.php         # Telegram API wrapper
│   │   └── LanguageHelper.php      # Bilingual detection
│   ├── Modules/                    # All command modules
│   │   ├── AddAdminModule.php
│   │   ├── RemoveAdminModule.php
│   │   ├── ListAdminsModule.php
│   │   ├── BanModule.php
│   │   ├── UnbanModule.php
│   │   ├── ListBansModule.php
│   │   ├── PinModule.php
│   │   ├── UnpinModule.php
│   │   ├── GetIdModule.php
│   │   ├── DeleteModule.php
│   │   ├── ClearModule.php
│   │   ├── SayHelloModule.php
│   │   ├── RemoveSayHelloModule.php
│   │   ├── Lock*Module.php         # All lock modules
│   │   ├── Unlock*Module.php       # All unlock modules
│   │   ├── StartModule.php
│   │   └── HelpModule.php
│   └── Exceptions/
│       └── ModuleNotFoundException.php
├── logs/                          # Log files (writable)
├── cache/                         # Cache files (writable)
├── bootstrap.php                  # Initialization
├── index.php                      # Webhook entry point
├── db.sql                         # Database schema
├── .htaccess                      # Security rules
└── README.md                      # This file
```

---

## 🧩 Adding New Commands

1. **Create a new module** in `src/Modules/` (e.g., `MyCommandModule.php`).

2. **Define the class** with a `handle` method:
```php
<?php
namespace Modules;

use Helpers\TelegramApi;
use Helpers\LanguageHelper;

class MyCommandModule
{
    public function handle(array $update, array $args, TelegramApi $api, string $command): void
    {
        $chatId = $update['message']['chat']['id'];
        $msgId = $update['message']['message_id'];
        $api->sendMessage($chatId, "Hello from my module!", $msgId);
    }
}
```

3. **Register the command** in `config/config.php` under `command_map`:
```php
'mycommand' => [
    'module' => 'MyCommandModule',
    'method' => 'handle',
    'authorized_only' => true,
    'allowed_in_private' => false,
    'required_role' => 'group_admin',
],
```

That's it! The bot will now recognize `/mycommand` (or `mycommand` in Persian) and execute your module.

---

## ⚙️ Configuration

All settings are centralized in `config/config.php`:

| Setting | Description |
|---------|-------------|
| `bot_token` | Your Telegram bot token from @BotFather |
| `db` | MySQL connection details |
| `modules_dir` | Path to modules directory |
| `log_dir` | Path to log files |
| `cache_dir` | Path to cache files |
| `cache_ttl` | Cache time‑to‑live in seconds |
| `enable_log` | Enable/disable logging |
| `log_level` | Log level (error, warning, info, debug) |
| `webhook_secret` | Optional secret token for webhook verification |
| `command_map` | Command‑to‑module mapping |

---

## 🔒 Security

- **Webhook Secret** – Set `webhook_secret` to verify incoming requests.
- **Role‑Based Access** – Commands require `public`, `group_admin`, `admin`, `admin_manager`, or `owner` roles.
- **Input Validation** – All user inputs are escaped before database queries.
- **HTTPS** – Always use HTTPS for production webhooks.
- **File Permissions** – Sensitive directories are protected via `.htaccess`.

---

## 🛠️ Troubleshooting

| Issue | Solution |
|-------|----------|
| Bot not responding | Check webhook: `https://api.telegram.org/bot<TOKEN>/getWebhookInfo` |
| Commands not recognized | Ensure Privacy Mode is disabled and bot is admin in the group |
| Permission errors | Verify user is in `bot_admins` or `bot_sub_admins` table |
| Database errors | Check credentials and import `db.sql` |
| Cache issues | Delete `cache/*.cache` files or adjust `cache_ttl` |

---

## 📄 License

This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.

---

## 🤝 Contributing

Contributions are welcome! Please submit a pull request or open an issue for bugs and feature requests.

---

## 📬 Contact

- **GitHub**: [github.com/parhampa/quarter_tg](https://github.com/parhampa/quarter_tg)
- **Telegram**: [@parhamtrojan](https://t.me/parhamtrojan)

---

**Made with ❤️ for the Telegram community.**  
**Quarter TG** – The complete group management solution. 🚀
