# 🤖 QuarterTG - Telegram Group Management Bot

A modular, high-performance Telegram bot for advanced group management with powerful features including content locking, admin management, ban/mute/warn systems, and comprehensive security.

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![Code Style](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-blueviolet.svg)](https://phpstan.org/)

---

## 📋 Table of Contents

- [Features](#-features)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Database Setup](#-database-setup)
- [Webhook Setup](#-webhook-setup)
- [Bot Commands](#-bot-commands)
- [Project Structure](#-project-structure)
- [Security](#-security)
- [Testing](#-testing)
- [Console Commands](#-console-commands)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### Core Management
- **Ban/Unban** – Permanent or temporary bans with duration support (e.g., `1h`, `1d`)
- **Mute/Unmute** – Restrict users from sending messages, media, and polls
- **Kick** – Remove users from the group instantly
- **Warn System** – Issue warnings with auto-ban after reaching a configurable threshold (default: 3)
- **Clear Messages** – Bulk delete up to 100 messages with rate limiting protection

### Admin Management
- **Add/Remove Admins** – With role-based levels (`admin`, `super_admin`)
- **Promote/Demote** – Change admin levels on the fly
- **Admin List** – View all admins with their roles

### Content Locking
- **10 Lock Types**: Links, Tags, Hashtags, Commands, Arabic text, English text, Persian text, Spam, Stickers, Videos, Audio, Documents, Voice, Photos, GIFs
- **Lock/Unlock** – Individual or batch lock management
- **View Active Locks** – See which locks are currently enabled

### User & Group Info
- **User Info** – View user details with `/info` or `/whoami`
- **Group Info** – View group statistics with `/group`
- **Warn List** – View warnings for any user

### Security & Performance
- **Webhook Secret Token** – Validate incoming requests
- **IP Whitelisting** – Restrict access to official Telegram IPs
- **Prepared Statements** – Prevent SQL injection attacks
- **RBAC (Role-Based Access Control)** – Four levels: `owner`, `super_admin`, `admin`, `moderator`, `member`
- **Self-Protection** – Bot cannot be banned, muted, or warned
- **File-based Caching** – With TTL support and garbage collection
- **Log Rotation** – Automatic rotation with configurable file size

### Developer Experience
- **Modular Architecture** – Easy to extend with new modules
- **Dependency Injection** – Clean service management with Container
- **Event System** – Event-driven architecture for extensibility
- **Console Commands** – CLI management tool for cache, database, webhook, stats
- **Unit Tests** – Comprehensive test suite with PHPUnit
- **Code Quality** – PSR-12 compliance, PHPStan static analysis

---

## 📦 Requirements

- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- **Composer**
- **SSL Certificate** (required for Webhook)

### Required PHP Extensions
- `ext-pdo` – Database connection
- `ext-json` – JSON processing
- `ext-curl` – HTTP requests
- `ext-mbstring` – Unicode string handling

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/parhampa/quarter_tg.git
cd quarter_tg
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Open `.env` and fill in your credentials:

```env
BOT_TOKEN=your_bot_token_here
DB_PASSWORD=your_strong_password
OWNER_ID=your_telegram_id
WEBHOOK_SECRET=your_webhook_secret
```

### 4. Create Database

```sql
CREATE DATABASE quarter_tg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Run Database Migrations

```bash
php scripts/console.php db:migrate
```

### 6. Run Database Seeders (optional)

```bash
php scripts/console.php db:seed
```

### 7. Set Webhook

```bash
php scripts/console.php webhook:set --url=https://your-domain.com/webhook.php
```

### 8. Set Directory Permissions

```bash
chmod -R 755 cache logs
```

---

## ⚙️ Configuration

### Main Configuration File
Location: `config/config.php`

All configuration keys are also available as environment variables in `.env` for security.

### Key Configuration Options

| Key | Description | Default |
|-----|-------------|---------|
| `bot_token` | Telegram bot token | (required) |
| `database.host` | MySQL host | `localhost` |
| `database.name` | Database name | `quarter_tg` |
| `database.username` | Database user | `root` |
| `database.password` | Database password | (required) |
| `cache.path` | Cache directory | `./cache` |
| `cache.ttl` | Cache TTL (seconds) | `3600` |
| `log.path` | Log file path | `./logs/app.log` |
| `log.level` | Log level | `info` |
| `log.max_size` | Max log size (bytes) | `10485760` |
| `owner_id` | Bot owner's Telegram ID | (required) |
| `webhook.secret` | Webhook secret token | (optional) |
| `webhook.allowed_ips` | Allowed IPs (CIDR) | `149.154.160.0/20,91.108.4.0/22` |
| `warn.max_warns` | Max warnings before auto-ban | `3` |
| `warn.expiry_time` | Warning expiry (seconds) | `86400` |

---

## 🗄️ Database Schema

The database includes the following tables:

| Table | Description |
|-------|-------------|
| `users` | User information (ID, name, username, etc.) |
| `groups` | Group information (ID, title, type, etc.) |
| `admins` | Admin records with levels (`admin`, `super_admin`) |
| `moderators` | Moderator records (lower than admin) |
| `group_members` | Group membership tracking |
| `group_locks` | Active locks per group |
| `warns` | Warning records with expiry dates |
| `bans` | Ban history |
| `group_settings` | Group settings (welcome message, rules, etc.) |
| `migrations` | Migration version control |

---

## 🔗 Webhook Setup

### Manual Webhook Setup
```bash
php scripts/console.php webhook:set --url=https://your-domain.com/webhook.php
```

### View Webhook Status
```bash
php scripts/console.php webhook:info
```

### Delete Webhook
```bash
php scripts/console.php webhook:delete
```

### Webhook Endpoint Requirements
- **HTTPS Required** – Telegram only accepts HTTPS
- **Valid SSL Certificate** – Self-signed certificates are not accepted
- **Correct File Permissions** – Webhook file must be readable by web server

---

## 🤖 Bot Commands

### Public Commands

| Command | Description |
|---------|-------------|
| `/help` or `/start` | Show help and welcome message |
| `/ping` | Check bot status and response time |
| `/info [@username|ID]` | Show user information |
| `/whoami` | Show your own information |
| `/group` | Show group information |
| `/locks` | Show active locks |

### Admin Commands (requires `admin` level)

| Command | Description |
|---------|-------------|
| `/ban [@username|ID] [duration] [reason]` | Ban user (e.g., `/ban @user 1h spam`) |
| `/unban [@username|ID]` | Unban user |
| `/kick [@username|ID] [reason]` | Kick user from group |
| `/mute [@username|ID] [duration] [reason]` | Mute user temporarily |
| `/unmute [@username|ID]` | Unmute user |
| `/warn [@username|ID] [reason]` | Warn user |
| `/unwarn [@username|ID]` | Remove one warning |
| `/warns [@username|ID]` | Show user's warnings |
| `/clear [count]` | Delete messages (max 100) |
| `/lock [type]` | Enable a lock |
| `/unlock [type]` | Disable a lock |
| `/lockall` | Enable all locks |
| `/unlockall` | Disable all locks |
| `/settings` | Show group settings |
| `/setwelcome [text]` | Set welcome message |
| `/setrules [text]` | Set group rules |
| `/removewelcome` | Remove welcome message |
| `/removerules` | Remove group rules |
| `/stats` | Show bot statistics |

### Super Admin Commands (requires `super_admin` level)

| Command | Description |
|---------|-------------|
| `/promote [@username|ID]` | Promote to `super_admin` |
| `/demote [@username|ID]` | Demote to `admin` |

### Owner Commands (requires `owner` level)

| Command | Description |
|---------|-------------|
| `/setadmin [@username|ID] [level]` | Add admin (level: admin, super_admin) |
| `/removeadmin [@username|ID]` | Remove admin |

---

## 📂 Project Structure

```
quarter_tg/
├── src/
│   ├── Core/                 # Core framework
│   │   ├── Application.php   # Application entry point
│   │   ├── Bot.php          # Main bot logic
│   │   ├── Container.php    # Dependency injection container
│   │   ├── Config.php       # Configuration manager
│   │   ├── Database.php     # PDO database wrapper
│   │   ├── Cache.php        # File-based cache
│   │   ├── Logger.php       # Logging system with rotation
│   │   ├── TelegramApi.php  # Telegram API client
│   │   ├── ModuleManager.php # Module loader
│   │   ├── EventDispatcher.php # Event system
│   │   └── Middleware/      # HTTP middleware
│   │       ├── AuthMiddleware.php
│   │       └── LoggingMiddleware.php
│   ├── Managers/            # Data managers
│   │   ├── UserManager.php
│   │   ├── AdminManager.php
│   │   ├── LockManager.php
│   │   ├── WarnManager.php
│   │   └── AuthorizationManager.php
│   ├── Modules/             # Bot command modules
│   │   ├── BanModule.php
│   │   ├── UnbanModule.php
│   │   ├── KickModule.php
│   │   ├── MuteModule.php
│   │   ├── UnmuteModule.php
│   │   ├── WarnModule.php
│   │   ├── LockModule.php
│   │   ├── HelpModule.php
│   │   ├── InfoModule.php
│   │   ├── StatsModule.php
│   │   ├── SettingsModule.php
│   │   ├── ClearModule.php
│   │   └── ...
│   ├── Helpers/             # Utility helpers
│   │   ├── FormatHelper.php
│   │   ├── ValidationHelper.php
│   │   └── MessageHelper.php
│   └── Exceptions/          # Custom exceptions
│       ├── BaseException.php
│       ├── ApiException.php
│       ├── DatabaseException.php
│       └── ...
├── config/
│   ├── config.php          # Main configuration
│   └── config.example.php  # Example configuration
├── database/
│   ├── migrations/         # Database migrations
│   │   └── initial_schema.sql
│   └── seeders/            # Seed data
│       └── initial_data.sql
├── scripts/                # CLI scripts
│   ├── console.php        # Management console
│   └── set_webhook.php    # Webhook setup script
├── tests/                  # Unit tests
│   ├── bootstrap.php
│   ├── TestCase.php
│   └── Unit/
│       ├── ConfigTest.php
│       ├── LoggerTest.php
│       ├── DatabaseTest.php
│       └── Helpers/
│           ├── FormatHelperTest.php
│           └── ValidationHelperTest.php
├── cache/                  # Cache directory (auto-created)
├── logs/                   # Log directory (auto-created)
├── webhook.php             # Webhook entry point
├── bootstrap.php           # Bootstrapping
├── .env.example            # Environment variables example
├── composer.json           # Dependencies
├── phpunit.xml            # PHPUnit configuration
├── .htaccess              # Apache security rules
├── .gitignore             # Git ignore rules
└── README.md              # Documentation
```

---

## 🔒 Security

| Feature | Description |
|---------|-------------|
| **Webhook Secret** | Validates incoming requests with a secret token |
| **IP Whitelist** | Restricts access to official Telegram IPs |
| **Prepared Statements** | Prevents SQL injection attacks |
| **Input Validation** | All user inputs are validated |
| **RBAC** | Role-based access control for all commands |
| **Self-Protection** | Bot cannot be banned, muted, or warned |
| **Rate Limiting** | 24-hour cooldown on `/clear` command |
| **HTTPS Only** | Webhook accepts only HTTPS connections |
| **Logging** | All sensitive operations are logged |

### Recommended IP Whitelist

Add these official Telegram IP ranges to your `.env`:

```env
ALLOWED_IPS=149.154.160.0/20,91.108.4.0/22,93.190.128.0/18
```

---

## 🧪 Testing

### Run All Tests

```bash
vendor/bin/phpunit
```

### Run Specific Test File

```bash
vendor/bin/phpunit tests/Unit/ConfigTest.php
```

### Run Tests with Code Coverage

```bash
vendor/bin/phpunit --coverage-html coverage/
```

### Test Suite Coverage

| Component | Tests | Description |
|-----------|-------|-------------|
| Config | 24 | Configuration management, dot notation, environment variables |
| Logger | 15 | Logging levels, rotation, handlers, error handling |
| Database | 30 | Queries, transactions, prepared statements |
| FormatHelper | 29 | Persian digits, date formatting, Markdown |
| ValidationHelper | 34 | Input validation, sanitization |

---

## 🛠️ Console Commands

### Cache Management

```bash
# Clear cache
php scripts/console.php cache:clear

# Show cache statistics
php scripts/console.php cache:stats
```

### Database Management

```bash
# Run migrations
php scripts/console.php db:migrate

# Run migrations with fresh reset
php scripts/console.php db:migrate --fresh

# Seed database with initial data
php scripts/console.php db:seed
```

### Webhook Management

```bash
# Set webhook
php scripts/console.php webhook:set --url=https://example.com/webhook.php

# Delete webhook
php scripts/console.php webhook:delete

# Show webhook info
php scripts/console.php webhook:info
```

### Statistics

```bash
# Show bot statistics
php scripts/console.php stats:show
```

### User & Group Lists

```bash
# List users (limit 20)
php scripts/console.php user:list

# List users with custom limit
php scripts/console.php user:list --limit=50

# List groups
php scripts/console.php group:list --limit=20
```

---

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer test`)
5. Fix code style (`composer cs-fix`)
6. Run static analysis (`composer phpstan`)
7. Commit and push
8. Open a Pull Request

### Development Commands

```bash
# Run tests
composer test

# Fix code style
composer cs-fix

# Run PHPStan
composer phpstan

# Run all quality checks
composer qa
```

---

## 📄 License

This project is licensed under the **MIT License** – see the [LICENSE](LICENSE) file for details.

---

## 🙏 Credits

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [PHP](https://php.net)
- [Composer](https://getcomposer.org)
- All contributors and supporters

---

## ⭐ Support

If this project helped you, please give it a **Star** ⭐ on GitHub!

- **Issues**: [GitHub Issues](https://github.com/parhampa/quarter_tg/issues)
- **Discussions**: [GitHub Discussions](https://github.com/parhampa/quarter_tg/discussions)

---

**Made with ❤️ for the Telegram community**
