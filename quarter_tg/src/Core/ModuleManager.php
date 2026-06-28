<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت بارگذاری و اجرای ماژول‌ها
 * ماژول‌ها به‌صورت پویا بر اساس command_map بارگذاری می‌شوند
 */
class ModuleManager
{
    private $commandMap;
    private $modules = [];
    private $dependencies = [];
    private $moduleNamespace = 'Modules\\';
    private $loadedModules = [];

    /**
     * @param array $commandMap آرایه نگاشت دستورات به نام ماژول‌ها
     * @param array $dependencies وابستگی‌های اولیه
     */
    public function __construct(array $commandMap, array $dependencies = [])
    {
        $this->commandMap = $commandMap;
        $this->dependencies = $dependencies;
    }

    /**
     * ثبت یک وابستگی برای استفاده در ماژول‌ها
     */
    public function registerDependency(string $name, $dependency): void
    {
        $this->dependencies[$name] = $dependency;
    }

    /**
     * ثبت یک ماژول به‌صورت دستی
     */
    public function registerModule(string $name, $module): void
    {
        $this->modules[$name] = $module;
    }

    /**
     * دریافت نام ماژول برای یک دستور خاص
     */
    public function getModuleName(string $command): ?string
    {
        // دستورات با / را بدون اسلش بررسی می‌کنیم
        $cleanCommand = ltrim($command, '/');
        
        // جستجوی دقیق
        if (isset($this->commandMap[$command])) {
            return $this->commandMap[$command];
        }
        
        // جستجوی با اسلش
        if (isset($this->commandMap['/' . $cleanCommand])) {
            return $this->commandMap['/' . $cleanCommand];
        }
        
        // جستجوی بدون اسلش
        if (isset($this->commandMap[$cleanCommand])) {
            return $this->commandMap[$cleanCommand];
        }
        
        return null;
    }

    /**
     * بارگذاری یک ماژول
     * @return object|null
     */
    public function loadModule(string $moduleName)
    {
        // اگر ماژول قبلاً ثبت شده، آن را برگردان
        if (isset($this->modules[$moduleName])) {
            return $this->modules[$moduleName];
        }

        // جلوگیری از بارگذاری مجدد
        if (isset($this->loadedModules[$moduleName])) {
            return $this->loadedModules[$moduleName];
        }

        // ساخت نام کامل کلاس با Namespace
        $fullClassName = $this->moduleNamespace . $moduleName;
        
        if (!class_exists($fullClassName)) {
            return null;
        }

        // ایجاد نمونه از ماژول با تزریق وابستگی‌ها
        $reflection = new \ReflectionClass($fullClassName);
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            // بدون Constructor
            $instance = $reflection->newInstance();
        } else {
            // دریافت پارامترهای Constructor
            $params = $constructor->getParameters();
            $args = [];
            
            foreach ($params as $param) {
                $paramType = $param->getType();
                $paramName = $param->getName();
                
                // اگر نوع مشخص شده باشد
                if ($paramType && !$paramType->isBuiltin()) {
                    $typeName = $paramType->getName();
                    // جستجو در وابستگی‌ها بر اساس نام کلاس
                    $found = false;
                    foreach ($this->dependencies as $name => $dependency) {
                        if (is_object($dependency) && $dependency instanceof $typeName) {
                            $args[] = $dependency;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        // تلاش برای ساخت خودکار
                        try {
                            if (class_exists($typeName)) {
                                $args[] = new $typeName();
                            } else {
                                $args[] = null;
                            }
                        } catch (\Exception $e) {
                            $args[] = null;
                        }
                    }
                } else {
                    // پارامتر بدون نوع یا نوع ساده – جستجو بر اساس نام
                    if (isset($this->dependencies[$paramName])) {
                        $args[] = $this->dependencies[$paramName];
                    } else {
                        // مقدار پیش‌فرض در صورت وجود
                        if ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        } else {
                            $args[] = null;
                        }
                    }
                }
            }
            
            $instance = $reflection->newInstanceArgs($args);
        }

        // ذخیره در کش برای استفاده مجدد
        $this->loadedModules[$moduleName] = $instance;
        return $instance;
    }

    /**
     * اجرای یک ماژول با پارامترهای داده‌شده
     * @return mixed|null
     */
    public function runModule(string $command, array $update, int $chatId, int $userId, string $params = '')
    {
        $moduleName = $this->getModuleName($command);
        if (!$moduleName) {
            return null;
        }

        $module = $this->loadModule($moduleName);
        if (!$module) {
            return null;
        }

        // بررسی وجود متد execute
        if (!method_exists($module, 'execute')) {
            return null;
        }

        // آماده‌سازی آرگومان‌ها
        $message = $update['message'] ?? [];
        $args = [];

        // اگر متد execute دو پارامتر دارد (message, params)
        $reflection = new \ReflectionMethod($module, 'execute');
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount === 1) {
            // فقط message
            $args[] = $message;
        } elseif ($paramCount === 2) {
            // message و params
            $args[] = $message;
            $args[] = $params;
        } elseif ($paramCount === 3) {
            // message, params, chatId
            $args[] = $message;
            $args[] = $params;
            $args[] = $chatId;
        } elseif ($paramCount === 4) {
            // message, params, chatId, userId
            $args[] = $message;
            $args[] = $params;
            $args[] = $chatId;
            $args[] = $userId;
        } else {
            // در غیر این صورت، کل update را ارسال می‌کنیم
            $args[] = $update;
        }

        return $module->execute(...$args);
    }

    /**
     * دریافت لیست تمام دستورات موجود
     */
    public function getCommands(): array
    {
        return array_keys($this->commandMap);
    }

    /**
     * دریافت لیست دستورات با توضیحات (برای Help)
     */
    public function getCommandsWithDescriptions(): array
    {
        $result = [];
        foreach ($this->commandMap as $command => $moduleName) {
            $result[$command] = [
                'module' => $moduleName,
                'description' => $this->getModuleDescription($moduleName),
            ];
        }
        return $result;
    }

    /**
     * دریافت توضیحات ماژول (اگر متد getDescription داشته باشد)
     */
    private function getModuleDescription(string $moduleName): string
    {
        $module = $this->loadModule($moduleName);
        if ($module && method_exists($module, 'getDescription')) {
            return $module->getDescription();
        }
        return '';
    }

    /**
     * بررسی وجود ماژول
     */
    public function moduleExists(string $moduleName): bool
    {
        $fullClassName = $this->moduleNamespace . $moduleName;
        return class_exists($fullClassName);
    }

    /**
     * دریافت تمام ماژول‌های بارگذاری‌شده
     */
    public function getLoadedModules(): array
    {
        return array_keys($this->loadedModules);
    }

    /**
     * تنظیم Namespace ماژول‌ها
     */
    public function setModuleNamespace(string $namespace): void
    {
        $this->moduleNamespace = rtrim($namespace, '\\') . '\\';
    }

    /**
     * دریافت نگاشت دستورات
     */
    public function getCommandMap(): array
    {
        return $this->commandMap;
    }
}