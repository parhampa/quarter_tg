<?php
namespace Core;

use Helpers\TelegramApi;
use Exceptions\ModuleNotFoundException;

class ModuleManager
{
    private $modulesDir;
    private $commandMap;
    private $defaults;

    public function __construct(string $modulesDir, array $commandMap, array $defaults = [])
    {
        $this->modulesDir = rtrim($modulesDir, '/') . '/';
        $this->commandMap = $commandMap;
        $this->defaults = array_merge([
            'authorized_only' => true,
            'allowed_in_private' => false,
            'required_role' => 'group_admin',
        ], $defaults);
    }

    public function getModuleInfo(string $command): ?array
    {
        if (!isset($this->commandMap[$command])) {
            return null;
        }
        return array_merge($this->defaults, $this->commandMap[$command]);
    }

    public function execute(string $command, array $args, array $update, TelegramApi $api): void
    {
        $moduleInfo = $this->getModuleInfo($command);
        if (!$moduleInfo) {
            throw new ModuleNotFoundException("Command '{$command}' not found.");
        }

        $className = $moduleInfo['module'];
        $method = $moduleInfo['method'] ?? 'handle';

        $filePath = $this->modulesDir . $className . '.php';
        if (!file_exists($filePath)) {
            throw new ModuleNotFoundException("Module file '{$className}.php' not found.");
        }
        require_once $filePath;

        $fullClassName = "\\Modules\\{$className}";
        if (!class_exists($fullClassName)) {
            throw new ModuleNotFoundException("Class '{$fullClassName}' not found.");
        }
        $module = new $fullClassName();

        if (!method_exists($module, $method)) {
            throw new ModuleNotFoundException("Method '{$method}' not found in module '{$className}'.");
        }

        $module->{$method}($update, $args, $api, $command);
    }
}