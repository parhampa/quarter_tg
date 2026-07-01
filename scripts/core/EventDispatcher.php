<?php

declare(strict_types=1);

namespace QuarterTg\Core;

use Throwable;

/**
 * سیستم مدیریت رویدادها (Event Dispatcher)
 */
class EventDispatcher
{
    private array $listeners = [];
    private array $cache = [];
    private bool $cacheEnabled = true;

    public function listen(string $eventName, callable $listener, int $priority = 0): self
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }
        if (!isset($this->listeners[$eventName][$priority])) {
            $this->listeners[$eventName][$priority] = [];
        }
        $this->listeners[$eventName][$priority][] = $listener;
        unset($this->cache[$eventName]);
        return $this;
    }

    public function subscribe(object $subscriber): self
    {
        if (method_exists($subscriber, 'subscribe')) {
            $subscriber->subscribe($this);
        }
        return $this;
    }

    public function dispatch(Event $event): Event
    {
        $eventName = $event->getName();
        $listeners = $this->getListeners($eventName);
        krsort($listeners);

        foreach ($listeners as $priority => $listenerGroup) {
            foreach ($listenerGroup as $listener) {
                if ($event->isPropagationStopped()) {
                    break 2;
                }
                try {
                    if (is_callable($listener)) {
                        $listener($event);
                    } elseif (is_object($listener) && method_exists($listener, 'handle')) {
                        $listener->handle($event);
                    }
                } catch (Throwable $e) {
                    error_log("Event listener error: " . $e->getMessage());
                }
            }
        }
        return $event;
    }

    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }

    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]) && !empty($this->listeners[$eventName]);
    }

    public function clearListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
        unset($this->cache[$eventName]);
    }

    public function clearAll(): void
    {
        $this->listeners = [];
        $this->cache = [];
    }

    public function setCacheEnabled(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        if (!$enabled) {
            $this->cache = [];
        }
        return $this;
    }

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}