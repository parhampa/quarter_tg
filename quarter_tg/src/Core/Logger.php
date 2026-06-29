<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت لاگ‌گیری با پشتیبانی از چندین سطح و چرخش فایل
 * سطوح پشتیبانی‌شده: debug, info, warning, error
 */
class Logger
{
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';
    const LEVEL_NONE    = 'none';

    private $logFile;
    private $enabled;
    private $minLevel;
    private $maxFileSize;
    private $backupCount;
    private $levels = [
        'debug'   => 0,
        'info'    => 1,
        'warning' => 2,
        'error'   => 3,
        'none'    => 999,
    ];

    /**
     * @param string $logFile مسیر فایل لاگ
     * @param bool $enabled فعال/غیرفعال کردن لاگ
     * @param string $minLevel حداقل سطح لاگ (debug, info, warning, error)
     * @param int $maxFileSize حداکثر حجم فایل بر حسب بایت (پیش‌فرض ۵ مگابایت)
     * @param int $backupCount تعداد فایل‌های بکاپ
     */
    public function __construct(
        string $logFile,
        bool $enabled = true,
        string $minLevel = 'info',
        int $maxFileSize = 5242880, // 5MB
        int $backupCount = 5
    ) {
        $this->logFile = $logFile;
        $this->enabled = $enabled;
        $this->minLevel = $minLevel;
        $this->maxFileSize = $maxFileSize;
        $this->backupCount = $backupCount;

        // ایجاد دایرکتوری لاگ در صورت عدم وجود
        $dir = dirname($this->logFile);
        if ($this->enabled && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * ثبت پیام در لاگ
     * @param string $level سطح لاگ (debug, info, warning, error)
     * @param string $message پیام
     * @param array $context داده‌های اضافی (برای JSON encode)
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        // بررسی سطح لاگ
        if ($this->levels[$level] < $this->levels[$this->minLevel]) {
            return;
        }

        $this->writeLog($level, $message, $context);
    }

    /**
     * لاگ سطح DEBUG
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * لاگ سطح INFO
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * لاگ سطح WARNING
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * لاگ سطح ERROR
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * لاگ کردن یک استثناء
     * @param \Throwable $e
     * @param string $extra پیام اضافی
     */
    public function exception(\Throwable $e, string $extra = ''): void
    {
        $context = [
            'exception' => get_class($e),
            'code'      => $e->getCode(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $this->getTraceSummary($e),
        ];

        $message = $e->getMessage();
        if ($extra) {
            $message = $extra . ': ' . $message;
        }

        $this->error($message, $context);
    }

    /**
     * نوشتن در فایل لاگ
     */
    private function writeLog(string $level, string $message, array $context = []): void
    {
        // چرخش فایل در صورت بزرگ بودن
        $this->rotateIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIp();
        $contextJson = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $logLine = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $ip,
            $message,
            $contextJson
        );

        // استفاده از LOCK_EX برای جلوگیری از تداخل در نوشتن همزمان
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * چرخش فایل لاگ در صورت نیاز
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $size = filesize($this->logFile);
        if ($size < $this->maxFileSize) {
            return;
        }

        // بکاپ گرفتن از فایل فعلی
        for ($i = $this->backupCount - 1; $i >= 1; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // فایل اصلی به بکاپ شماره ۱ تبدیل می‌شود
        rename($this->logFile, $this->logFile . '.1');

        // فایل جدید خالی ایجاد می‌شود
        touch($this->logFile);
        chmod($this->logFile, 0644);
    }

    /**
     * دریافت خلاصه Trace برای نمایش در لاگ
     */
    private function getTraceSummary(\Throwable $e): string
    {
        $trace = $e->getTrace();
        $summary = [];
        $limit = 5; // فقط ۵ خط اول

        foreach ($trace as $index => $item) {
            if ($index >= $limit) {
                $summary[] = '... and more';
                break;
            }
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? '?';
            $function = $item['function'] ?? 'unknown';
            $class = $item['class'] ?? '';
            $type = $item['type'] ?? '';
            $summary[] = sprintf(
                "#%d %s(%d): %s%s%s()",
                $index,
                basename($file),
                $line,
                $class,
                $type,
                $function
            );
        }

        return implode("\n", $summary);
    }

    /**
     * دریافت IP کاربر (برای لاگ)
     */
    private function getClientIp(): string
    {
        $ip = 'CLI';
        if (php_sapi_name() !== 'cli') {
            $headers = [
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
                'REMOTE_ADDR',
            ];

            foreach ($headers as $header) {
                if (isset($_SERVER[$header]) && $_SERVER[$header]) {
                    $ips = explode(',', $_SERVER[$header]);
                    $ip = trim($ips[0]);
                    break;
                }
            }
        }
        return $ip;
    }

    /**
     * دریافت محتوای فایل لاگ
     */
    public function getLogs(int $lines = 100): string
    {
        if (!file_exists($this->logFile)) {
            return '';
        }

        // اجرای دستور tail در صورت وجود
        if (function_exists('exec') && $this->isUnix()) {
            $output = shell_exec("tail -n {$lines} " . escapeshellarg($this->logFile));
            if ($output !== null) {
                return $output;
            }
        }

        // روش جایگزین با PHP
        $content = file($this->logFile);
        if ($content === false) {
            return '';
        }

        return implode('', array_slice($content, -$lines));
    }

    /**
     * پاک کردن لاگ
     */
    public function clear(): bool
    {
        if (file_exists($this->logFile)) {
            return file_put_contents($this->logFile, '') !== false;
        }
        return true;
    }

    /**
     * حذف تمام فایل‌های بکاپ
     */
    public function clearBackups(): void
    {
        for ($i = 1; $i <= $this->backupCount; $i++) {
            $file = $this->logFile . '.' . $i;
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * دریافت آمار لاگ
     * @return array ['size' => int, 'lines' => int, 'backups' => int]
     */
    public function stats(): array
    {
        $stats = [
            'size'    => 0,
            'lines'   => 0,
            'backups' => 0,
        ];

        if (file_exists($this->logFile)) {
            $stats['size'] = filesize($this->logFile);
            $content = file($this->logFile);
            $stats['lines'] = $content ? count($content) : 0;
        }

        for ($i = 1; $i <= $this->backupCount; $i++) {
            $file = $this->logFile . '.' . $i;
            if (file_exists($file)) {
                $stats['backups']++;
            }
        }

        return $stats;
    }

    /**
     * تنظیم سطح لاگ
     */
    public function setMinLevel(string $level): void
    {
        if (isset($this->levels[$level])) {
            $this->minLevel = $level;
        }
    }

    /**
     * تنظیم وضعیت فعال/غیرفعال
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * بررسی فعال بودن لاگ
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * دریافت مسیر فایل لاگ
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * بررسی سیستم عامل
     */
    private function isUnix(): bool
    {
        return DIRECTORY_SEPARATOR === '/';
    }
}