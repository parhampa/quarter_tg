Here's a **fully comprehensive README.md** for your project, covering every feature, installation step, command reference, and contribution guide in detail.

---

```markdown
# 🤖 Quarter TG – Advanced Telegram Group Management Bot

**Quarter TG** is a powerful, modular Telegram group management bot built with **PHP 7.4+** and **MySQL**. It gives you full control over your groups with a clean, extensible architecture. Add new features without touching the core – just drop in a new module!

---

## 📋 Table of Contents
- [Features](#-features)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Webhook Setup](#-webhook-setup)
- [Commands Reference](#-commands-reference)
  - [English Commands](#-english-commands)
  - [Persian Commands](#-persian-commands)
- [Project Structure](#-project-structure)
- [Extending the Bot](#-extending-the-bot)
- [Logging & Caching](#-logging--caching)
- [Security](#-security)
- [Contributing](#-contributing)
- [Contact](#-contact)
- [License](#-license)

---

## ✨ Features

### 👥 Admin Management
- **Add/Remove Admins** – by @mention or reply
- **List All Admins** – view all group administrators

### 🛡️ User Moderation & Penalties
- **Ban / Unban** – by @mention, numeric ID, or reply
- **List Banned Users** – view all banned members
- **Mute / Unmute** – mute a user; automatically deletes their last 50 messages and blocks new ones
- **Warning System** – give warnings to users; **auto‑ban** after 3 warnings
- **Remove Warnings** – clear all warnings for a user

### 🗑️ Message Management
- **Pin / Unpin** – pin or unpin a message (by reply)
- **Delete Message** – delete a single message (by reply)
- **Clear Chat** – delete last 5000 messages (24‑hour cooldown)
- **Get User ID** – retrieve numeric ID of a user

### 🔒 Content Locks (Per‑Group)
Lock or unlock the following content types:
- Text messages
- Photos
- Videos
- GIFs / Animations
- Stickers
- Voice messages
- Video notes

### 🧩 Additional Features
- **Welcome Message** – enable/disable per group (customizable)
- **Bilingual Support** – responds in Persian or English based on command language
- **Bilingual Help** – Persian with `راهنما`, English with `/help` (admins only)
- **Full Logging** – all messages and admin commands stored in database
- **Modular Architecture** – easily add new commands without modifying core
- **Role‑Based Permissions** – owner, group admin, and sub‑admin levels
- **File‑Based Caching** – improves performance and reduces DB load

---

## 🚀 Installation

### 1. Prerequisites
- **PHP 7.4** or higher (PHP 8.x recommended)
- **MySQL** 5.7+ or **MariaDB** 10.2+
- **cURL** extension enabled
- **Composer** (optional, but recommended for dependency management)
- A Telegram bot token from [@BotFather](https://t.me/BotFather)

### 2. Clone the Repository
```bash
git clone https://github.com/parhampa/quarter_tg.git
cd quarter_tg
```

### 3. Set Up the Database
Import the database schema:
```bash
mysql -u your_db_user -p your_database_name < db.sql
```

This creates all required tables:
- `bot_admins` – main admins
- `bot_sub_admins` – group admins
- `bot_permissions` – per‑command permissions
- `bot_welcome_settings` – welcome message settings
- `bot_group_locks` – content lock settings
- `bot_messages` – message logs
- `bot_command_logs` – command logs
- `bot_bans` – banned users
- `bot_mutes` – muted users
- `bot_warnings` – warning records

### 4. Install Dependencies (Optional)
If you use Composer:
```bash
composer install
```
Otherwise, the built‑in autoloader will work.

### 5. Configure the Bot
Edit `config/config.php` and fill in your details:
```php
<?php
return [
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',
    'db' => [
        'host'     => 'localhost',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
        'database' => 'quarter_tg',
        'charset'  => 'utf8mb4',
    ],
    'webhook_secret' => 'your_random_secret_string', // optional but recommended
    'logs_dir' => __DIR__ . '/../logs',
    'cache_dir' => __DIR__ . '/../cache',
    'command_map' => [
        // All command mappings (already pre‑configured)
        // Add custom commands here
    ],
];
```

### 6. Set Permissions
Ensure the `logs/` and `cache/` directories are writable:
```bash
chmod 755 logs cache
```

---

## 🔗 Webhook Setup

Point Telegram to your bot’s `index.php` file. Make sure the URL is publicly accessible.

**Set the webhook:**
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://your-domain.com/path/to/quarter_tg/index.php
```

**For extra security (using webhook secret):**
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://your-domain.com/path/to/quarter_tg/index.php?secret=your_random_secret_string
```

**Verify the webhook:**
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo
```

> 💡 If you're testing locally, you can use [ngrok](https://ngrok.com/) to expose your local server.

---

## 📖 Commands Reference

### 🇬🇧 English Commands

| Command | Description |
|---------|-------------|
| `/help` | Show full help (admins only) |
| `/addadmin` @user | Add a new admin |
| `/remadmin` @user | Remove an admin |
| `/listadmin` | List all admins |
| `/ban` @user | Ban a user |
| `/unban` @user | Unban a user |
| `/listbans` | List banned users |
| `/mute` @user | Mute a user (deletes last 50 messages) |
| `/unmute` @user | Unmute a user |
| `/warning` @user | Give a warning (auto‑ban after 3) |
| `/remwarning` @user | Remove all warnings for a user |
| `/pin` (reply) | Pin a message |
| `/rempin` | Unpin a message |
| `/del` (reply) | Delete a message |
| `/clear` | Clear last 5000 messages (24h cooldown) |
| `/id` | Get user ID |
| `/lockmsg` / `/dislockmsg` | Lock / Unlock text messages |
| `/lockpic` / `/dislockpic` | Lock / Unlock photos |
| `/lockfilm` / `/dislockfilm` | Lock / Unlock videos |
| `/lockgif` / `/dislockgif` | Lock / Unlock GIFs |
| `/locksticker` / `/dislocksticker` | Lock / Unlock stickers |
| `/lockvoice` / `/remlockvoice` | Lock / Unlock voice messages |
| `/lockvm` / `/remlockvm` | Lock / Unlock video notes |
| `/sayhello` / `/remsayhello` | Enable / Disable welcome message |

### 🇮🇷 Persian Commands (same functionality)

| Command | Description |
|---------|-------------|
| `راهنما` | Show full help (admins only) |
| `ست ادمین` @user | Add admin |
| `حذف ادمین` @user | Remove admin |
| `لیست ادمین‌ها` | List admins |
| `بن` @user | Ban |
| `آن‌بن` @user | Unban |
| `لیست بن‌ها` | List bans |
| `سکوت` @user | Mute |
| `حذف سکوت` @user | Unmute |
| `اخطار` @user | Give warning |
| `حذف اخطار` @user | Remove warnings |
| `پین` (reply) | Pin |
| `حذف پین` | Unpin |
| `حذف` (reply) | Delete |
| `پاکسازی` | Clear |
| `آیدی` | Get ID |
| `قفل پیام` / `رفع قفل پیام` | Lock / Unlock text |
| `قفل عکس` / `رفع قفل عکس` | Lock / Unlock photos |
| `قفل فیلم` / `رفع قفل فیلم` | Lock / Unlock videos |
| `قفل گیف` / `رفع قفل گیف` | Lock / Unlock GIFs |
| `قفل استیکر` / `رفع قفل استیکر` | Lock / Unlock stickers |
| `قفل ویس` / `رفع قفل ویس` | Lock / Unlock voice |
| `قفل ویدئو مسیج` / `رفع قفل ویدئو مسیج` | Lock / Unlock video notes |
| `خوش آمد بگو` / `خوش آمد نگو` | Enable / Disable welcome |

---

## 📁 Project Structure

```
quarter_tg/
├── config/
│   └── config.php              # Main configuration (DB, token, command map)
├── src/
│   ├── Core/                   # Core engine
│   │   ├── Bot.php             # Main orchestrator
│   │   ├── MuteManager.php     # Mute/Unmute logic
│   │   ├── WarningManager.php  # Warning/Auto‑ban logic
│   │   ├── AuthorizationManager.php
│   │   ├── AdminManager.php
│   │   ├── WelcomeManager.php
│   │   ├── LockManager.php
│   │   ├── MessageLogger.php
│   │   ├── CommandLogger.php
│   │   ├── ModuleManager.php   # Module loader
│   │   ├── RequestHandler.php
│   │   ├── Database.php        # MySQL wrapper
│   │   ├── Cache.php           # File‑based cache
│   │   └── Logger.php          # Logging system
│   ├── Modules/                # All command modules
│   │   ├── HelpModule.php
│   │   ├── MuteModule.php
│   │   ├── UnmuteModule.php
│   │   ├── WarningModule.php
│   │   ├── RemoveWarningModule.php
│   │   ├── AddAdminModule.php
│   │   ├── RemoveAdminModule.php
│   │   ├── ListAdminsModule.php
│   │   ├── BanModule.php
│   │   ├── UnbanModule.php
│   │   ├── ListBansModule.php
│   │   ├── PinModule.php
│   │   ├── UnpinModule.php
│   │   ├── DeleteModule.php
│   │   ├── ClearModule.php
│   │   ├── GetIdModule.php
│   │   ├── Lock*Module.php     # All lock/unlock modules
│   │   └── ...
│   ├── Helpers/                # API wrappers & utilities
│   │   ├── TelegramApi.php
│   │   └── LanguageHelper.php
│   └── Exceptions/
│       └── ModuleNotFoundException.php
├── logs/                       # Log files (writable)
├── cache/                      # Cache files (writable)
├── bootstrap.php               # Initialization & dependency injection
├── index.php                   # Webhook entry point
├── db.sql                      # Complete database schema
├── .htaccess                   # Security rules
├── README.md                   # This file
└── LICENSE                     # MIT License
```

---

## 🛠️ Extending the Bot

### Adding a New Command
1. Create a new PHP class in `src/Modules/` (e.g., `MyCommandModule.php`).
2. Implement an `execute($message, $params)` method.
3. Register it in `config/config.php` under the `command_map` array.

**Example:**
```php
// src/Modules/MyCommandModule.php
<?php
namespace Modules;

class MyCommandModule
{
    private $telegram;
    private $db;
    private $logger;

    public function __construct($telegram, $db, $logger)
    {
        $this->telegram = $telegram;
        $this->db = $db;
        $this->logger = $logger;
    }

    public function execute($message, $params)
    {
        $chat_id = $message['chat']['id'];
        $this->telegram->sendMessage($chat_id, "Hello! This is my custom command.");
    }
}
```

**Register in `config/config.php`:**
```php
'command_map' => [
    // ...
    'mycommand' => 'MyCommandModule',
    'دستورمن'   => 'MyCommandModule',
],
```

### Adding a New Language
The bot already supports Persian and English. To add another language:
1. Extend the `LanguageHelper` class.
2. Add translations for all responses.
3. Modify the command detection logic to recognise the new language.

---

## 📊 Logging & Caching

### Logging
- **Message Logs** – stored in `bot_messages` table (all messages)
- **Command Logs** – stored in `bot_command_logs` table (admin actions)
- **File Logs** – written to `logs/bot.log` for debugging

### Caching
- **File‑Based Cache** – stored in `cache/` directory
- Reduces database queries for frequently accessed settings (welcome messages, locks, etc.)
- Cache TTL: 300 seconds (configurable)

---

## 🔒 Security

- **Webhook Secret** – optional but recommended to verify incoming requests
- **Role‑Based Access** – only owners, admins, and sub‑admins can execute privileged commands
- **Prevention of Self‑Moderation** – admins cannot mute, warn, or ban other admins
- **Rate Limiting** – `clear` command has a 24‑hour cooldown
- **SQL Injection Protection** – all queries use prepared statements
- **HTTPS Recommended** – always use HTTPS for webhook endpoints

---

## 🤝 Contributing

Contributions are welcome! Whether it's a bug fix, new feature, or documentation improvement:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -am 'Add some feature'`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

**Guidelines:**
- Follow PSR-12 coding standards.
- Write clear, commented code.
- Update the README if you add new features.
- Test your changes before submitting.

---

## 📬 Contact

- **GitHub**: [parhampa](https://github.com/parhampa)
- **Telegram**: [@parhamtrojan](https://t.me/parhamtrojan)
- **Issues**: [GitHub Issues](https://github.com/parhampa/quarter_tg/issues)

Feel free to reach out for support, feature requests, or collaboration!

---

## 📄 License

This project is open‑source and available under the **MIT License**.

Copyright (c) 2025 Parham PA

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

**Thank you for using Quarter TG! 🚀**  
_Keep your groups safe, organized, and fun._
```

---

This README is now **fully comprehensive**, covering every aspect of your project. Just copy and paste it into your `README.md` file, commit, and push. Let me know if you need any adjustments!
