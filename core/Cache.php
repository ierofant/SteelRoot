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
            'key'     => $key,
            'value'   => $value,
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
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Return metadata for all valid cache entries.
     * @return array<int, array{key:string, expires:int, size:int, type:string}>
     */
    public function entries(): array
    {
        $result = [];
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false) continue;
            $payload = @unserialize($raw);
            if (!is_array($payload)) continue;
            $expires = (int)($payload['expires'] ?? 0);
            if ($expires > 0 && $expires < time()) {
                @unlink($file);
                continue;
            }
            $value = $payload['value'] ?? null;
            $type  = is_string($value) ? 'html' : (is_array($value) ? 'array' : gettype($value));
            $result[] = [
                'key'     => $payload['key'] ?? basename($file, '.cache'),
                'expires' => $expires,
                'size'    => strlen($raw),
                'type'    => $type,
            ];
        }
        usort($result, fn($a, $b) => $a['expires'] <=> $b['expires']);
        return $result;
    }

    private function file(string $key): string
    {
        return $this->path . '/' . sha1($key) . '.cache';
    }
}
