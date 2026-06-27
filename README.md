# 🚀 Quarter TG

**A modular Telegram group management bot written in PHP 7.4+ with MySQL**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](http://makeapullrequest.com)

---

## 📖 Table of Contents

- [Introduction](#-introduction)
- [Features](#-features)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Commands](#-commands)
- [Project Structure](#-project-structure)
- [Security](#-security)
- [Contributing](#-contributing)
- [License](#-license)
- [Support](#-support)

---

## 📌 Introduction

**Quarter TG** is a powerful, modular, and easy-to-extend Telegram bot designed to give group administrators full control over their communities. It supports **bilingual commands** (English and Persian) and includes a wide range of moderation tools—from banning and muting to content locking and automated welcome messages.

With its clean architecture and dependency injection, you can add new features without touching the core code—just drop a new module into the `Modules/` directory and register it in the configuration.

---

## ✨ Features

### Core Management
- **Admin Management**: Add/remove/list administrators with granular permissions.
- **User Moderation**: Ban, unban, mute, unmute, warn (auto‑ban after 3 warnings).
- **Message Control**: Pin, unpin, delete single messages, or bulk‑delete up to 5000 messages (with a 24‑hour cooldown).
- **Welcome Messages**: Enable/disable automatic welcome messages for new members.

### Content Locks (Per‑Group)
Prevent non‑admin users from sending specific types of content:

| Lock Type          | Command (English)       | Command (Persian)        |
|--------------------|-------------------------|--------------------------|
| Text messages      | `/lockmsg` / `/dislockmsg` | `قفل پیام` / `رفع قفل پیام` |
| Photos             | `/lockpic` / `/dislockpic` | `قفل عکس` / `رفع قفل عکس` |
| Videos             | `/lockfilm` / `/dislockfilm` | `قفل فیلم` / `رفع قفل فیلم` |
| GIFs               | `/lockgif` / `/dislockgif` | `قفل گیف` / `رفع قفل گیف` |
| Stickers           | `/locksticker` / `/dislocksticker` | `قفل استیکر` / `رفع قفل استیکر` |
| Voice messages     | `/lockvoice` / `/remlockvoice` | `قفل ویس` / `رفع قفل ویس` |
| Video notes        | `/lockvm` / `/remlockvm` | `قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` |
| **Links** (NEW)    | `/locklink` / `/remlocklink` | `قفل لینک` / `رفع قفل لینک` |
| **Tags/Mentions** (NEW) | `/locktag` / `/remlocktag` | `قفل تگ` / `رفع قفل تگ` |

### Additional Highlights
- **Bilingual Support**: Commands can be issued in English or Persian; the bot replies in the same language.
- **Full Logging**: Every message and admin command is logged to the database for auditing.
- **File‑Based Caching**: Reduces database load with configurable TTL (default 300s).
- **Secure by Design**: Prepared statements prevent SQL injection; role‑based access control (Owner, Group Admin, Sub‑Admin) prevents privilege escalation.
- **Modular Architecture**: Add new commands by creating a module class and registering it—no core changes needed.

---

## 📋 Prerequisites

- **PHP** 7.4 or higher (with `curl`, `json`, `mysqli`, `mbstring` extensions)
- **MySQL** 5.7 or higher (or MariaDB 10.2+)
- **Composer** (for dependency management)
- A **Telegram Bot Token** (obtain from [@BotFather](https://t.me/botfather))
- A **public HTTPS endpoint** for the webhook (e.g., a VPS or hosting with SSL)

---

## 🛠 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/parhampa/quarter_tg.git
cd quarter_tg
```

### 2. Install Dependencies
```bash
composer install
```
> If you don't have Composer, download it from [getcomposer.org](https://getcomposer.org/).

### 3. Set Up the Database
- Create a new MySQL database (e.g., `quarter_tg`).
- Import the schema:
  ```bash
  mysql -u root -p quarter_tg < db.sql
  ```

### 4. Configure the Bot
Copy the example configuration and edit it:
```bash
cp config/config.example.php config/config.php
```
Edit `config/config.php` with your:
- Telegram Bot Token
- Database credentials
- Webhook URL and secret (recommended)
- Owner ID (your Telegram user ID)
- Cache and logging preferences

### 5. Set Webhook
Run the following command in your terminal (replace placeholders):
```bash
curl -F "url=https://your-domain.com/quarter_tg/index.php" \
     -F "secret_token=your-secret-key" \
     https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook
```
> Replace `<YOUR_BOT_TOKEN>` with your actual bot token.

### 6. Set Permissions
Ensure the `cache/` and `logs/` directories are writable:
```bash
chmod 755 cache logs
```

### 7. Test the Bot
Send `/help` to your bot in a group. It should respond with the available commands.

---

## ⚙️ Configuration

The main configuration file is `config/config.php`. Below are the key options:

| Key                | Description                                                                 |
|--------------------|-----------------------------------------------------------------------------|
| `bot_token`        | Your Telegram bot token from @BotFather.                                   |
| `database`         | MySQL connection settings (host, name, user, password, charset).           |
| `webhook.url`      | Public URL where your bot is hosted (e.g., `https://example.com/index.php`). |
| `webhook.secret`   | Optional secret token for verifying incoming webhook requests.             |
| `cache.enabled`    | Enable/disable file‑based caching.                                        |
| `cache.ttl`        | Cache lifetime in seconds (default 300).                                  |
| `logging.enabled`  | Whether to log bot activity to `logs/bot.log`.                            |
| `owner_id`         | Your Telegram user ID (super admin).                                      |
| `default_language` | Default reply language (`fa` or `en`).                                   |
| `command_map`      | Associates command strings (English & Persian) to module classes.         |

> **Important**: Never commit `config.php` with real credentials to a public repository!

---

## 📝 Commands

Below is the complete list of commands. All commands also work in Persian (as shown in the table).

### Administration

| Command (English)      | Command (Persian)      | Description                        |
|------------------------|------------------------|------------------------------------|
| `/addadmin @user`      | `ست ادمین @user`       | Add a user as group admin.         |
| `/remadmin @user`      | `حذف ادمین @user`      | Remove a user from admin list.     |
| `/listadmin`           | `لیست ادمین‌ها`        | List all group admins.             |

### User Moderation

| Command (English)      | Command (Persian)      | Description                                  |
|------------------------|------------------------|----------------------------------------------|
| `/ban @user`           | `بن @user`             | Ban a user from the group.                   |
| `/unban @user`         | `آن‌بن @user`          | Unban a user.                                |
| `/listbans`            | `لیست بن‌ها`           | List all banned users.                       |
| `/mute @user`          | `سکوت @user`           | Mute a user (deletes their last 50 messages).|
| `/unmute @user`        | `حذف سکوت @user`       | Unmute a user.                               |
| `/warning @user`       | `اخطار @user`          | Give a warning (auto‑ban after 3 warnings). |
| `/remwarning @user`    | `حذف اخطار @user`      | Clear all warnings for a user.               |

### Message Management

| Command (English)      | Command (Persian)      | Description                                        |
|------------------------|------------------------|----------------------------------------------------|
| `/pin` (reply)         | `پین`                  | Pin the replied message.                           |
| `/rempin`              | `حذف پین`              | Unpin the current pinned message.                  |
| `/del` (reply)         | `حذف`                  | Delete the replied message.                        |
| `/clear`               | `پاکسازی`              | Delete last 5000 messages (24‑hour cooldown).      |
| `/id` (reply or none)  | `آیدی`                 | Get the ID of a user or the group.                 |

### Content Locks

| Command (English)      | Command (Persian)      | Description                                        |
|------------------------|------------------------|----------------------------------------------------|
| `/lockmsg` / `/dislockmsg` | `قفل پیام` / `رفع قفل پیام` | Lock/unlock text messages.                  |
| `/lockpic` / `/dislockpic` | `قفل عکس` / `رفع قفل عکس` | Lock/unlock photos.                         |
| `/lockfilm` / `/dislockfilm` | `قفل فیلم` / `رفع قفل فیلم` | Lock/unlock videos.                        |
| `/lockgif` / `/dislockgif` | `قفل گیف` / `رفع قفل گیف` | Lock/unlock GIFs.                           |
| `/locksticker` / `/dislocksticker` | `قفل استیکر` / `رفع قفل استیکر` | Lock/unlock stickers.              |
| `/lockvoice` / `/remlockvoice` | `قفل ویس` / `رفع قفل ویس` | Lock/unlock voice messages.               |
| `/lockvm` / `/remlockvm` | `قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` | Lock/unlock video notes. |
| **`/locklink` / `/remlocklink`** | **`قفل لینک` / `رفع قفل لینک`** | **Lock/unlock links in messages.** |
| **`/locktag` / `/remlocktag`**   | **`قفل تگ` / `رفع قفل تگ`**     | **Lock/unlock mentions (@username).** |

### Other

| Command (English)      | Command (Persian)      | Description                                        |
|------------------------|------------------------|----------------------------------------------------|
| `/sayhello` / `/remsayhello` | `خوش آمد بگو` / `خوش آمد نگو` | Enable/disable welcome messages.         |
| `/help`                | `راهنما`               | Show this help message.                            |

---

## 📁 Project Structure

```
quarter_tg/
├── config/
│   └── config.php              # Main configuration file
├── src/
│   ├── Core/                   # Core engine
│   │   ├── Bot.php             # Main orchestrator
│   │   ├── ModuleManager.php   # Dynamic module loader
│   │   ├── LockManager.php     # Content lock logic
│   │   ├── MuteManager.php     # Mute/unmute logic
│   │   ├── WarningManager.php  # Warning/auto‑ban logic
│   │   ├── AuthorizationManager.php  # RBAC
│   │   ├── AdminManager.php    # Admin CRUD
│   │   ├── WelcomeManager.php  # Welcome messages
│   │   ├── MessageLogger.php   # Message logging
│   │   ├── CommandLogger.php   # Command auditing
│   │   ├── Database.php        # MySQL wrapper (PDO)
│   │   ├── Cache.php           # File‑based caching
│   │   └── Logger.php          # Logging system
│   ├── Modules/                # All command modules
│   │   ├── HelpModule.php
│   │   ├── MuteModule.php
│   │   ├── BanModule.php
│   │   ├── LockLinkModule.php  # NEW
│   │   ├── RemLockLinkModule.php # NEW
│   │   ├── LockTagModule.php   # NEW
│   │   ├── RemLockTagModule.php # NEW
│   │   └── ...                 # (other modules)
│   ├── Helpers/                # API wrappers and utilities
│   │   ├── TelegramApi.php     # Telegram Bot API wrapper
│   │   └── LanguageHelper.php  # Bilingual support
│   └── Exceptions/             # Custom exceptions
│       └── ModuleNotFoundException.php
├── logs/                       # Log files (writable)
├── cache/                      # Cache files (writable)
├── bootstrap.php               # Dependency injection & init
├── index.php                   # Webhook entry point
├── db.sql                      # Complete database schema
├── .htaccess                   # Security rules
├── README.md                   # This file
└── LICENSE                     # MIT License
```

---

## 🔒 Security

- **Webhook Secret**: Configure a secret token to verify incoming requests (prevents spoofing).
- **SQL Injection Prevention**: All queries use prepared statements.
- **Role‑Based Access**: Owner, Group Admin, and Sub‑Admin levels; admins cannot act on other admins.
- **Rate Limiting**: `/clear` has a 24‑hour cooldown per group.
- **Self‑Protection**: The bot cannot be muted, banned, or warned by anyone (including owners) to avoid accidental lockout.
- **HTTPS Required**: Always host your webhook endpoint over HTTPS.

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. **Fork** the repository.
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`).
3. **Commit your changes** using [Conventional Commits](https://www.conventionalcommits.org/).
4. **Push** to your branch (`git push origin feature/amazing-feature`).
5. **Open a Pull Request** with a clear description of your changes.

Please ensure your code follows the PSR‑12 coding standard and includes appropriate comments.

---

## 📄 License

This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.

---

## 💬 Support

- **Issues**: Please report bugs or suggest features via [GitHub Issues](https://github.com/parhampa/quarter_tg/issues).
- **Telegram**: Contact the maintainer [@parhampa](https://t.me/parhampa) for direct inquiries.

---

**Made with ❤️ by Parham PA and contributors.**
