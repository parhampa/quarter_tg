<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * کلاس Container برای مدیریت وابستگی‌ها (Dependency Injection)
 * 
 * ویژگی‌ها:
 * - ثبت وابستگی‌ها با singleton و factory
 * - تزریق خودکار از طریق Reflection
 * - پشتیبانی از اینترفیس‌ها
 * - کش کردن نمونه‌ها
 * - مدیریت خطا
 */
class Container
{
    /** @var array کش نمونه‌های singleton */
    private array $singletons = [];

    /** @var array تعاریف وابستگی‌ها (کلاس => تعریف) */
    private array $definitions = [];

    /** @var array نقشه اینترفیس به کلاس */
    private array $aliases = [];

    /** @var array پارامترهای پیشفرض برای کلاس‌ها */
    private array $parameters = [];

    /**
     * ثبت یک کلاس به صورت Singleton
     * 
     * @param string $class نام کلاس
     * @param callable|null $factory تابع سازنده (اختیاری)
     * @return self
     */
    public function singleton(string $class, ?callable $factory = null): self
    {
        $this->definitions[$class] = [
            'type' => 'singleton',
            'factory' => $factory,
        ];
        return $this;
    }

    /**
     * ثبت یک کلاس به صورت Prototype (هر بار نمونه جدید)
     */
    public function factory(string $class, callable $factory): self
    {
        $this->definitions[$class] = [
            'type' => 'factory',
            'factory' => $factory,
        ];
        return $this;
    }

    /**
     * ثبت یک کلاس ساده (بدون Factory)
     */
    public function set(string $class): self
    {
        $this->definitions[$class] = [
            'type' => 'singleton',
            'factory' => null,
        ];
        return $this;
    }

    /**
     * ثبت یک اینترفیس به یک کلاس خاص
     */
    public function alias(string $interface, string $class): self
    {
        $this->aliases[$interface] = $class;
        return $this;
    }

    /**
     * ثبت پارامترهای پیشفرض برای یک کلاس
     * 
     * @param string $class نام کلاس
     * @param array $params آرایه پارامترها (نام => مقدار)
     */
    public function setParameters(string $class, array $params): self
    {
        $this->parameters[$class] = $params;
        return $this;
    }

    /**
     * دریافت یک نمونه از کلاس (با تزریق وابستگی‌ها)
     * 
     * @param string $class نام کلاس یا اینترفیس
     * @return object
     * @throws \RuntimeException در صورت عدم امکان ساخت
     */
    public function get(string $class): object
    {
        // اگر alias وجود داشته باشد، کلاس اصلی را پیدا کنیم
        $class = $this->resolveAlias($class);

        // بررسی singleton در کش
        if (isset($this->singletons[$class])) {
            return $this->singletons[$class];
        }

        // بررسی تعریف
        if (isset($this->definitions[$class])) {
            $definition = $this->definitions[$class];
            if ($definition['factory'] !== null) {
                $instance = $definition['factory']($this);
            } else {
                $instance = $this->build($class);
            }
            // اگر singleton بود، در کش ذخیره کنیم
            if ($definition['type'] === 'singleton') {
                $this->singletons[$class] = $instance;
            }
            return $instance;
        }

        // ساخت خودکار با Reflection
        $instance = $this->build($class);

        // ذخیره به عنوان singleton به طور پیشفرض (اختیاری)
        // برای جلوگیری از ساخت مجدد، میتوان آن را کش کرد
        // اما به طور پیشفرض برای کلاس‌های بدون تعریف، prototype در نظر گرفته میشود
        return $instance;
    }

    /**
     * ساخت یک نمونه از کلاس با تزریق وابستگی‌ها (با Reflection)
     * 
     * @param string $class نام کلاس
     * @return object
     * @throws \RuntimeException
     */
    private function build(string $class): object
    {
        try {
            $reflection = new ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw new \RuntimeException("Class {$class} is not instantiable.");
            }

            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                // بدون Constructor
                return $reflection->newInstance();
            }

            // پارامترهای Constructor را استخراج و تزریق میکنیم
            $parameters = $constructor->getParameters();
            $dependencies = [];

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();
                $paramType = $parameter->getType();

                // بررسی پارامترهای پیشفرض ثبت‌شده
                if (isset($this->parameters[$class][$paramName])) {
                    $dependencies[] = $this->parameters[$class][$paramName];
                    continue;
                }

                if ($paramType === null) {
                    // اگر نوع نداشته باشد، از مقدار پیشفرض استفاده کنیم
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new \RuntimeException(
                            "Cannot resolve parameter '{$paramName}' in {$class} (no type and no default value)."
                        );
                    }
                    continue;
                }

                $typeName = $paramType->getName();

                // اگر نوع scalar (string, int, etc.) باشد
                if ($paramType->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        throw new \RuntimeException(
                            "Cannot resolve built-in parameter '{$paramName}' (type: {$typeName}) in {$class}."
                        );
                    }
                    continue;
                }

                // اگر نوع یک کلاس یا اینترفیس باشد
                try {
                    // تلاش برای دریافت از Container
                    $dependency = $this->get($typeName);
                    $dependencies[] = $dependency;
                } catch (Throwable $e) {
                    // اگر وابستگی قابل حل نبود، خطا
                    throw new \RuntimeException(
                        "Cannot resolve dependency '{$typeName}' for parameter '{$paramName}' in {$class}: {$e->getMessage()}"
                    );
                }
            }

            // ساخت نمونه با وابستگی‌های تزریق‌شده
            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new \RuntimeException("Reflection error for class {$class}: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * حل alias (اینترفیس به کلاس)
     */
    private function resolveAlias(string $class): string
    {
        return $this->aliases[$class] ?? $class;
    }

    /**
     * بررسی وجود یک کلاس در Container
     */
    public function has(string $class): bool
    {
        $class = $this->resolveAlias($class);
        return isset($this->definitions[$class]) || isset($this->singletons[$class]);
    }

    /**
     * دریافت نمونه Singleton (اگر وجود داشته باشد)
     */
    public function getSingleton(string $class): ?object
    {
        $class = $this->resolveAlias($class);
        return $this->singletons[$class] ?? null;
    }

    /**
     * پاک کردن همه نمونه‌های Singleton (برای تست)
     */
    public function clearSingletons(): void
    {
        $this->singletons = [];
    }

    /**
     * پاک کردن همه تعاریف و کش
     */
    public function clear(): void
    {
        $this->singletons = [];
        $this->definitions = [];
        $this->aliases = [];
        $this->parameters = [];
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * ایجاد یک Container با تنظیمات پیشفرض برای پروژه
     */
    public static function createDefault(): self
    {
        $container = new self();

        // ثبت وابستگی‌های اصلی
        $container->singleton(Config::class, function (Container $c) {
            return Config::createFromEnv();
        });

        $container->singleton(Logger::class, function (Container $c) {
            $config = $c->get(Config::class);
            return new Logger(
                $config->get('log.path', __DIR__ . '/../../logs/app.log'),
                $config->get('log.level', 'info'),
                $config->get('log.max_size', 10485760),
                5
            );
        });

        $container->singleton(Database::class, function (Container $c) {
            $config = $c->get(Config::class);
            $logger = $c->get(Logger::class);
            $cache = $c->get(Cache::class);
            return new Database(
                $config->get('database.host', 'localhost'),
                $config->get('database.name', 'quarter_tg'),
                $config->get('database.username', 'root'),
                $config->get('database.password', ''),
                $config->get('database.charset', 'utf8mb4'),
                $logger,
                $cache
            );
        });

        $container->singleton(Cache::class, function (Container $c) {
            $config = $c->get(Config::class);
            $logger = $c->get(Logger::class);
            return new Cache(
                $config->get('cache.path', __DIR__ . '/../../cache'),
                $config->get('cache.ttl', 3600),
                $logger,
                true
            );
        });

        $container->singleton(TelegramApi::class, function (Container $c) {
            $config = $c->get(Config::class);
            $logger = $c->get(Logger::class);
            $token = $config->get('bot_token', '');
            if (empty($token)) {
                throw new \RuntimeException('BOT_TOKEN not set in config.');
            }
            return new TelegramApi($token, $logger);
        });

        $container->singleton(ModuleManager::class, function (Container $c) {
            $config = $c->get(Config::class);
            $logger = $c->get(Logger::class);
            $cache = $c->get(Cache::class);
            return new ModuleManager(
                $config->get('modules.path', __DIR__ . '/../Modules'),
                $config->get('modules.namespace', 'QuarterTg\\Modules\\'),
                $logger,
                $cache
            );
        });

        // ثبت Managers
        $container->singleton(\QuarterTg\Managers\UserManager::class);
        $container->singleton(\QuarterTg\Managers\AdminManager::class);
        $container->singleton(\QuarterTg\Managers\LockManager::class);
        $container->singleton(\QuarterTg\Managers\WarnManager::class);
        $container->singleton(\QuarterTg\Managers\AuthorizationManager::class);

        return $container;
    }
}