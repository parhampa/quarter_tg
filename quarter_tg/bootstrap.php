<?php
spl_autoload_register(function ($class) {
    $prefixes = [
        'Core\\' => __DIR__ . '/src/Core/',
        'Helpers\\' => __DIR__ . '/src/Helpers/',
        'Modules\\' => __DIR__ . '/src/Modules/',
        'Exceptions\\' => __DIR__ . '/src/Exceptions/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }
});

$config = require_once __DIR__ . '/config/config.php';

$logger = new \Core\Logger($config['log_dir'], $config['log_level'], $config['enable_log']);
$cache = new \Core\Cache($config['cache_dir'], $config['cache_ttl']);
$db = \Core\Database::getInstance($config['db']);
$api = new \Helpers\TelegramApi($config['bot_token']);
$permissionManager = new \Core\PermissionManager($db, $cache);
$adminManager = new \Core\AdminManager($db, $cache);
$welcomeManager = new \Core\WelcomeManager($db, $cache);
$lockManager = new \Core\LockManager($db, $cache);
$messageLogger = new \Core\MessageLogger($db, $cache, true);
$commandLogger = new \Core\CommandLogger($db, $cache, true);  // NEW

$auth = new \Core\AuthorizationManager($db, $api, $cache, $permissionManager, $adminManager);
$requestHandler = new \Core\RequestHandler($config['command_map']);
$moduleManager = new \Core\ModuleManager(
    $config['modules_dir'],
    $config['command_map'],
    $config['module_defaults'] ?? []
);

$GLOBALS['adminManager'] = $adminManager;
$GLOBALS['welcomeManager'] = $welcomeManager;
$GLOBALS['lockManager'] = $lockManager;
$GLOBALS['db'] = $db;
$GLOBALS['cache'] = $cache;

$bot = new \Core\Bot(
    $api,
    $auth,
    $moduleManager,
    $requestHandler,
    $logger,
    $config,
    $welcomeManager,
    $lockManager,
    $messageLogger,
    $commandLogger
);