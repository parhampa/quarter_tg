<?php

require_once __DIR__ . '/bootstrap.php';

$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    exit('No input');
}

if (!empty($config['webhook_secret'])) {
    $headers = getallheaders();
    if (!isset($headers['X-Telegram-Bot-Api-Secret-Token']) ||
        $headers['X-Telegram-Bot-Api-Secret-Token'] !== $config['webhook_secret']) {
        http_response_code(403);
        exit('Invalid secret');
    }
}

try {
    $bot->handleRequest($input);
} catch (\Exception $e) {
    $logger->error('Unhandled exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    http_response_code(500);
    exit('Internal Server Error');
}