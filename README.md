# 🤖 Quarter TG — Telegram Group Management Bot

[![PHP Version](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Telegram](https://img.shields.io/badge/Telegram-Bot-0088cc.svg)](https://core.telegram.org/bots)
[![Code style](https://img.shields.io/badge/code%20style-PSR--12-9cf.svg)](https://www.php-fig.org/psr/psr-12/)
[![Tests](https://img.shields.io/badge/tests-PHPUnit-6b3e7e.svg)](https://phpunit.de)

> **A production‑ready Telegram group management bot with advanced content locks, including full support for hashtag locking, bilingual interface, and modular architecture.**

---

## 📖 Table of Contents

- [✨ Features](#-features)
- [📸 Screenshots](#-screenshots)
- [🏗️ Architecture](#️-architecture)
- [📦 Requirements](#-requirements)
- [🚀 Installation](#-installation)
- [⚙️ Configuration](#️-configuration)
- [🔒 Security](#-security)
- [📚 Command Reference](#-command-reference)
  - [Admin Management](#admin-management)
  - [User Management](#user-management)
  - [Message Management](#message-management)
  - [Content Locks](#content-locks)
  - [Other Commands](#other-commands)
- [🗄️ Database Schema](#️-database-schema)
- [🧪 Testing](#-testing)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)
- [🙏 Acknowledgements](#-acknowledgements)

---

## ✨ Features

### 🧑‍💼 Admin Management
- Add / remove **primary admins**
- Add / remove **sub‑admins**
- List all admins with role levels (`owner`, `admin`, `subadmin`)
- Promote / demote between admin and sub‑admin

### 👥 User Management
- **Ban** users with optional reason (auto‑revoke messages)
- **Unban** users
- List all banned users with date, reason, and who banned
- **Mute** users – permanent or timed (seconds, minutes, hours, days)
- **Unmute** users
- **Warn** users – auto‑ban after 3 warnings
- Remove all warnings for a user

### 💬 Message Management
- **Pin** messages (with silent mode)
- **Unpin** specific or all pinned messages
- **Delete** messages (via reply)
- **Clear** up to 5 000 messages with 24‑hour cooldown
- Get **ID** of any user or group

### 🔐 Content Locks (10 types)
| Lock Type        | English Command               | Persian Command           |
|------------------|-------------------------------|---------------------------|
| Text messages    | `/lockmsg` / `/dislockmsg`    | `قفل پیام` / `رفع قفل پیام` |
| Photos           | `/lockpic` / `/dislockpic`    | `قفل عکس` / `رفع قفل عکس`   |
| Videos           | `/lockfilm` / `/dislockfilm`  | `قفل فیلم` / `رفع قفل فیلم` |
| GIFs             | `/lockgif` / `/dislockgif`    | `قفل گیف` / `رفع قفل گیف`   |
| Stickers         | `/locksticker` / `/dislocksticker` | `قفل استیکر` / `رفع قفل استیکر` |
| Voice messages   | `/lockvoice` / `/remlockvoice`| `قفل ویس` / `رفع قفل ویس`   |
| Video notes      | `/lockvm` / `/remlockvm`      | `قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` |
| Links            | `/locklink` / `/remlocklink`  | `قفل لینک` / `رفع قفل لینک` |
| Tags (mentions)  | `/locktag` / `/remlocktag`    | `قفل تگ` / `رفع قفل تگ`     |
| **Hashtags** ⭐   | `/lockhashtag` / `/remlockhashtag` | `قفل هشتگ` / `رفع قفل هشتگ` |

### 🌐 Bilingual Interface
- Commands work in both **Persian** and **English**
- Responses are automatically shown in the same language as the command
- Easy to extend with new translations

### 🔐 Security & Performance
- Webhook **secret token** validation
- Prepared statements (PDO) to prevent SQL injection
- Role‑based access control (RBAC)
- **24‑hour cooldown** on `/clear` to prevent abuse
- Self‑protection: bot cannot be banned, muted, or warned by anyone
- File‑based **caching** with configurable TTL (default 5 min)
- Comprehensive **logging** with rotation (5 backup files)
- Optional IP whitelisting for webhook endpoints

---

## 🏗️ Architecture

```
quarter_tg/
├── config/
│   └── config.php                  # Main settings (bot token, DB, cache, logs)
├── src/
│   ├── Core/                       # Core engine
│   │   ├── Bot.php                 # Main orchestrator
│   │   ├── ModuleManager.php       # Dynamic module loader with DI
│   │   ├── LockManager.php         # Content lock logic
│   │   ├── MuteManager.php         # Mute management (timed/permanent)
│   │   ├── WarningManager.php      # Warning system with auto‑ban
│   │   ├── AuthorizationManager.php# RBAC (owner/admin/subadmin/user)
│   │   ├── AdminManager.php        # Admin CRUD operations
│   │   ├── PermissionManager.php   # Advanced command permissions
│   │   ├── WelcomeManager.php      # Welcome message handling
│   │   ├── MessageLogger.php       # Message logging
│   │   ├── CommandLogger.php       # Admin command logging
│   │   ├── Database.php            # PDO database wrapper
│   │   ├── Cache.php               # File‑based cache
│   │   └── Logger.php              # Logging with rotation
│   ├── Helpers/
│   │   ├── TelegramApi.php         # Telegram Bot API wrapper (cURL)
│   │   └── LanguageHelper.php      # i18n translation
│   ├── Modules/                    # Command modules
│   │   ├── BaseLockModule.php      # Base class for all lock modules
│   │   ├── LockHashtagModule.php   # ⭐ Hashtag lock
│   │   ├── RemLockHashtagModule.php# ⭐ Hashtag unlock
│   │   ├── HelpModule.php          # Help system
│   │   ├── AddAdminModule.php
│   │   ├── RemoveAdminModule.php
│   │   ├── ListAdminsModule.php
│   │   ├── BanModule.php
│   │   ├── UnbanModule.php
│   │   ├── ListBansModule.php
│   │   ├── MuteModule.php
│   │   ├── UnmuteModule.php
│   │   ├── WarningModule.php
│   │   ├── RemoveWarningModule.php
│   │   ├── PinModule.php
│   │   ├── UnpinModule.php
│   │   ├── DeleteModule.php
│   │   ├── ClearModule.php
│   │   ├── GetIdModule.php
│   │   ├── WelcomeModule.php
│   │   └── RemWelcomeModule.php
│   └── Exceptions/                 # Custom exceptions
├── tests/                          # PHPUnit test suite
│   ├── bootstrap.php
│   ├── TestCase.php
│   └── Unit/ExampleTest.php
├── logs/                           # Log files (must be writable)
├── cache/                          # Cache files (must be writable)
├── composer.json
├── db.sql                          # Complete database schema
├── index.php                       # Webhook entry point with security checks
├── bootstrap.php                   # Dependency injection & bootstrapping
├── .htaccess                       # Security rules
├── .gitignore
├── phpunit.xml
└── README.md                       # This file
```

---

## 📦 Requirements

- **PHP** ≥ 7.4 (with `curl`, `json`, `mbstring`, `pdo`, `mysqli`)
- **MySQL** ≥ 5.7 or **MariaDB** ≥ 10.2
- **Composer**
- **Telegram Bot Token** from [@BotFather](https://t.me/BotFather)
- **HTTPS** endpoint for webhook (required by Telegram)

---

## 🚀 Installation

### 1. Clone the repository
```bash
git clone https://github.com/parhampa/quarter_tg.git
cd quarter_tg
```

### 2. Install dependencies
```bash
composer install
```

### 3. Create and configure the database
```bash
mysql -u root -p < db.sql
```

### 4. Configure the bot
```bash
cp config/config.example.php config/config.php   # If config.example exists
```
Then edit `config/config.php` and fill in:
- `bot_token` – your token from @BotFather
- `database` – host, name, user, password
- `webhook.url` – your public HTTPS URL (e.g. `https://your-domain.com/index.php`)
- `webhook.secret` – a random secret for webhook validation
- `owner_id` – your Telegram numeric ID
- `cache`, `logging` – enable/disable and paths

### 5. Set permissions
```bash
chmod -R 755 cache/ logs/
```

### 6. Set webhook
```bash
curl -F "url=https://your-domain.com/index.php" \
     -F "secret_token=your-secret-key-here" \
     https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook
```

---

## ⚙️ Configuration Reference

| Key | Description |
|-----|-------------|
| `bot_token` | Telegram bot token from @BotFather |
| `database` | MySQL connection settings |
| `webhook.url` | Public URL of your `index.php` |
| `webhook.secret` | Secret token for webhook security (optional but recommended) |
| `cache.enabled` | Enable/disable caching |
| `cache.ttl` | Cache TTL in seconds (default 300) |
| `logging.enabled` | Enable/disable logging |
| `logging.path` | Log file path |
| `logging.level` | Minimum log level (`debug`, `info`, `warning`, `error`) |
| `owner_id` | Telegram numeric ID of the super admin |
| `default_language` | Default language (`fa` or `en`) |
| `command_map` | Command-to-module mapping (both English & Persian) |

---

## 🔒 Security

- **Webhook secret token** – only requests with the correct `X-Telegram-Bot-Api-Secret-Token` header are processed.
- **Prepared statements** – all database queries use PDO prepared statements to prevent SQL injection.
- **RBAC** – users are categorised as `owner`, `admin`, `subadmin`, or `user`. Admins cannot act on other admins.
- **Rate limiting** – `/clear` has a 24‑hour cooldown per user per group.
- **Self‑protection** – the bot cannot be banned, muted, or warned by anyone, including the owner.
- **Optional IP whitelisting** – you can enable Telegram’s official IP ranges in `index.php`.

---

## 📚 Command Reference

### Admin Management
| Command | Description | Persian |
|---------|-------------|---------|
| `/addadmin @username` | Add a new primary admin | `ست ادمین` |
| `/remadmin @username` | Remove an admin | `حذف ادمین` |
| `/listadmin` | List all admins with roles | `لیست ادمین‌ها` |

### User Management
| Command | Description | Persian |
|---------|-------------|---------|
| `/ban @username [reason]` | Ban a user | `بن` |
| `/unban @username` | Unban a user | `آن‌بن` |
| `/listbans` | List banned users | `لیست بن‌ها` |
| `/mute @username [duration]` | Mute user (duration: 60, 5m, 2h, 1d) | `سکوت` |
| `/unmute @username` | Unmute a user | `حذف سکوت` |
| `/warning @username [reason]` | Give a warning (auto‑ban after 3) | `اخطار` |
| `/remwarning @username` | Remove all warnings | `حذف اخطار` |

### Message Management
| Command | Description | Persian |
|---------|-------------|---------|
| `/pin` (reply) | Pin the replied message | `پین` |
| `/rempin` (reply) | Unpin a specific message | `حذف پین` |
| `/rempin` | Unpin all messages | — |
| `/del` (reply) | Delete the replied message | `حذف` |
| `/clear [count]` | Clear up to 5000 messages (24h cooldown) | `پاکسازی` |
| `/id` | Get your ID | `آیدی` |
| `/id @username` | Get a user's ID | — |
| `/id` (reply) | Get ID of the replied user | — |

### Content Locks
| Command | Description | Persian |
|---------|-------------|---------|
| `/lockmsg` / `/dislockmsg` | Lock / unlock text messages | `قفل پیام` / `رفع قفل پیام` |
| `/lockpic` / `/dislockpic` | Lock / unlock photos | `قفل عکس` / `رفع قفل عکس` |
| `/lockfilm` / `/dislockfilm` | Lock / unlock videos | `قفل فیلم` / `رفع قفل فیلم` |
| `/lockgif` / `/dislockgif` | Lock / unlock GIFs | `قفل گیف` / `رفع قفل گیف` |
| `/locksticker` / `/dislocksticker` | Lock / unlock stickers | `قفل استیکر` / `رفع قفل استیکر` |
| `/lockvoice` / `/remlockvoice` | Lock / unlock voice messages | `قفل ویس` / `رفع قفل ویس` |
| `/lockvm` / `/remlockvm` | Lock / unlock video notes | `قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` |
| `/locklink` / `/remlocklink` | Lock / unlock links | `قفل لینک` / `رفع قفل لینک` |
| `/locktag` / `/remlocktag` | Lock / unlock mentions | `قفل تگ` / `رفع قفل تگ` |
| **⭐ `/lockhashtag` / `/remlockhashtag`** | **Lock / unlock hashtags** | **`قفل هشتگ` / `رفع قفل هشتگ`** |

### Other Commands
| Command | Description | Persian |
|---------|-------------|---------|
| `/sayhello <message>` | Enable and set welcome message | `خوش آمد بگو` |
| `/remsayhello` | Disable welcome message | `خوش آمد نگو` |
| `/help` | Show help (bilingual) | `راهنما` |

---

## 🗄️ Database Schema

The project uses 11 tables (see `db.sql` for full schema):

| Table | Description |
|-------|-------------|
| `bot_admins` | Primary admins |
| `bot_sub_admins` | Sub‑admins |
| `bot_permissions` | Command‑level permissions |
| `bot_group_locks` | Content lock status (includes `lock_hashtag`) |
| `bot_bans` | Banned users |
| `bot_mutes` | Muted users (with expiry) |
| `bot_warnings` | User warnings (count auto‑increments) |
| `bot_welcome_settings` | Welcome message config |
| `bot_messages` | Message logs |
| `bot_command_logs` | Admin command audit trail |
| `bot_clear_cooldown` | 24‑hour cooldown for `/clear` |

---

## 🧪 Testing

The project includes a full PHPUnit test suite.

### Run all tests
```bash
vendor/bin/phpunit
```

### Run specific test suite
```bash
vendor/bin/phpunit tests/Unit/
```

### Generate coverage report
```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Test configuration
- Uses a separate test database (`quarter_tg_test`)
- Cache and logging are disabled during tests
- All data is automatically cleaned up after each test

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Coding Standards
- Follow **PSR‑12** coding style
- Write **PHPDoc** comments for all public methods
- Add **unit tests** for new features
- Update **README.md** if adding new commands

---

## 📄 License

This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgements

- [Telegram Bot API](https://core.telegram.org/bots/api) – for the amazing platform
- [PHP](https://php.net) – the language that powers it all
- [Composer](https://getcomposer.org) – dependency management
- [PHPUnit](https://phpunit.de) – testing framework

---

## 📬 Contact

- **Author**: Parham Pourmohammad
- **GitHub**: [parhampa](https://github.com/parhampa)
- **Project URL**: [https://github.com/parhampa/quarter_tg](https://github.com/parhampa/quarter_tg)

---

<div align="center">
  <sub>Built with ❤️ for the Telegram community</sub>
</div>
