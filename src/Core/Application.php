<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use QuarterTg\Managers\AdminManager;
use QuarterTg\Managers\AuthorizationManager;
use QuarterTg\Managers\LockManager;
use QuarterTg\Managers\UserManager;
use QuarterTg\Managers\WarnManager;
use Throwable;

/**
 * کلاس اصلی اپلیکیشن
 * 
 * مسئولیت‌ها:
 * - یکپارچه‌سازی همه اجزا
 * - راه‌اندازی Container و وابستگی‌ها
 * - پردازش درخواست‌های Webhook
 * - مدیریت خطا در سطح بالا
 * - پشتیبانی از Middleware
 */
class Application
{
    private Container $container;
    private Config $config;
    private Logger $logger;
    private ?EventDispatcher $eventDispatcher = null;
    private array $middlewares = [];
    private bool $booted = false;

    /**
     * @param array|string|null $config آرایه تنظیمات یا مسیر فایل کانفیگ
     */
    public function __construct(array|string|null $config = null)
    {
        $this->container = new Container();
        
        // بارگذاری کانفیگ
        if (is_array($config)) {
            $this->config = new Config($config);
        } elseif (is_string($config)) {
            $this->config = new Config($config);
        } else {
            $this->config = Config::createFromEnv();
        }

        // ثبت Config در Container
        $this->container->singleton(Config::class, function () {
            return $this->config;
        });

        $this->bootstrap();
    }

    // ============================================================
    // متدهای Bootstrap
    // ============================================================

    /**
     * راه‌اندازی اولیه اپلیکیشن
     */
    private function bootstrap(): void
    {
        // ثبت وابستگی‌های اصلی در Container
        $this->registerServices();
        
        // ثبت Event Dispatcher
        $this->registerEventDispatcher();
        
        // ثبت Module Manager
        $this->registerModuleManager();

        // ثبت Managers
        $this->registerManagers();

        $this->booted = true;
    }

    /**
     * ثبت سرویس‌های اصلی در Container
     */
    private function registerServices(): void
    {
        // Logger
        $this->container->singleton(Logger::class, function () {
            return new Logger(
                $this->config->get('log.path', __DIR__ . '/../../logs/app.log'),
                $this->config->get('log.level', 'info'),
                $this->config->get('log.max_size', 10485760),
                5
            );
        });

        // Logger را در دسترس قرار دهیم
        $this->logger = $this->container->get(Logger::class);

        // Cache
        $this->container->singleton(Cache::class, function () {
            return new Cache(
                $this->config->get('cache.path', __DIR__ . '/../../cache'),
                $this->config->get('cache.ttl', 3600),
                $this->logger,
                true
            );
        });

        // Database
        $this->container->singleton(Database::class, function () {
            return new Database(
                $this->config->get('database.host', 'localhost'),
                $this->config->get('database.name', 'quarter_tg'),
                $this->config->get('database.username', 'root'),
                $this->config->get('database.password', ''),
                $this->config->get('database.charset', 'utf8mb4'),
                $this->logger,
                $this->container->get(Cache::class)
            );
        });

        // TelegramApi
        $this->container->singleton(TelegramApi::class, function () {
            $token = $this->config->get('bot_token', '');
            if (empty($token)) {
                throw new \RuntimeException('BOT_TOKEN is not set in config.');
            }
            return new TelegramApi($token, $this->logger);
        });
    }

    /**
     * ثبت Event Dispatcher
     */
    private function registerEventDispatcher(): void
    {
        $this->container->singleton(EventDispatcher::class, function () {
            $dispatcher = new EventDispatcher();
            
            // ثبت Listenerهای پیشفرض (در صورت نیاز)
            $this->registerDefaultListeners($dispatcher);
            
            return $dispatcher;
        });

        $this->eventDispatcher = $this->container->get(EventDispatcher::class);
    }

    /**
     * ثبت Listenerهای پیشفرض
     */
    private function registerDefaultListeners(EventDispatcher $dispatcher): void
    {
        // Listener برای رویداد new_member
        $dispatcher->listen('new_member', function (Event $event) {
            $chatId = $event->get('chat_id');
            $user = $event->get('user');
            $telegram = $this->container->get(TelegramApi::class);
            
            // دریافت تنظیمات خوش‌آمدگویی از دیتابیس
            try {
                $settings = $this->getGroupSettings($chatId);
                if (!empty($settings['welcome_message'])) {
                    $message = str_replace(
                        ['{user}', '{username}', '{first_name}', '{last_name}'],
                        [
                            $user['first_name'] ?? 'کاربر',
                            $user['username'] ?? 'کاربر',
                            $user['first_name'] ?? 'کاربر',
                            $user['last_name'] ?? '',
                        ],
                        $settings['welcome_message']
                    );
                    $telegram->sendMessage($chatId, $message);
                }
            } catch (Throwable $e) {
                $this->logger->error('Welcome message failed.', ['error' => $e->getMessage()]);
            }
        }, 10);

        // Listener برای رویداد message (برای لاگ کردن پیام‌ها)
        $dispatcher->listen('message', function (Event $event) {
            $message = $event->get('message');
            $chatId = $event->get('chat_id');
            $userId = $event->get('user_id');
            $text = $event->get('text', '');
            
            $this->logger->debug('Message received.', [
                'chat' => $chatId,
                'user' => $userId,
                'text' => substr($text, 0, 100),
            ]);
        }, -100);
    }

    /**
     * ثبت Module Manager
     */
    private function registerModuleManager(): void
    {
        $this->container->singleton(ModuleManager::class, function () {
            return new ModuleManager(
                $this->config->get('modules.path', __DIR__ . '/../Modules'),
                $this->config->get('modules.namespace', 'QuarterTg\\Modules\\'),
                $this->logger,
                $this->container->get(Cache::class)
            );
        });
    }

    /**
     * ثبت Managers
     */
    private function registerManagers(): void
    {
        $ownerId = $this->config->get('owner_id', 0);
        
        // UserManager
        $this->container->singleton(UserManager::class, function () {
            return new UserManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger
            );
        });

        // AdminManager
        $this->container->singleton(AdminManager::class, function () use ($ownerId) {
            return new AdminManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger,
                $ownerId
            );
        });

        // LockManager
        $this->container->singleton(LockManager::class, function () {
            return new LockManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger
            );
        });

        // WarnManager
        $this->container->singleton(WarnManager::class, function () {
            return new WarnManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger,
                $this->container->get(TelegramApi::class),
                $this->container->get(AuthorizationManager::class),
                $this->container->get(LockManager::class),
                3, // max warns
                86400 // warn expiry (24 hours)
            );
        });

        // AuthorizationManager
        $this->container->singleton(AuthorizationManager::class, function () use ($ownerId) {
            return new AuthorizationManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger,
                $this->container->get(AdminManager::class),
                $ownerId
            );
        });
    }

    // ============================================================
    // متدهای مدیریت درخواست
    // ============================================================

    /**
     * پردازش درخواست Webhook
     * 
     * @param array $update داده‌های دریافتی از تلگرام
     */
    public function handleRequest(array $update): void
    {
        try {
            // اجرای Middlewareها (قبل)
            foreach ($this->middlewares as $middleware) {
                if (is_callable($middleware)) {
                    $middleware($update, $this);
                }
            }

            // ساخت Bot و پردازش
            $bot = $this->createBot($update);
            $bot->handleRequest();

            // اجرای Middlewareها (بعد)
            foreach ($this->middlewares as $middleware) {
                // Middleware بعد از اجرا (اختیاری)
            }

        } catch (Throwable $e) {
            $this->logger->critical('Application error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // ارسال خطا به ادمین (در صورت امکان)
            $this->notifyError($e);
            
            throw $e;
        }
    }

    /**
     * ساخت نمونه Bot با وابستگی‌های تزریق‌شده
     */
    private function createBot(array $update): Bot
    {
        return new Bot(
            $this->config->all(),
            $update,
            $this->container->get(Database::class),
            $this->container->get(Cache::class),
            $this->logger,
            $this->container->get(TelegramApi::class),
            $this->container->get(UserManager::class),
            $this->container->get(AdminManager::class),
            $this->container->get(LockManager::class),
            $this->container->get(WarnManager::class),
            $this->container->get(AuthorizationManager::class),
            $this->container->get(ModuleManager::class)
        );
    }

    // ============================================================
    // متدهای Middleware
    // ============================================================

    /**
     * افزودن Middleware
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * پاک کردن همه Middlewareها
     */
    public function clearMiddlewares(): self
    {
        $this->middlewares = [];
        return $this;
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * دریافت Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * دریافت Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * دریافت Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * دریافت EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * دریافت یک سرویس از Container
     */
    public function get(string $class): object
    {
        return $this->container->get($class);
    }

    /**
     * دریافت تنظیمات گروه از دیتابیس
     */
    private function getGroupSettings(int $chatId): array
    {
        try {
            $db = $this->container->get(Database::class);
            $settings = $db->query(
                'SELECT setting_key, setting_value FROM group_settings WHERE group_id = ?',
                [$chatId]
            );
            
            $result = [];
            if (is_array($settings)) {
                foreach ($settings as $setting) {
                    $result[$setting['setting_key']] = $setting['setting_value'];
                }
            }
            return $result;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * ارسال خطا به ادمین اصلی
     */
    private function notifyError(Throwable $e): void
    {
        $ownerId = $this->config->get('owner_id', 0);
        if ($ownerId > 0) {
            try {
                $telegram = $this->container->get(TelegramApi::class);
                $message = "🚨 **خطای بحرانی در ربات**\n\n";
                $message .= "**پیام:** " . $e->getMessage() . "\n";
                $message .= "**فایل:** " . $e->getFile() . ":" . $e->getLine() . "\n";
                $message .= "**زمان:** " . date('Y-m-d H:i:s');
                $telegram->sendMessage($ownerId, $message);
            } catch (Throwable $e2) {
                // اگر ارسال پیام خطا هم مشکل داشت، نادیده بگیرید
                error_log('Failed to send error notification: ' . $e2->getMessage());
            }
        }
    }

    // ============================================================
    // متدهای استاتیک برای راه‌اندازی سریع
    // ============================================================

    /**
     * ایجاد و اجرای اپلیکیشن از Webhook
     */
    public static function runFromWebhook(array $update, array|string|null $config = null): void
    {
        $app = new self($config);
        $app->handleRequest($update);
    }

    /**
     * ایجاد و اجرای اپلیکیشن از خط فرمان (برای تست)
     */
    public static function runFromCli(string $command, array $args = []): void
    {
        $app = new self();
        // پردازش دستورات خط فرمان
        // (قابل توسعه)
    }
}