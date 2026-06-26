<?php
namespace Core;

class Logger
{
    private $logDir;
    private $level;
    private $enabled;
    private const LEVELS = ['error' => 1, 'warning' => 2, 'info' => 3, 'debug' => 4];

    public function __construct(string $logDir, string $level = 'info', bool $enabled = true)
    {
        $this->logDir = rtrim($logDir, '/') . '/';
        $this->level = $level;
        $this->enabled = $enabled;
        if ($enabled && !is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled || self::LEVELS[$level] > self::LEVELS[$this->level]) {
            return;
        }
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$date}] [{$level}] {$message}{$contextStr}\n";
        $filename = $this->logDir . date('Y-m-d') . '.log';
        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('warning', $message, $context); }
    public function info(string $message, array $context = []): void { $this->log('info', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('debug', $message, $context); }
}