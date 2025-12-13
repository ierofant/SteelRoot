<?php
namespace Core;

class Cache
{
    private string $path;
    private int $defaultTtl;

    public function __construct(string $path, int $defaultTtl)
    {
        $this->path = $path;
        $this->defaultTtl = $defaultTtl;
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function set(string $key, $value, ?int $ttl = null): void
    {
        $file = $this->file($key);
        $payload = [
            'expires' => time() + ($ttl ?? $this->defaultTtl),
            'value' => $value,
        ];
        file_put_contents($file, serialize($payload));
    }

    public function get(string $key)
    {
        $file = $this->file($key);
        if (!file_exists($file)) {
            return null;
        }
        $payload = @unserialize(file_get_contents($file));
        if (!$payload || ($payload['expires'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }
        return $payload['value'];
    }

    public function delete(string $key): void
    {
        $file = $this->file($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function clear(): void
    {
        foreach (glob($this->path . '/*.cache') as $file) {
            @unlink($file);
        }
    }

    private function file(string $key): string
    {
        return $this->path . '/' . sha1($key) . '.cache';
    }
}
