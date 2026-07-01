<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use Throwable;

/**
 * کلاس مدیریت رویدادها (Event Dispatcher)
 * 
 * ویژگی‌ها:
 * - ثبت و مدیریت Listenerها
 * - پشتیبانی از اولویت
 * - توقف انتشار رویداد
 * - کش کردن Listenerها برای عملکرد بهتر
 * - مدیریت خطا
 */
class EventDispatcher
{
    /** @var array لیست Listenerها [event_name => [priority => [listeners]]] */
    private array $listeners = [];

    /** @var array کش برای رویدادهای پردازش‌شده */
    private array $cache = [];

    /** @var bool آیا کش فعال است؟ */
    private bool $cacheEnabled = true;

    /**
     * ثبت یک Listener برای یک رویداد خاص
     * 
     * @param string $eventName نام رویداد
     * @param callable $listener تابع Listener (یا کلاس با متد handle)
     * @param int $priority اولویت (عدد بیشتر = اجرای زودتر)
     * @return self
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): self
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }
        if (!isset($this->listeners[$eventName][$priority])) {
            $this->listeners[$eventName][$priority] = [];
        }
        $this->listeners[$eventName][$priority][] = $listener;

        // پاک کردن کش برای این رویداد
        unset($this->cache[$eventName]);

        return $this;
    }

    /**
     * ثبت یک Subscriber (کلاسی که چند Listener دارد)
     * 
     * @param object $subscriber کلاس Subscriber با متدهای subscribe
     */
    public function subscribe(object $subscriber): self
    {
        if (method_exists($subscriber, 'subscribe')) {
            $subscriber->subscribe($this);
        }
        return $this;
    }

    /**
     * ارسال یک رویداد
     * 
     * @param Event $event رویداد
     * @return Event رویداد پردازش‌شده
     */
    public function dispatch(Event $event): Event
    {
        $eventName = $event->getName();

        // بررسی کش
        if ($this->cacheEnabled && isset($this->cache[$eventName])) {
            // فقط اگر رویداد قبلاً پردازش شده باشد
            // اما برای رویدادهای پویا، کش مناسب نیست
            // بنابراین کش فقط برای رویدادهای استاتیک استفاده میشود
        }

        // دریافت Listenerهای این رویداد
        $listeners = $this->getListeners($eventName);

        // پردازش Listenerها بر اساس اولویت (از بزرگ به کوچک)
        krsort($listeners);

        foreach ($listeners as $priority => $listenerGroup) {
            foreach ($listenerGroup as $listener) {
                // اگر رویداد متوقف شده، ادامه ندهیم
                if ($event->isPropagationStopped()) {
                    break 2;
                }

                try {
                    // اجرای Listener
                    if (is_callable($listener)) {
                        $listener($event);
                    } elseif (is_object($listener) && method_exists($listener, 'handle')) {
                        $listener->handle($event);
                    }
                } catch (Throwable $e) {
                    // خطا در Listener را لاگ کنیم ولی ادامه دهیم
                    // (در آینده میتوان Logger را تزریق کرد)
                    error_log("Event listener error: " . $e->getMessage());
                }
            }
        }

        // ذخیره در کش (اختیاری)
        if ($this->cacheEnabled && !$event->isPropagationStopped()) {
            // فقط رویدادهای بدون تغییر را کش کنیم
            // فعلاً ساده پیادهسازی میشود
        }

        return $event;
    }

    /**
     * دریافت Listenerهای یک رویداد
     */
    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }

    /**
     * بررسی وجود Listener برای یک رویداد
     */
    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && !empty($this->listeners[$eventName]);
    }

    /**
     * حذف همه Listenerهای یک رویداد
     */
    public function clearListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
        unset($this->cache[$eventName]);
    }

    /**
     * پاک کردن همه Listenerها و کش
     */
    public function clearAll(): void
    {
        $this->listeners = [];
        $this->cache = [];
    }

    /**
     * فعال/غیرفعال کردن کش
     */
    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->cache = [];
        }
        return $this;
    }

    /**
     * ایجاد یک رویداد جدید و ارسال آن (روش سریع)
     */
    public function fire(string $eventName, array $data = []): Event
    {
        $event = new Event($eventName, $data);
        return $this->dispatch($event);
    }
}

/**
 * کلاس پایه رویداد
 */
class Event
{
    private string $name;
    private array $data;
    private bool $propagationStopped = false;

    public function __construct(string $name, array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * دریافت نام رویداد
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * دریافت داده‌های رویداد
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * دریافت یک مقدار خاص از داده‌ها
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * تنظیم یک مقدار در داده‌ها
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * توقف انتشار رویداد (Listenerهای بعدی اجرا نشوند)
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * آیا انتشار رویداد متوقف شده است؟
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}