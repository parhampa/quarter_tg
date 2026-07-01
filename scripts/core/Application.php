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
 * کلاس اصلی اپلیکیشن - یکپارچه‌سازی همه اجزا
 */
class Application
{
    private Container $container;
    private Config $config;
    private Logger $logger;
    private ?EventDispatcher $eventDispatcher = null;
    private array $middlewares = [];
    private bool $booted = false;

    public function __construct(array|string|null $config = null)
    {
        $this->container = new Container();
        
        if (is_array($config)) {
            $this->config = new Config($config);
        } elseif (is_string($config)) {
            $this->config = new Config($config);
        } else {
            $this->config = Config::createFromEnv();
        }

        $this->container->singleton(Config::class, function () {
            return $this->config;
        });

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        $this->registerServices();
        $this->registerEventDispatcher();
        $this->registerModuleManager();
        $this->registerManagers();
        $this->booted = true;
    }

    private function registerServices(): void
    {
        $this->container->singleton(Logger::class, function () {
            return new Logger(
                $this->config->get('log.path', __DIR__ . '/../../logs/app.log'),
                $this->config->get('log.level', 'info'),
                $this->config->get('log.max_size', 10485760),
                5
            );
        });

        $this->logger = $this->container->get(Logger::class);

        $this->container->singleton(Cache::class, function () {
            return new Cache(
                $this->config->get('cache.path', __DIR__ . '/../../cache'),
                $this->config->get('cache.ttl', 3600),
                $this->logger,
                true
            );
        });

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

        $this->container->singleton(TelegramApi::class, function () {
            $token = $this->config->get('bot_token', '');
            if (empty($token)) {
                throw new \RuntimeException('BOT_TOKEN is not set in config.');
            }
            return new TelegramApi($token, $this->logger);
        });
    }

    private function registerEventDispatcher(): void
    {
        $this->container->singleton(EventDispatcher::class, function () {
            return new EventDispatcher();
        });
        $this->eventDispatcher = $this->container->get(EventDispatcher::class);
    }

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

    private function registerManagers(): void
    {
        $ownerId = $this->config->get('owner_id', 0);

        $this->container->singleton(UserManager::class, function () {
            return new UserManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger
            );
        });

        $this->container->singleton(AdminManager::class, function () use ($ownerId) {
            return new AdminManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger,
                $ownerId
            );
        });

        $this->container->singleton(LockManager::class, function () {
            return new LockManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger
            );
        });

        $this->container->singleton(WarnManager::class, function () {
            return new WarnManager(
                $this->container->get(Database::class),
                $this->container->get(Cache::class),
                $this->logger,
                $this->container->get(TelegramApi::class),
                $this->container->get(AuthorizationManager::class),
                $this->container->get(LockManager::class),
                $this->config->get('warn.max_warns', 3),
                $this->config->get('warn.expiry_time', 86400)
            );
        });

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

    public function handleRequest(array $update): void
    {
        try {
            foreach ($this->middlewares as $middleware) {
                if (is_callable($middleware)) {
                    $middleware($update, $this);
                }
            }

            $bot = new Bot(
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

            $bot->handleRequest();

        } catch (Throwable $e) {
            $this->logger->critical('Application error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->notifyError($e);
            throw $e;
        }
    }

    public function addMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    public function get(string $class): object
    {
        return $this->container->get($class);
    }

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
                error_log('Failed to send error notification: ' . $e2->getMessage());
            }
        }
    }

    public static function runFromWebhook(array $update, array|string|null $config = null): void
    {
        $app = new self($config);
        $app->handleRequest($update);
    }
}