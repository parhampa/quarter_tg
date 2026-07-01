<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use Throwable;

/**
 * کلاس مدیریت کش فایل‌بنیاد با قفل و Garbage Collection خودکار
 * 
 * ویژگیها:
 * - قفل فایل برای جلوگیری از Race Condition
 * - Garbage Collection خودکار (حذف فایل‌های منقضی شده)
 * - پشتیبانی از TTL جداگانه برای هر کلید
 * - ذخیرهسازی در پوشههای سطحبندی شده برای عملکرد بهتر
 * - مدیریت کامل خطاها
 */
class Cache
{
    private string $cacheDir;
    private int $defaultTtl;
    private Logger $logger;
    private bool $gcEnabled;
    private int $gcProbability = 100; // 1 به 100 (1%)
    private int $maxDepth = 2; // عمق پوشهها برای شارد کردن

    public function __construct(
        string $cacheDir,
        int $defaultTtl = 3600,
        Logger $logger,
        bool $gcEnabled = true
    ) {
        $this->cacheDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->defaultTtl = $defaultTtl;
        $this->logger = $logger;
        $this->gcEnabled = $gcEnabled;

        // ایجاد دایرکتوری کش اگر وجود ندارد
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new \RuntimeException("Cannot create cache directory: {$this->cacheDir}");
            }
        }

        // اجرای Garbage Collection با احتمال مشخص
        if ($this->gcEnabled && mt_rand(1, 100) <= $this->gcProbability) {
            $this->garbageCollect();
        }
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * ذخیره یک مقدار در کش
     * 
     * @param string $key کلید (فقط کاراکترهای مجاز: a-zA-Z0-9_-. )
     * @param mixed $value مقدار (قابل serialize)
     * @param int|null $ttl زمان انقضا به ثانیه (null = استفاده از پیشفرض)
     * @return bool موفقیت
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->validateKey($key);
        
        $ttl = $ttl ?? $this->defaultTtl;
        $expiry = time() + $ttl;
        
        $data = [
            'expiry' => $expiry,
            'value'  => $value,
        ];
        
        $serialized = serialize($data);
        $filePath = $this->getFilePath($key);
        $tempFile = $filePath . '.tmp';
        
        // نوشتن در فایل موقت و سپس تغییر نام (Atomic)
        if (file_put_contents($tempFile, $serialized, LOCK_EX) === false) {
            $this->logger->error('Failed to write cache file.', ['key' => $key, 'path' => $tempFile]);
            return false;
        }
        
        // تغییر نام اتمی (در اکثر سیستم‌ها)
        if (!rename($tempFile, $filePath)) {
            $this->logger->error('Failed to rename cache file.', ['key' => $key, 'from' => $tempFile, 'to' => $filePath]);
            @unlink($tempFile);
            return false;
        }
        
        $this->logger->debug('Cache set.', ['key' => $key, 'ttl' => $ttl, 'expiry' => $expiry]);
        return true;
    }

    /**
     * دریافت مقدار از کش
     * 
     * @param string $key کلید
     * @return mixed|null مقدار یا null در صورت عدم وجود یا انقضا
     */
    public function get(string $key): mixed
    {
        $this->validateKey($key);
        
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }
        
        // خواندن با قفل اشتراکی
        $fp = fopen($filePath, 'rb');
        if ($fp === false) {
            $this->logger->error('Failed to open cache file for reading.', ['key' => $key]);
            return null;
        }
        
        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            $this->logger->error('Failed to acquire shared lock.', ['key' => $key]);
            return null;
        }
        
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        
        if ($content === false) {
            $this->logger->error('Failed to read cache file.', ['key' => $key]);
            return null;
        }
        
        $data = @unserialize($content);
        if ($data === false) {
            $this->logger->error('Failed to unserialize cache data.', ['key' => $key]);
            @unlink($filePath); // حذف فایل خراب
            return null;
        }
        
        // بررسی انقضا
        if (isset($data['expiry']) && $data['expiry'] < time()) {
            $this->logger->debug('Cache expired.', ['key' => $key]);
            @unlink($filePath);
            return null;
        }
        
        return $data['value'] ?? null;
    }

    /**
     * حذف یک کلید از کش
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return true; // قبلاً حذف شده
        }
        
        $result = @unlink($filePath);
        if ($result) {
            $this->logger->debug('Cache deleted.', ['key' => $key]);
        } else {
            $this->logger->error('Failed to delete cache file.', ['key' => $key]);
        }
        return $result;
    }

    /**
     * بررسی وجود کلید در کش (بدون خواندن مقدار)
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);
        
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return false;
        }
        
        // بررسی انقضا (بدون خواندن کامل)
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return false;
        }
        
        $data = @unserialize($content);
        if ($data === false || !isset($data['expiry'])) {
            return false;
        }
        
        if ($data['expiry'] < time()) {
            @unlink($filePath);
            return false;
        }
        
        return true;
    }

    /**
     * دریافت یا ذخیره (Get or Set) - اگر کلید موجود باشد، مقدار را برمیگرداند، در غیر این صورت مقدار جدید را محاسبه و ذخیره میکند
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * پاک کردن همه کش
     */
    public function clear(): bool
    {
        $this->logger->info('Clearing all cache.');
        return $this->deleteDirectory($this->cacheDir);
    }

    // ============================================================
    // Garbage Collection
    // ============================================================

    /**
     * حذف فایل‌های منقضی شده از دایرکتوری کش
     */
    public function garbageCollect(): int
    {
        $count = 0;
        $this->logger->debug('Starting garbage collection.');
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // خوندن فقط هدر فایل برای بررسی انقضا (بهینه)
                $fp = fopen($file->getPathname(), 'rb');
                if ($fp === false) {
                    continue;
                }
                
                if (!flock($fp, LOCK_SH)) {
                    fclose($fp);
                    continue;
                }
                
                // خواندن ۱۰۰ بایت اول (معمولاً کافی برای هدر)
                $header = fread($fp, 100);
                flock($fp, LOCK_UN);
                fclose($fp);
                
                if ($header === false) {
                    continue;
                }
                
                // بررسی سریع انقضا
                if (preg_match('/expiry";i:(\d+)/', $header, $matches)) {
                    $expiry = (int)$matches[1];
                    if ($expiry < time()) {
                        @unlink($file->getPathname());
                        $count++;
                    }
                }
            }
        }
        
        $this->logger->info('Garbage collection completed.', ['removed' => $count]);
        return $count;
    }

    /**
     * تنظیم احتمال اجرای Garbage Collection (از 1 تا 100)
     */
    public function setGcProbability(int $probability): self
    {
        $this->gcProbability = max(1, min(100, $probability));
        return $this;
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * اعتبارسنجی کلید (فقط کاراکترهای مجاز)
     */
    private function validateKey(string $key): void
    {
        if (empty($key) || strlen($key) > 255) {
            throw new \InvalidArgumentException("Cache key must be non-empty and max 255 characters.");
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $key)) {
            throw new \InvalidArgumentException("Cache key contains invalid characters. Allowed: a-zA-Z0-9_-.");
        }
    }

    /**
     * تولید مسیر فایل بر اساس کلید (با شارد کردن در پوشه‌های سطحبندی شده)
     */
    private function getFilePath(string $key): string
    {
        // استفاده از هش برای شارد کردن
        $hash = md5($key);
        $pathParts = [];
        
        // ایجاد پوشه‌های سطحبندی شده
        for ($i = 0; $i < $this->maxDepth; $i++) {
            $pathParts[] = substr($hash, $i * 2, 2);
        }
        
        $dirPath = $this->cacheDir . implode(DIRECTORY_SEPARATOR, $pathParts);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                $this->logger->error('Failed to create cache subdirectory.', ['path' => $dirPath]);
                // اگر نتوانست پوشه بسازد، از مسیر اصلی استفاده میکند
                $dirPath = $this->cacheDir;
            }
        }
        
        return $dirPath . DIRECTORY_SEPARATOR . $hash . '.cache';
    }

    /**
     * حذف یک دایرکتوری به صورت بازگشتی
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }
        
        $items = scandir($dir);
        if ($items === false) {
            return false;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        
        return @rmdir($dir);
    }

    // ============================================================
    // متدهای آماری (برای دیباگ)
    // ============================================================

    /**
     * تعداد کل فایل‌های کش
     */
    public function getFileCount(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * حجم کل کش به بایت
     */
    public function getTotalSize(): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    /**
     * دریافت اطلاعات آماری
     */
    public function getStats(): array
    {
        return [
            'file_count' => $this->getFileCount(),
            'total_size' => $this->getTotalSize(),
            'cache_dir'  => $this->cacheDir,
            'default_ttl' => $this->defaultTtl,
            'gc_enabled' => $this->gcEnabled,
            'gc_probability' => $this->gcProbability,
        ];
    }
}