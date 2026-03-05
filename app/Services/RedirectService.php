<?php
namespace App\Services;

use Core\Cache;
use Core\Database;
use Core\Logger;

class RedirectService
{
    private Database $db;
    private Cache $cache;
    private string $cacheKey = 'redirects.v2';

    public function __construct(Database $db, Cache $cache)
    {
        $this->db    = $db;
        $this->cache = $cache;
    }

    /**
     * Resolve path to redirect target or null.
     * Returns array with keys: id, from_path, to_url, status_code, is_regexp
     */
    public function resolve(string $path): ?array
    {
        [$exact, $regexp] = $this->getMaps();

        // 1. Fast exact lookup
        $key = $this->normalize($path);
        if (isset($exact[$key])) {
            return $exact[$key];
        }

        // 2. Iterate regexp rules (ordered by id asc — first match wins)
        foreach ($regexp as $row) {
            $pattern = $row['from_path'];
            if (@preg_match($pattern, $path) === 1) {
                $to = @preg_replace($pattern, $row['to_url'], $path);
                if ($to !== null && $to !== false) {
                    return array_merge($row, ['to_url' => $to]);
                }
            }
        }

        return null;
    }

    /**
     * Increment hits counter.
     */
    public function touch(int $id): void
    {
        try {
            $this->db->execute("UPDATE redirects SET hits = hits + 1, last_hit = NOW() WHERE id = ?", [$id]);
        } catch (\Throwable $e) {
            Logger::log('Redirect touch failed: ' . $e->getMessage());
        }
    }

    /**
     * Create or update a redirect.
     * For exact redirects deduplicates on from_path.
     * For regexp inserts a fresh row each time (patterns managed manually).
     */
    public function create(string $fromPath, string $toUrl, int $status = 301, bool $isRegexp = false): ?string
    {
        $status = in_array($status, [301, 302, 307, 308], true) ? $status : 301;

        if ($isRegexp) {
            $error = $this->validatePattern($fromPath);
            if ($error !== null) {
                return $error;
            }
            $this->db->execute(
                "INSERT INTO redirects (from_path, to_url, status_code, is_regexp, active, created_at)
                 VALUES (?, ?, ?, 1, 1, NOW())",
                [$fromPath, $toUrl, $status]
            );
        } else {
            $fromPath = $this->normalize($fromPath);
            $this->db->execute(
                "INSERT INTO redirects (from_path, to_url, status_code, is_regexp, active, created_at)
                 VALUES (?, ?, ?, 0, 1, NOW())
                 ON DUPLICATE KEY UPDATE to_url = VALUES(to_url), status_code = VALUES(status_code), active = 1",
                [$fromPath, $toUrl, $status]
            );
        }

        $this->clearCache();
        return null;
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM redirects WHERE id = ?", [$id]);
        $this->clearCache();
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM redirects ORDER BY id DESC");
    }

    public function clearCache(): void
    {
        $this->cache->delete($this->cacheKey);
        // also clear old cache key in case it exists
        $this->cache->delete('redirects.map');
    }

    public function rebuildCache(): void
    {
        $this->clearCache();
        $this->getMaps();
    }

    /**
     * Validate a regexp pattern for safety.
     * Returns null on success, error string on failure.
     */
    public function validatePattern(string $pattern): ?string
    {
        if (strlen($pattern) > 512) {
            return 'Pattern too long (max 512 characters)';
        }
        // Must be a valid delimited regexp
        if (strlen($pattern) < 2) {
            return 'Pattern is too short';
        }
        // Temporarily lower backtrack limit to catch catastrophic backtracking
        $prevLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '100000');
        $result = @preg_match($pattern, '');
        ini_set('pcre.backtrack_limit', $prevLimit);

        if ($result === false) {
            return 'Invalid regular expression: ' . (preg_last_error_msg() ?: 'syntax error');
        }
        return null;
    }

    private function getMaps(): array
    {
        $cached = $this->cache->get($this->cacheKey);
        if (is_array($cached) && isset($cached[0], $cached[1])) {
            return $cached;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, from_path, to_url, status_code, is_regexp FROM redirects WHERE active = 1 ORDER BY id ASC"
        );

        $exact  = [];
        $regexp = [];
        foreach ($rows as $row) {
            if (!empty($row['is_regexp'])) {
                $regexp[] = $row;
            } else {
                $key         = $this->normalize($row['from_path']);
                $exact[$key] = $row;
            }
        }

        $maps = [$exact, $regexp];
        $this->cache->set($this->cacheKey, $maps);
        return $maps;
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
