<?php
namespace Core;

class Cache
{
    private $cacheDir;
    private $ttl;

    public function __construct(string $cacheDir, int $ttl = 300)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/';
        $this->ttl = $ttl;
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }

    public function get(string $key)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file) || (time() - filemtime($file) > $this->ttl)) {
            return null;
        }
        $data = file_get_contents($file);
        return $data ? unserialize($data) : null;
    }

    public function set(string $key, $value): void
    {
        file_put_contents($this->getFilePath($key), serialize($value), LOCK_EX);
    }

    public function delete(string $key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}