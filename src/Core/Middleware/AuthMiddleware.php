<?php

declare(strict_types=1);

namespace QuarterTg\Core\Middleware;

use QuarterTg\Core\Config;
use QuarterTg\Core\Logger;

/**
 * میدلور احراز هویت Webhook
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

    public function handle(array $update, $app): void
    {
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

    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        return $ip;
    }

    private function isIpAllowed(string $clientIp, string $allowedList): bool
    {
        $allowedList = trim($allowedList);
        if (empty($allowedList)) {
            return true;
        }

        $allowedIps = array_map('trim', explode(',', $allowedList));
        foreach ($allowedIps as $allowed) {
            if ($clientIp === $allowed) {
                return true;
            }
            if (strpos($allowed, '/') !== false) {
                if ($this->ipInCidr($clientIp, $allowed)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $mask = (int)$mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $ipBin = ip2long($ip);
            $subnetBin = ip2long($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $maskBin = -1 << (32 - $mask);
            return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
        }

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