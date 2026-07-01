<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use QuarterTg\Helpers\ValidationHelper;
use Throwable;

/**
 * کلاس مدیریت تنظیمات (Configuration)
 * 
 * ویژگی‌ها:
 * - خواندن تنظیمات از آرایه یا فایل
 * - دسترسی با dot notation (مثلاً database.host)
 * - پشتیبانی از متغیرهای محیطی
 * - کش کردن برای عملکرد بهتر
 * - اعتبارسنجی کلیدهای ضروری
 * - ادغام با تنظیمات دیگر
 */
class Config
{
    /** @var array تنظیمات اصلی */
    private array $config = [];

    /** @var array کش برای کلیدهای دسترسی‌شده */
    private array $cache = [];

    /** @var bool آیا تنظیمات بارگذاری شده است؟ */
    private bool $loaded = false;

    /**
     * @param array|string|null $config آرایه تنظیمات یا مسیر فایل کانفیگ
     */
    public function __construct(array|string|null $config = null)
    {
        if (is_array($config)) {
            $this->config = $config;
            $this->loaded = true;
        } elseif (is_string($config) && file_exists($config)) {
            $this->loadFile($config);
        } else {
            // بارگذاری از فایل پیشفرض
            $defaultFile = __DIR__ . '/../../config/config.php';
            if (file_exists($defaultFile)) {
                $this->loadFile($defaultFile);
            } else {
                $this->config = [];
                $this->loaded = true;
            }
        }
    }

    // ============================================================
    // متدهای اصلی
    // ============================================================

    /**
     * دریافت یک مقدار از تنظیمات با پشتیبانی از dot notation
     * 
     * @param string $key کلید (مثلاً database.host)
     * @param mixed $default مقدار پیشفرض در صورت عدم وجود
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // بررسی کش
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // اگر کلید شامل نقطه نباشد
        if (!str_contains($key, '.')) {
            $value = $this->config[$key] ?? $default;
            $this->cache[$key] = $value;
            return $value;
        }

        // پیمایش درخت با dot notation
        $segments = explode('.', $key);
        $current = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                $this->cache[$key] = $default;
                return $default;
            }
            $current = $current[$segment];
        }

        $this->cache[$key] = $current;
        return $current;
    }

    /**
     * تنظیم یک مقدار در تنظیمات با پشتیبانی از dot notation
     * 
     * @param string $key کلید (مثلاً database.host)
     * @param mixed $value مقدار
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        // پاک کردن کش
        unset($this->cache[$key]);

        if (!str_contains($key, '.')) {
            $this->config[$key] = $value;
            return $this;
        }

        // تنظیم با dot notation
        $segments = explode('.', $key);
        $current = &$this->config;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }

        return $this;
    }

    /**
     * بررسی وجود یک کلید در تنظیمات
     */
    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    /**
     * دریافت کل آرایه تنظیمات
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * ادغام با آرایه دیگر
     */
    public function merge(array $config): self
    {
        $this->config = array_merge_recursive($this->config, $config);
        $this->cache = [];
        return $this;
    }

    /**
     * دریافت مقدار از متغیرهای محیطی (با پشتیبانی از پیشفرض)
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }
        if ($value === null || $value === false) {
            return $default;
        }

        // تبدیل مقادیر boolean
        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }
        if (strtolower($value) === 'null') {
            return null;
        }

        return $value;
    }

    // ============================================================
    // متدهای بارگذاری
    // ============================================================

    /**
     * بارگذاری تنظیمات از فایل
     */
    private function loadFile(string $file): void
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("Config file not found: {$file}");
        }

        try {
            $config = require $file;
            if (is_array($config)) {
                $this->config = $config;
                $this->loaded = true;
            } else {
                throw new \RuntimeException("Config file must return an array: {$file}");
            }
        } catch (Throwable $e) {
            throw new \RuntimeException("Error loading config file: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * بارگذاری مجدد تنظیمات از فایل
     */
    public function reload(): self
    {
        $this->cache = [];
        $this->loaded = false;
        $defaultFile = __DIR__ . '/../../config/config.php';
        if (file_exists($defaultFile)) {
            $this->loadFile($defaultFile);
        }
        return $this;
    }

    // ============================================================
    // متدهای اعتبارسنجی
    // ============================================================

    /**
     * اعتبارسنجی کلیدهای ضروری
     * 
     * @param array $requiredKeys لیست کلیدهای ضروری
     * @throws \RuntimeException در صورت عدم وجود کلید
     */
    public function validateRequired(array $requiredKeys): void
    {
        $missing = [];
        foreach ($requiredKeys as $key) {
            if (!$this->has($key)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Required config keys missing: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * دریافت مقدار و اعتبارسنجی نوع
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : (string)$default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        return is_numeric($value) ? (int)$value : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }
        return (bool)$value;
    }

    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);
        return is_array($value) ? $value : $default;
    }

    // ============================================================
    // متدهای کمکی
    // ============================================================

    /**
     * پاک کردن کش
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * دریافت وضعیت بارگذاری
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * تبدیل به آرایه (برای دیباگ)
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * دریافت یک کلید به صورت شیء (برای دسترسی آسان)
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * تنظیم یک کلید به صورت شیء
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * بررسی وجود کلید به صورت شیء
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    // ============================================================
    // متدهای نمونه (برای استفاده در DI)
    // ============================================================

    /**
     * ایجاد نمونه از Config با بارگذاری از فایل پیشفرض
     */
    public static function createDefault(): self
    {
        return new self();
    }

    /**
     * ایجاد نمونه از Config با بارگذاری از متغیرهای محیطی
     */
    public static function createFromEnv(): self
    {
        $config = [
            'bot_token' => self::env('BOT_TOKEN'),
            'database' => [
                'host' => self::env('DB_HOST', 'localhost'),
                'name' => self::env('DB_NAME', 'quarter_tg'),
                'username' => self::env('DB_USERNAME', 'root'),
                'password' => self::env('DB_PASSWORD', ''),
                'charset' => self::env('DB_CHARSET', 'utf8mb4'),
            ],
            'cache' => [
                'path' => self::env('CACHE_PATH', __DIR__ . '/../../cache'),
                'ttl' => (int)self::env('CACHE_TTL', 3600),
            ],
            'log' => [
                'path' => self::env('LOG_PATH', __DIR__ . '/../../logs/app.log'),
                'level' => self::env('LOG_LEVEL', 'info'),
                'max_size' => (int)self::env('LOG_MAX_SIZE', 10485760),
            ],
            'modules' => [
                'path' => self::env('MODULES_PATH', __DIR__ . '/../Modules'),
                'namespace' => self::env('MODULES_NAMESPACE', 'QuarterTg\\Modules\\'),
            ],
            'owner_id' => (int)self::env('OWNER_ID', 0),
            'webhook' => [
                'secret' => self::env('WEBHOOK_SECRET', ''),
                'allowed_ips' => self::env('ALLOWED_IPS', ''),
            ],
            'timezone' => self::env('TIMEZONE', 'Asia/Tehran'),
            'locale' => self::env('LOCALE', 'fa'),
        ];

        return new self($config);
    }
}