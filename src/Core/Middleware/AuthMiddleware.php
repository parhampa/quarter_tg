<?php

declare(strict_types=1);

namespace QuarterTg\Core\Middleware;

use QuarterTg\Core\Config;
use QuarterTg\Core\Logger;
use QuarterTg\Helpers\ValidationHelper;

/**
 * Middleware احراز هویت Webhook
 * 
 * وظایف:
 * - بررسی Webhook Secret
 * - بررسی IP مجاز (در صورت تنظیم)
 * - مدیریت خطاهای احراز هویت
 */
class AuthMiddleware
{
    private Config $config;
    private Logger $logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * اجرای Middleware
     */
    public function handle(array $update, $app): void
    {
        // 1. بررسی Webhook Secret
        $webhookSecret = $this->config->get('webhook.secret', '');
        if (!empty($webhookSecret)) {
            $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_WEBHOOK_SECRET'] ?? $_GET['secret'] ?? '';
            if (!hash_equals($webhookSecret, $receivedSecret)) {
                $this->logger->warning('Invalid webhook secret.', [
                    'received' => $receivedSecret,
                    'expected' => $webhookSecret,
                ]);
                http_response_code(403);
                exit('Forbidden: Invalid webhook secret.');
            }
        }

        // 2. بررسی IP کلاینت
        $allowedIps = $this->config->get('webhook.allowed_ips', '');
        if (!empty($allowedIps)) {
            $clientIp = $this->getClientIp();
            if (!$this->isIpAllowed($clientIp, $allowedIps)) {
                $this->logger->warning('IP not allowed.', ['ip' => $clientIp]);
                http_response_code(403);
                exit('Forbidden: IP not allowed.');
            }
        }

        $this->logger->debug('Auth middleware passed.', [
            'ip' => $this->getClientIp(),
        ]);
    }

    /**
     * دریافت IP کلاینت (با پشتیبانی از پروکسی)
     */
    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // اگر پروکسی وجود دارد، از HTTP_X_FORWARDED_FOR استفاده کنیم
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }

    /**
     * بررسی اینکه آیا IP در لیست مجاز قرار دارد؟
     * پشتیبانی از IPv4 و IPv6 با CIDR
     */
    private function isIpAllowed(string $clientIp, string $allowedList): bool
    {
        $allowedList = trim($allowedList);
        if (empty($allowedList)) {
            return true;
        }

        $allowedIps = array_map('trim', explode(',', $allowedList));
        foreach ($allowedIps as $allowed) {
            // بررسی دقیق
            if ($clientIp === $allowed) {
                return true;
            }
            // بررسی CIDR
            if (strpos($allowed, '/') !== false) {
                if ($this->ipInCidr($clientIp, $allowed)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * بررسی اینکه آیا IP در محدوده CIDR قرار دارد؟
     * پشتیبانی از IPv4 و IPv6
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $mask = (int)$mask;

        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $ipBin = ip2long($ip);
            $subnetBin = ip2long($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $maskBin = -1 << (32 - $mask);
            return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $bytes = $mask >> 3;
            $bits = $mask & 7;
            for ($i = 0; $i < $bytes; $i++) {
                if ($ipBin[$i] !== $subnetBin[$i]) {
                    return false;
                }
            }
            if ($bits > 0) {
                $maskByte = ~0 << (8 - $bits);
                $ipByte = ord($ipBin[$bytes]) & $maskByte;
                $subnetByte = ord($subnetBin[$bytes]) & $maskByte;
                if ($ipByte !== $subnetByte) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}