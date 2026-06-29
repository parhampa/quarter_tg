<?php

namespace QuarterTg\Core;

use QuarterTg\Core\Database;
use QuarterTg\Core\Cache;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\TelegramApi;

/**
 * کلاس مدیریت بارگذاری و اجرای ماژول‌ها
 * ماژول‌ها به‌صورت پویا بر اساس command_map بارگذاری می‌شوند
 * با قابلیت تزریق وابستگی‌ها از طریق Reflection
 */
class ModuleManager
{
    private $commandMap;
    private $dependencies = [];
    private $modules = [];
    private $moduleNamespace = 'Modules\\';
    private $loadedModules = [];

    /**
     * @param array $commandMap آرایه نگاشت دستورات به نام ماژول‌ها
     * @param array $dependencies وابستگی‌های اولیه (آرایه‌ای از اشیاء)
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
     * بارگذاری یک ماژول با تزریق وابستگی‌ها
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
                $paramClass = null;
                
                // دریافت نام کلاس از نوع پارامتر
                if ($paramType && !$paramType->isBuiltin()) {
                    $paramClass = $paramType->getName();
                }
                
                $found = false;
                
                // ۱. جستجو بر اساس نوع (کلاس)
                if ($paramClass) {
                    foreach ($this->dependencies as $name => $dependency) {
                        if (is_object($dependency) && $dependency instanceof $paramClass) {
                            $args[] = $dependency;
                            $found = true;
                            break;
                        }
                    }
                }
                
                // ۲. جستجو بر اساس نام پارامتر
                if (!$found && isset($this->dependencies[$paramName])) {
                    $args[] = $this->dependencies[$paramName];
                    $found = true;
                }
                
                // ۳. اگر پیدا نشد، مقدار پیش‌فرض یا null
                if (!$found) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        // تلاش برای ساخت خودکار
                        if ($paramClass && class_exists($paramClass)) {
                            try {
                                $args[] = new $paramClass();
                            } catch (\Exception $e) {
                                $args[] = null;
                            }
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

        // تشخیص تعداد پارامترهای متد execute
        $reflection = new \ReflectionMethod($module, 'execute');
        $paramCount = $reflection->getNumberOfParameters();
        $paramNames = [];
        foreach ($reflection->getParameters() as $param) {
            $paramNames[] = $param->getName();
        }

        // تخصیص آرگومان‌ها بر اساس نام پارامترها
        $argMap = [
            'message' => $message,
            'params'  => $params,
            'chatId'  => $chatId,
            'userId'  => $userId,
            'update'  => $update,
        ];

        for ($i = 0; $i < $paramCount; $i++) {
            $name = $paramNames[$i] ?? null;
            if ($name && isset($argMap[$name])) {
                $args[] = $argMap[$name];
            } elseif ($i === 0) {
                // اگر اولین پارامتر است، message را ارسال کن
                $args[] = $message;
            } elseif ($i === 1) {
                // اگر دومین پارامتر است، params را ارسال کن
                $args[] = $params;
            } elseif ($i === 2) {
                // اگر سومین پارامتر است، chatId را ارسال کن
                $args[] = $chatId;
            } elseif ($i === 3) {
                // اگر چهارمین پارامتر است، userId را ارسال کن
                $args[] = $userId;
            } else {
                // در غیر این صورت، کل update را ارسال می‌کنیم
                $args[] = $update;
            }
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

    /**
     * اضافه کردن یک دستور جدید به نگاشت (در زمان اجرا)
     */
    public function addCommand(string $command, string $moduleName): void
    {
        $this->commandMap[$command] = $moduleName;
    }

    /**
     * حذف یک دستور از نگاشت
     */
    public function removeCommand(string $command): void
    {
        unset($this->commandMap[$command]);
    }
}