<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * کانتینر مدیریت وابستگی‌ها (Dependency Injection Container)
 */
class Container
{
    private array $singletons = [];
    private array $definitions = [];
    private array $aliases = [];
    private array $parameters = [];

    public function singleton(string $class, ?callable $factory = null): self
    {
        $this->definitions[$class] = [
            'type' => 'singleton',
            'factory' => $factory,
        ];
        return $this;
    }

    public function factory(string $class, callable $factory): self
    {
        $this->definitions[$class] = [
            'type' => 'factory',
            'factory' => $factory,
        ];
        return $this;
    }

    public function set(string $class): self
    {
        $this->definitions[$class] = [
            'type' => 'singleton',
            'factory' => null,
        ];
        return $this;
    }

    public function alias(string $interface, string $class): self
    {
        $this->aliases[$interface] = $class;
        return $this;
    }

    public function setParameters(string $class, array $params): self
    {
        $this->parameters[$class] = $params;
        return $this;
    }

    public function get(string $class): object
    {
        $class = $this->resolveAlias($class);

        if (isset($this->singletons[$class])) {
            return $this->singletons[$class];
        }

        if (isset($this->definitions[$class])) {
            $definition = $this->definitions[$class];
            if ($definition['factory'] !== null) {
                $instance = $definition['factory']($this);
            } else {
                $instance = $this->build($class);
            }
            if ($definition['type'] === 'singleton') {
                $this->singletons[$class] = $instance;
            }
            return $instance;
        }

        $instance = $this->build($class);
        return $instance;
    }

    private function build(string $class): object
    {
        try {
            $reflection = new ReflectionClass($class);
            if (!$reflection->isInstantiable()) {
                throw new \RuntimeException("Class {$class} is not instantiable.");
            }

            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $parameters = $constructor->getParameters();
            $dependencies = [];

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();
                $paramType = $parameter->getType();

                if (isset($this->parameters[$class][$paramName])) {
                    $dependencies[] = $this->parameters[$class][$paramName];
                    continue;
                }

                if ($paramType === null) {
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

                try {
                    $dependencies[] = $this->get($typeName);
                } catch (Throwable $e) {
                    throw new \RuntimeException(
                        "Cannot resolve dependency '{$typeName}' for parameter '{$paramName}' in {$class}: {$e->getMessage()}"
                    );
                }
            }

            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            throw new \RuntimeException("Reflection error for class {$class}: {$e->getMessage()}", 0, $e);
        }
    }

    private function resolveAlias(string $class): string
    {
        return $this->aliases[$class] ?? $class;
    }

    public function has(string $class): bool
    {
        $class = $this->resolveAlias($class);
        return isset($this->definitions[$class]) || isset($this->singletons[$class]);
    }

    public function getSingleton(string $class): ?object
    {
        $class = $this->resolveAlias($class);
        return $this->singletons[$class] ?? null;
    }

    public function clearSingletons(): void
    {
        $this->singletons = [];
    }

    public function clear(): void
    {
        $this->singletons = [];
        $this->definitions = [];
        $this->aliases = [];
        $this->parameters = [];
    }

    public static function createDefault(): self
    {
        $container = new self();

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

        $container->singleton(\QuarterTg\Managers\UserManager::class);
        $container->singleton(\QuarterTg\Managers\AdminManager::class);
        $container->singleton(\QuarterTg\Managers\LockManager::class);
        $container->singleton(\QuarterTg\Managers\WarnManager::class);
        $container->singleton(\QuarterTg\Managers\AuthorizationManager::class);

        return $container;
    }
}