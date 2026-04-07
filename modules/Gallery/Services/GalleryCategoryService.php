<?php
namespace Modules\Gallery\Services;

use Core\Cache;
use Core\Database;
use Core\Logger;

class GalleryCategoryService
{
    private const ENABLED_CACHE_KEY = 'gallery.categories.enabled.v1';
    private const ENABLED_CACHE_TTL = 1800;

    private Database $db;
    private ?Cache $cache;

    public function __construct(Database $db, ?Cache $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function all(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM gallery_categories ORDER BY position ASC, id ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function enabled(): array
    {
        if ($this->cache !== null) {
            $cached = $this->cache->get(self::ENABLED_CACHE_KEY);
            if (is_array($cached)) {
                $this->logCache('HIT');
                return $cached;
            }
            $this->logCache('MISS');
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM gallery_categories WHERE enabled = 1 ORDER BY position ASC, id ASC"
            );
            $this->cache?->set(self::ENABLED_CACHE_KEY, $rows, self::ENABLED_CACHE_TTL);
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(int $id): ?array
    {
        try {
            $row = $this->db->fetch("SELECT * FROM gallery_categories WHERE id = ?", [$id]);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function findBySlug(string $slug): ?array
    {
        try {
            $row = $this->db->fetch("SELECT * FROM gallery_categories WHERE slug = ?", [$slug]);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function create(array $data): int
    {
        $slug = $this->generateSlug($data['slug'] ?? $data['name_en'] ?? '');
        $this->db->execute(
            "INSERT INTO gallery_categories (
                slug, name_en, name_ru, meta_title_en, meta_title_ru, image_url,
                meta_description_en, meta_description_ru, position, enabled
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $slug,
                $data['name_en'] ?? '',
                $data['name_ru'] ?? '',
                $data['meta_title_en'] ?? '',
                $data['meta_title_ru'] ?? '',
                $data['image_url'] ?? '',
                $data['meta_description_en'] ?? '',
                $data['meta_description_ru'] ?? '',
                (int)($data['position'] ?? 0),
                isset($data['enabled']) ? (int)(bool)$data['enabled'] : 1,
            ]
        );
        $this->invalidateEnabledCache();
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (!$existing) {
            return;
        }
        $slug = $this->generateSlug($data['slug'] ?? $data['name_en'] ?? $existing['slug'], $id);
        $this->db->execute(
            "UPDATE gallery_categories
             SET slug = ?, name_en = ?, name_ru = ?, meta_title_en = ?, meta_title_ru = ?,
                 image_url = ?, meta_description_en = ?, meta_description_ru = ?, position = ?, enabled = ?
             WHERE id = ?",
            [
                $slug,
                $data['name_en'] ?? $existing['name_en'],
                $data['name_ru'] ?? $existing['name_ru'],
                $data['meta_title_en'] ?? ($existing['meta_title_en'] ?? ''),
                $data['meta_title_ru'] ?? ($existing['meta_title_ru'] ?? ''),
                $data['image_url'] ?? $existing['image_url'],
                $data['meta_description_en'] ?? ($existing['meta_description_en'] ?? ''),
                $data['meta_description_ru'] ?? ($existing['meta_description_ru'] ?? ''),
                (int)($data['position'] ?? $existing['position']),
                isset($data['enabled']) ? (int)(bool)$data['enabled'] : (int)$existing['enabled'],
                $id,
            ]
        );
        $this->invalidateEnabledCache();
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM gallery_categories WHERE id = ?", [$id]);
        $this->invalidateEnabledCache();
    }

    public function generateSlug(string $name, ?int $exceptId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        if ($base === '') {
            $base = 'category';
        }
        $slug = $base;
        $i = 2;
        while (true) {
            $sql = "SELECT id FROM gallery_categories WHERE slug = ?";
            $params = [$slug];
            if ($exceptId !== null) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            $existing = $this->db->fetch($sql, $params);
            if (!$existing) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function invalidateEnabledCache(): void
    {
        $this->cache?->delete(self::ENABLED_CACHE_KEY);
        $this->logCache('BUST');
    }

    private function logCache(string $state): void
    {
        if (!$this->debugEnabled()) {
            return;
        }
        Logger::log('[cache][gallery.categories.enabled] ' . $state);
    }

    private function debugEnabled(): bool
    {
        static $enabled = null;
        if ($enabled === null) {
            $config = include APP_ROOT . '/app/config/app.php';
            $enabled = !empty($config['debug']);
        }
        return $enabled;
    }
}
