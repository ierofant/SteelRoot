<?php
namespace Modules\Articles\Services;

use Core\Database;

class ArticleCategoryService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM article_categories ORDER BY position ASC, id ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function enabled(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM article_categories WHERE enabled = 1 ORDER BY position ASC, id ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(int $id): ?array
    {
        $row = $this->db->fetch("SELECT * FROM article_categories WHERE id = ?", [$id]);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->fetch("SELECT * FROM article_categories WHERE slug = ?", [$slug]);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $slug = $this->generateSlug($data['slug'] ?? $data['name_en'] ?? '');
        $this->db->execute(
            "INSERT INTO article_categories (slug, name_en, name_ru, image_url, position, enabled)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $slug,
                $data['name_en'] ?? '',
                $data['name_ru'] ?? '',
                $data['image_url'] ?? '',
                (int)($data['position'] ?? 0),
                isset($data['enabled']) ? (int)(bool)$data['enabled'] : 1,
            ]
        );
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
            "UPDATE article_categories
             SET slug = ?, name_en = ?, name_ru = ?, image_url = ?, position = ?, enabled = ?
             WHERE id = ?",
            [
                $slug,
                $data['name_en'] ?? $existing['name_en'],
                $data['name_ru'] ?? $existing['name_ru'],
                $data['image_url'] ?? $existing['image_url'],
                (int)($data['position'] ?? $existing['position']),
                isset($data['enabled']) ? (int)(bool)$data['enabled'] : (int)$existing['enabled'],
                $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        // FK is ON DELETE SET NULL so articles are unaffected
        $this->db->execute("DELETE FROM article_categories WHERE id = ?", [$id]);
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
            $sql = "SELECT id FROM article_categories WHERE slug = ?";
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
}
