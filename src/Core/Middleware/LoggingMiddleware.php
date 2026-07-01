<?php

declare(strict_types=1);

namespace QuarterTg\Core\Middleware;

use QuarterTg\Core\Logger;

/**
 * Middleware لاگ کردن درخواست‌ها
 * 
 * وظایف:
 * - لاگ کردن درخواست‌های دریافتی
 * - لاگ کردن زمان پاسخ
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

    /**
     * اجرای Middleware (قبل از پردازش)
     */
    public function handle(array $update, $app): void
    {
        $this->logger->info('Webhook received.', [
            'update_id' => $update['update_id'] ?? null,
            'type' => $this->getUpdateType($update),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }

    /**
     * اجرای Middleware (بعد از پردازش)
     */
    public function after(array $update, $app): void
    {
        $executionTime = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->logger->info('Webhook processed.', [
            'execution_time' => $executionTime . 'ms',
            'memory_usage' => memory_get_peak_usage(true),
        ]);
    }

    /**
     * تشخیص نوع آپدیت
     */
    private function getUpdateType(array $update): string
    {
        if (isset($update['message'])) {
            return 'message';
        }
        if (isset($update['callback_query'])) {
            return 'callback_query';
        }
        if (isset($update['inline_query'])) {
            return 'inline_query';
        }
        if (isset($update['chosen_inline_result'])) {
            return 'chosen_inline_result';
        }
        if (isset($update['edited_message'])) {
            return 'edited_message';
        }
        if (isset($update['channel_post'])) {
            return 'channel_post';
        }
        if (isset($update['edited_channel_post'])) {
            return 'edited_channel_post';
        }
        if (isset($update['shipping_query'])) {
            return 'shipping_query';
        }
        if (isset($update['pre_checkout_query'])) {
            return 'pre_checkout_query';
        }
        if (isset($update['poll'])) {
            return 'poll';
        }
        if (isset($update['poll_answer'])) {
            return 'poll_answer';
        }
        if (isset($update['my_chat_member'])) {
            return 'my_chat_member';
        }
        if (isset($update['chat_member'])) {
            return 'chat_member';
        }
        if (isset($update['chat_join_request'])) {
            return 'chat_join_request';
        }
        return 'unknown';
    }
}