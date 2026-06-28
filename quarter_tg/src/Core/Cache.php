<?php

namespace QuarterTg\Core;

/**
 * کلاس مدیریت کش فایل‌بنیاد با پشتیبانی از TTL
 * مناسب برای کاهش بار دیتابیس و افزایش سرعت
 */
class Cache
{
    private $cacheDir;
    private $defaultTtl;
    private $enabled;

    /**
     * @param string $cacheDir مسیر دایرکتوری کش (با اسلش انتهایی)
     * @param int $defaultTtl زمان انقضای پیش‌فرض بر حسب ثانیه
     * @param bool $enabled فعال/غیرفعال کردن کش
     */
    public function __construct(string $cacheDir, int $defaultTtl = 300, bool $enabled = true)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->defaultTtl = $defaultTtl;
        $this->enabled = $enabled;

        // ایجاد دایرکتوری کش در صورت عدم وجود
        if ($this->enabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * دریافت مقدار از کش
     * @param string $key کلید کش
     * @param mixed $default مقدار پیش‌فرض در صورت عدم وجود یا انقضا
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->enabled) {
            return $default;
        }

        $file = $this->getCacheFile($key);
        if (!file_exists($file)) {
            return $default;
        }

        $data = $this->readFile($file);
        if ($data === null) {
            return $default;
        }

        // بررسی انقضا
        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * ذخیره مقدار در کش
     * @param string $key کلید کش
     * @param mixed $value مقدار
     * @param int|null $ttl زمان انقضا (ثانیه) – اگر null باشد از defaultTtl استفاده می‌شود
     * @return bool
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $ttl = $ttl ?? $this->defaultTtl;
        $expires = $ttl > 0 ? time() + $ttl : null;

        $data = [
            'value'   => $value,
            'expires' => $expires,
            'created' => time(),
        ];

        $file = $this->getCacheFile($key);
        return $this->writeFile($file, $data);
    }

    /**
     * حذف یک کلید از کش
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    /**
     * بررسی وجود کلید در کش (بدون در نظر گرفتن انقضا)
     */
    public function exists(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $file = $this->getCacheFile($key);
        return file_exists($file);
    }

    /**
     * بررسی معتبر بودن کلید (وجود و عدم انقضا)
     */
    public function isValid(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $value = $this->get($key);
        return $value !== null;
    }

    /**
     * پاک کردن تمام کش
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $files = glob($this->cacheDir . '*.cache');
        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * حذف کش‌های منقضی‌شده
     * @return int تعداد فایل‌های حذف‌شده
     */
    public function garbageCollect(): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $files = glob($this->cacheDir . '*.cache');
        $removed = 0;
        foreach ($files as $file) {
            $data = $this->readFile($file);
            if ($data && $data['expires'] !== null && $data['expires'] < time()) {
                if (unlink($file)) {
                    $removed++;
                }
            }
        }
        return $removed;
    }

    /**
     * دریافت آمار کش
     * @return array ['total' => int, 'valid' => int, 'expired' => int]
     */
    public function stats(): array
    {
        if (!$this->enabled) {
            return ['total' => 0, 'valid' => 0, 'expired' => 0];
        }

        $files = glob($this->cacheDir . '*.cache');
        $total = count($files);
        $valid = 0;
        $expired = 0;

        foreach ($files as $file) {
            $data = $this->readFile($file);
            if ($data === null) {
                continue;
            }
            if ($data['expires'] !== null && $data['expires'] < time()) {
                $expired++;
            } else {
                $valid++;
            }
        }

        return [
            'total'   => $total,
            'valid'   => $valid,
            'expired' => $expired,
        ];
    }

    /**
     * دریافت مسیر فایل کش بر اساس کلید
     */
    private function getCacheFile(string $key): string
    {
        // استفاده از md5 برای امنیت و جلوگیری از مشکلات مسیر
        $hash = md5($key);
        return $this->cacheDir . $hash . '.cache';
    }

    /**
     * خواندن فایل کش و بازگرداندن آرایه داده
     * @return array|null
     */
    private function readFile(string $file): ?array
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false || !is_array($data) || !isset($data['value'])) {
            return null;
        }

        return $data;
    }

    /**
     * نوشتن فایل کش با داده‌های سریالایز شده
     */
    private function writeFile(string $file, array $data): bool
    {
        $content = serialize($data);
        return file_put_contents($file, $content, LOCK_EX) !== false;
    }

    /**
     * فعال/غیرفعال کردن کش در زمان اجرا
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * بررسی وضعیت فعال بودن کش
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * دریافت TTL پیش‌فرض
     */
    public function getDefaultTtl(): int
    {
        return $this->defaultTtl;
    }

    /**
     * تنظیم TTL پیش‌فرض جدید
     */
    public function setDefaultTtl(int $ttl): void
    {
        $this->defaultTtl = $ttl;
    }
}