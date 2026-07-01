<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use Throwable;

/**
 * کلاس مدیریت لاگ‌ها
 * 
 * ویژگی‌ها:
 * - سطوح لاگ: debug, info, warning, error, critical
 * - چرخش خودکار فایل (Log Rotation)
 * - قفل فایل برای نوشتن همزمان
 * - فرمت استاندارد با تاریخ و Context
 * - مدیریت خطا (برنامه کرش نمیکند)
 * - قابلیت افزودن Handlerهای جدید
 */
class Logger
{
    /** @var array سطوح لاگ با اولویت */
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4,
    ];

    private string $logFile;
    private string $level;
    private int $maxSize;
    private int $maxFiles;
    private array $handlers = [];

    /**
     * @param string $logFile مسیر فایل لاگ
     * @param string $level سطح حداقل لاگ (debug, info, warning, error, critical)
     * @param int $maxSize حداکثر حجم فایل به بایت (پیشفرض ۱۰MB)
     * @param int $maxFiles تعداد فایل‌های بایگانی (پیشفرض ۵)
     */
    public function __construct(
        string $logFile,
        string $level = 'info',
        int $maxSize = 10485760,
        int $maxFiles = 5
    ) {
        $this->logFile = $logFile;
        $this->level = $level;
        $this->maxSize = $maxSize;
        $this->maxFiles = $maxFiles;

        // ایجاد دایرکتوری لاگ اگر وجود ندارد
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // افزودن Handler پیشفرض (فایل)
        $this->handlers[] = function (string $message) {
            $this->writeToFile($message);
        };
    }

    // ============================================================
    // متدهای اصلی لاگ
    // ============================================================

    /**
     * ثبت لاگ در سطح debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * ثبت لاگ در سطح info
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * ثبت لاگ در سطح warning
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * ثبت لاگ در سطح error
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * ثبت لاگ در سطح critical
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /**
     * متد اصلی لاگ
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // بررسی سطح
        if (!$this->isLevelEnabled($level)) {
            return;
        }

        // ساخت پیام
        $formatted = $this->formatMessage($level, $message, $context);

        // ارسال به همه Handlerها
        foreach ($this->handlers as $handler) {
            try {
                $handler($formatted);
            } catch (Throwable $e) {
                // خطا در Handler را نادیده بگیرید تا برنامه کرش نکند
                // اما در صورت امکان، خطا را به خروجی خطا بفرستید
                error_log('Logger handler error: ' . $e->getMessage());
            }
        }
    }

    // ============================================================
    // متدهای Handler
    // ============================================================

    /**
     * افزودن Handler سفارشی
     */
    public function addHandler(callable $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * پاک کردن همه Handlerها (به جز پیشفرض)
     */
    public function clearHandlers(): void
    {
        $this->handlers = [
            function (string $message) {
                $this->writeToFile($message);
            },
        ];
    }

    // ============================================================
    // متدهای داخلی
    // ============================================================

    /**
     * بررسی فعال بودن سطح لاگ
     */
    private function isLevelEnabled(string $level): bool
    {
        $currentLevel = self::LEVELS[$this->level] ?? 0;
        $requestedLevel = self::LEVELS[$level] ?? 0;
        return $requestedLevel >= $currentLevel;
    }

    /**
     * فرمت کردن پیام لاگ
     */
    private function formatMessage(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $pid = getmypid();

        // اگر Context وجود دارد، به صورت JSON در انتهای پیام اضافه میشود
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        return "[{$timestamp}] [{$levelUpper}] [PID:{$pid}] {$message}{$contextStr}\n";
    }

    /**
     * نوشتن پیام در فایل (با قفل و چرخش)
     */
    private function writeToFile(string $message): void
    {
        $logFile = $this->logFile;

        // چرخش فایل اگر حجم از حد مجاز بیشتر شده باشد
        if (file_exists($logFile) && filesize($logFile) >= $this->maxSize) {
            $this->rotateLogs();
        }

        // نوشتن با قفل
        $fp = @fopen($logFile, 'ab');
        if ($fp === false) {
            return; // اگر فایل قابل نوشتن نبود، نادیده بگیرید
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $message);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * چرخش فایل‌های لاگ
     */
    private function rotateLogs(): void
    {
        $baseFile = $this->logFile;
        $dir = dirname($baseFile);
        $baseName = basename($baseFile);

        // حذف قدیمی‌ترین فایل (با شماره maxFiles)
        $oldestFile = $dir . DIRECTORY_SEPARATOR . $baseName . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            @unlink($oldestFile);
        }

        // تغییر نام فایل‌های قبلی (از maxFiles-1 تا 1)
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $dir . DIRECTORY_SEPARATOR . $baseName . '.' . $i;
            $newFile = $dir . DIRECTORY_SEPARATOR . $baseName . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }

        // تغییر نام فایل اصلی به شماره 1
        if (file_exists($baseFile)) {
            @rename($baseFile, $dir . DIRECTORY_SEPARATOR . $baseName . '.1');
        }
    }

    // ============================================================
    // متدهای تنظیمات
    // ============================================================

    /**
     * تغییر سطح لاگ
     */
    public function setLevel(string $level): void
    {
        if (isset(self::LEVELS[$level])) {
            $this->level = $level;
        }
    }

    /**
     * دریافت سطح فعلی لاگ
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * تنظیم حداکثر حجم فایل
     */
    public function setMaxSize(int $maxSize): void
    {
        $this->maxSize = $maxSize;
    }

    /**
     * تنظیم تعداد فایل‌های بایگانی
     */
    public function setMaxFiles(int $maxFiles): void
    {
        $this->maxFiles = $maxFiles;
    }

    /**
     * دریافت مسیر فایل لاگ
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}