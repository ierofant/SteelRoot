<?php
namespace App\Services;

use Core\Cache;
use Core\Database;
use Core\Logger;

class RedirectService
{
    private Database $db;
    private Cache $cache;
    private string $cacheKey = 'redirects.map';

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Resolve path to redirect target or null.
     */
    public function resolve(string $path): ?array
    {
        $map = $this->getMap();
        $key = $this->normalize($path);
        return $map[$key] ?? null;
    }

    /**
     * Increment hits and touch last_hit for analytics.
     */
    public function touch(int $id): void
    {
        try {
            $this->db->execute("UPDATE redirects SET hits = hits + 1, last_hit = NOW() WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            Logger::log('Redirect touch failed: ' . $e->getMessage());
        }
    }

    public function create(string $fromPath, string $toUrl, int $status = 301): void
    {
        $fromPath = $this->normalize($fromPath);
        $status = in_array($status, [301, 302, 307, 308], true) ? $status : 301;
        $this->db->execute(
            "INSERT INTO redirects (from_path, to_url, status_code, active, created_at) VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE to_url = VALUES(to_url), status_code = VALUES(status_code), active = VALUES(active)",
            [$fromPath, $toUrl, $status]
        );
        $this->clearCache();
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM redirects ORDER BY id DESC");
    }

    public function clearCache(): void
    {
        $this->cache->delete($this->cacheKey);
    }

    public function rebuildCache(): void
    {
        $this->cache->delete($this->cacheKey);
        $this->getMap();
    }

    private function getMap(): array
    {
        $cached = $this->cache->get($this->cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $rows = $this->db->fetchAll("SELECT id, from_path, to_url, status_code FROM redirects WHERE active = 1");
        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalize($row['from_path']);
            $map[$key] = $row;
        }
        $this->cache->set($this->cacheKey, $map);
        return $map;
    }

    private function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }
}
