<?php
namespace Core;

class RateLimiter
{
    private string $key;
    private int $max;
    private int $window;
    private bool $useFile;

    public function __construct(string $key, int $max, int $windowSeconds, bool $useFileBackend = false)
    {
        $this->key = $key;
        $this->max = $max;
        $this->window = $windowSeconds;
        $this->useFile = $useFileBackend;
    }

    public function tooManyAttempts(): bool
    {
        if ($this->useFile) {
            return $this->fileCheck();
        }
        $now = time();
        if (!isset($_SESSION['rl'][$this->key])) {
            $_SESSION['rl'][$this->key] = ['count' => 0, 'time' => $now];
        }
        $entry = $_SESSION['rl'][$this->key];
        if ($now - $entry['time'] > $this->window) {
            $_SESSION['rl'][$this->key] = ['count' => 0, 'time' => $now];
            return false;
        }
        return $entry['count'] >= $this->max;
    }

    public function hit(): void
    {
        if ($this->useFile) {
            $this->fileHit();
            return;
        }
        $now = time();
        if (!isset($_SESSION['rl'][$this->key])) {
            $_SESSION['rl'][$this->key] = ['count' => 0, 'time' => $now];
        }
        $entry = $_SESSION['rl'][$this->key];
        if ($now - $entry['time'] > $this->window) {
            $entry = ['count' => 0, 'time' => $now];
        }
        $entry['count']++;
        $entry['time'] = $now;
        $_SESSION['rl'][$this->key] = $entry;
    }

    private function filePath(): string
    {
        $dir = APP_ROOT . '/storage/tmp/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . sha1($this->key) . '.json';
    }

    private function fileCheck(): bool
    {
        $file = $this->filePath();
        if (!file_exists($file)) {
            return false;
        }
        $data = json_decode(file_get_contents($file), true) ?: ['count' => 0, 'time' => time()];
        $now = time();
        if ($now - ($data['time'] ?? 0) > $this->window) {
            return false;
        }
        return ($data['count'] ?? 0) >= $this->max;
    }

    private function fileHit(): void
    {
        $file = $this->filePath();
        $data = ['count' => 0, 'time' => time()];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: $data;
        }
        $now = time();
        if ($now - ($data['time'] ?? 0) > $this->window) {
            $data = ['count' => 0, 'time' => $now];
        }
        $data['count'] = ($data['count'] ?? 0) + 1;
        $data['time'] = $now;
        file_put_contents($file, json_encode($data));
    }
}
