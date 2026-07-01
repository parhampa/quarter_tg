<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * مدیریت بارگذاری و اجرای ماژولهای ربات
 * 
 * مسئولیتها:
 * - اسکن دایرکتوری ماژولها و یافتن کلاسهای ماژول
 * - ایجاد نقشه (Mapping) بین دستورات و ماژولها
 * - تزریق وابستگیها با Reflection و کش کردن
 * - اجرای متد execute ماژول
 */
class ModuleManager
{
    private string $modulesPath;
    private string $namespace;
    private Logger $logger;
    private ?Cache $cache = null;
    
    /** @var array<string, string> نقشه دستور -> نام کلاس ماژول */
    private array $commandMap = [];
    
    /** @var array<string, object> کش نمونههای ساختهشده از ماژولها */
    private array $moduleInstances = [];
    
    /** @var array<string, mixed> اشیاء اشتراکی برای تزریق */
    private array $sharedInstances = [];
    
    /** @var bool آیا نقشه بارگذاری شده است؟ */
    private bool $mapLoaded = false;

    public function __construct(
        string $modulesPath,
        string $namespace,
        Logger $logger,
        ?Cache $cache = null
    ) {
        $this->modulesPath = rtrim($modulesPath, '/\\') . DIRECTORY_SEPARATOR;
        $this->namespace = rtrim($namespace, '\\') . '\\';
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * ثبت یک شیء اشتراکی برای تزریق خودکار به ماژولها
     */
    public function addSharedInstance(object $instance): void
    {
        $className = get_class($instance);
        $this->sharedInstances[$className] = $instance;
        
        // همچنین با نام رابط (Interface) اگر پیادهسازی کرده باشد
        $interfaces = class_implements($instance);
        foreach ($interfaces as $interface) {
            $this->sharedInstances[$interface] = $instance;
        }
    }

    /**
     * بارگذاری نقشه دستورات از کش یا اسکن دایرکتوری
     */
    private function loadCommandMap(): void
    {
        if ($this->mapLoaded) {
            return;
        }

        // تلاش برای خواندن از کش
        $cacheKey = 'module_command_map';
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null && is_array($cached)) {
                $this->commandMap = $cached;
                $this->mapLoaded = true;
                $this->logger->debug('Command map loaded from cache.', ['count' => count($this->commandMap)]);
                return;
            }
        }

        // اسکن دایرکتوری ماژولها
        $this->commandMap = $this->scanModules();
        $this->mapLoaded = true;

        // ذخیره در کش
        if ($this->cache !== null && !empty($this->commandMap)) {
            $this->cache->set($cacheKey, $this->commandMap, 3600); // 1 ساعت
        }

        $this->logger->info('Command map built from files.', ['count' => count($this->commandMap)]);
    }

    /**
     * اسکن دایرکتوری ماژولها و استخراج نقشه دستورات
     * 
     * @return array<string, string> نقشه دستور -> نام کامل کلاس
     */
    private function scanModules(): array
    {
        $map = [];
        if (!is_dir($this->modulesPath)) {
            $this->logger->error('Modules directory not found: ' . $this->modulesPath);
            return $map;
        }

        // اسکن همه فایلهای PHP در دایرکتوری ماژولها
        $files = glob($this->modulesPath . '*.php');
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName = $this->namespace . $className;

            // بررسی وجود کلاس
            if (!class_exists($fullClassName)) {
                $this->logger->warning('Class not found for module file.', ['class' => $fullClassName, 'file' => $file]);
                continue;
            }

            try {
                $reflection = new ReflectionClass($fullClassName);
                
                // بررسی اینکه آیا کلاس ماژول است (اینترفیس یا متد execute دارد)
                if (!$this->isValidModule($reflection)) {
                    continue;
                }

                // دریافت دستورات از ثابت یا متد استاتیک یا Annotation
                $commands = $this->getModuleCommands($reflection);
                foreach ($commands as $command) {
                    $command = strtolower(trim($command));
                    if (!empty($command)) {
                        $map[$command] = $fullClassName;
                    }
                }
            } catch (ReflectionException $e) {
                $this->logger->error('Reflection error for module.', [
                    'class' => $fullClassName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $map;
    }

    /**
     * بررسی اینکه یک کلاس ماژول معتبر است یا خیر
     */
    private function isValidModule(ReflectionClass $reflection): bool
    {
        // اگر اینترفیس ModuleInterface را پیادهسازی کرده باشد
        if ($reflection->implementsInterface('QuarterTg\\Modules\\ModuleInterface')) {
            return true;
        }

        // یا اینکه متد execute عمومی داشته باشد
        try {
            $method = $reflection->getMethod('execute');
            return $method->isPublic() && !$method->isAbstract();
        } catch (ReflectionException $e) {
            return false;
        }
    }

    /**
     * استخراج دستورات از یک ماژول
     * 
     * @return array<string> لیست دستورات
     */
    private function getModuleCommands(ReflectionClass $reflection): array
    {
        $commands = [];

        // 1. از ثابت COMMANDS
        if ($reflection->hasConstant('COMMANDS')) {
            $const = $reflection->getConstant('COMMANDS');
            if (is_array($const)) {
                $commands = array_merge($commands, $const);
            } elseif (is_string($const)) {
                $commands[] = $const;
            }
        }

        // 2. از متد استاتیک getCommands()
        try {
            $method = $reflection->getMethod('getCommands');
            if ($method->isStatic() && $method->isPublic()) {
                $result = $method->invoke(null);
                if (is_array($result)) {
                    $commands = array_merge($commands, $result);
                } elseif (is_string($result)) {
                    $commands[] = $result;
                }
            }
        } catch (ReflectionException $e) {
            // متد وجود ندارد - نادیده گرفته میشود
        }

        // 3. از DocBlock با @command
        $docComment = $reflection->getDocComment();
        if ($docComment) {
            preg_match_all('/@command\s+([^\s]+)/', $docComment, $matches);
            if (!empty($matches[1])) {
                $commands = array_merge($commands, $matches[1]);
            }
        }

        // حذف تکراریها و خالیها
        return array_unique(array_filter(array_map('trim', $commands)));
    }

    /**
     * یافتن ماژول برای یک دستور خاص
     * 
     * @param string $commandName نام دستور (بدون /)
     * @return object|null نمونه ماژول یا null در صورت عدم وجود
     */
    public function findModuleForCommand(string $commandName): ?object
    {
        $this->loadCommandMap();

        $commandName = strtolower(trim($commandName));
        if (empty($commandName)) {
            return null;
        }

        // بررسی در نقشه
        if (!isset($this->commandMap[$commandName])) {
            return null;
        }

        $className = $this->commandMap[$commandName];

        // اگر قبلاً نمونه ساخته شده، همان را برگردانیم
        if (isset($this->moduleInstances[$className])) {
            return $this->moduleInstances[$className];
        }

        // ساخت نمونه با تزریق وابستگی
        try {
            $instance = $this->buildModuleInstance($className);
            if ($instance !== null) {
                $this->moduleInstances[$className] = $instance;
                return $instance;
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to build module instance.', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * ساخت نمونه از یک کلاس ماژول با تزریق وابستگی از طریق Reflection
     * 
     * @param string $className نام کامل کلاس
     * @return object|null نمونه ساختهشده یا null در صورت خطا
     */
    private function buildModuleInstance(string $className): ?object
    {
        try {
            $reflection = new ReflectionClass($className);
            if (!$reflection->isInstantiable()) {
                $this->logger->error('Class is not instantiable.', ['class' => $className]);
                return null;
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
                $paramType = $parameter->getType();
                if ($paramType === null) {
                    // اگر نوع نداشته باشد، از مقدار پیشفرض استفاده کنیم
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        // اگر نوع و مقدار پیشفرض نداشته باشد، خطاست
                        $this->logger->error('Cannot resolve parameter without type.', [
                            'class' => $className,
                            'param' => $parameter->getName()
                        ]);
                        return null;
                    }
                    continue;
                }

                $typeName = $paramType->getName();
                
                // اگر نوع scalar (string, int, etc.) باشد و مقدار پیشفرض نداشته باشد، خطاست
                if ($paramType->isBuiltin()) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $dependencies[] = $parameter->getDefaultValue();
                    } else {
                        $this->logger->error('Cannot resolve built-in parameter.', [
                            'class' => $className,
                            'param' => $parameter->getName(),
                            'type' => $typeName
                        ]);
                        return null;
                    }
                    continue;
                }

                // جستجوی وابستگی در sharedInstances
                if (isset($this->sharedInstances[$typeName])) {
                    $dependencies[] = $this->sharedInstances[$typeName];
                    continue;
                }

                // اگر امکان ساخت خودکار وجود دارد (با Constructor خالی)
                if (class_exists($typeName)) {
                    try {
                        $subReflection = new ReflectionClass($typeName);
                        if ($subReflection->isInstantiable()) {
                            $dependencies[] = $this->buildModuleInstance($typeName);
                            continue;
                        }
                    } catch (ReflectionException $e) {
                        // نادیده گرفته شود
                    }
                }

                // اگر هیچکدام، خطاست
                $this->logger->error('Unresolved dependency.', [
                    'class' => $className,
                    'param' => $parameter->getName(),
                    'type' => $typeName
                ]);
                return null;
            }

            // ساخت نمونه با وابستگیهای تزریقشده
            return $reflection->newInstanceArgs($dependencies);

        } catch (ReflectionException $e) {
            $this->logger->error('Reflection error building module.', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * اجرای یک ماژول (با فراخوانی متد execute)
     * این متد برای سازگاری با کدهای قدیمی نگهداری شده است
     * 
     * @deprecated استفاده از findModuleForCommand و سپس execute مستقیم توصیه میشود
     */
    public function runModule(string $className, array $params = []): mixed
    {
        $instance = $this->buildModuleInstance($className);
        if ($instance === null) {
            $this->logger->error('Cannot run module, instance creation failed.', ['class' => $className]);
            return null;
        }

        if (!method_exists($instance, 'execute')) {
            $this->logger->error('Module does not have execute method.', ['class' => $className]);
            return null;
        }

        try {
            // فراخوانی execute با پارامترهای دادهشده
            return $instance->execute(...$params);
        } catch (Throwable $e) {
            $this->logger->error('Error executing module.', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * پاک کردن کش نقشه دستورات
     */
    public function clearCommandMapCache(): void
    {
        if ($this->cache !== null) {
            $this->cache->delete('module_command_map');
        }
        $this->commandMap = [];
        $this->mapLoaded = false;
        $this->logger->info('Command map cache cleared.');
    }

    /**
     * دریافت لیست کامل نقشه دستورات (برای دیباگ)
     */
    public function getCommandMap(): array
    {
        $this->loadCommandMap();
        return $this->commandMap;
    }

    /**
     * بارگذاری مجدد نقشه دستورات (از طریق اسکن مجدد)
     */
    public function reloadCommandMap(): void
    {
        $this->clearCommandMapCache();
        $this->loadCommandMap();
    }
}