<?php

declare(strict_types=1);

namespace QuarterTg\Core\Middleware;

use QuarterTg\Core\Logger;

/**
 * میدلور لاگ‌گیری درخواست‌ها
 */
class LoggingMiddleware
{
    private Logger $logger;
    private float $startTime;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->startTime = microtime(true);
    }

    public function handle(array $update, $app): void
    {
        $this->logger->info('Webhook received.', [
            'update_id' => $update['update_id'] ?? null,
            'type' => $this->getUpdateType($update),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }

    public function after(array $update, $app): void
    {
        $executionTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info('Webhook processed.', [
            'execution_time' => $executionTime . 'ms',
            'memory_usage' => memory_get_peak_usage(true),
        ]);
    }

    private function getUpdateType(array $update): string
    {
        if (isset($update['message'])) return 'message';
        if (isset($update['callback_query'])) return 'callback_query';
        if (isset($update['inline_query'])) return 'inline_query';
        if (isset($update['edited_message'])) return 'edited_message';
        if (isset($update['channel_post'])) return 'channel_post';
        if (isset($update['poll'])) return 'poll';
        if (isset($update['my_chat_member'])) return 'my_chat_member';
        if (isset($update['chat_member'])) return 'chat_member';
        return 'unknown';
    }
}