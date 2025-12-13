<?php
namespace App\Services;

use Core\Database;

class TagService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function normalizeInput(?string $input): array
    {
        $parts = array_filter(array_map('trim', explode(',', (string)$input)));
        $tags = [];
        foreach ($parts as $tag) {
            $name = mb_substr($tag, 0, 64);
            $slug = $this->slugify($name);
            if ($slug !== '') {
                $tags[$slug] = ['slug' => $slug, 'name' => $name];
            }
        }
        return array_values($tags);
    }

    public function sync(string $entityType, int $entityId, array $tags): void
    {
        $tagIds = [];
        foreach ($tags as $tag) {
            $slug = $tag['slug'];
            $name = $tag['name'];
            $existing = $this->db->fetch("SELECT id FROM tags WHERE slug = ?", [$slug]);
            if ($existing) {
                $tagIds[] = (int)$existing['id'];
            } else {
                $this->db->execute("INSERT INTO tags (slug, name, created_at) VALUES (:slug, :name, NOW())", [
                    ':slug' => $slug,
                    ':name' => $name,
                ]);
                $tagIds[] = (int)$this->db->pdo()->lastInsertId();
            }
        }
        // Remove stale
        $this->db->execute("DELETE FROM taggables WHERE entity_type = ? AND entity_id = ?", [$entityType, $entityId]);
        foreach ($tagIds as $tagId) {
            $this->db->execute("INSERT INTO taggables (tag_id, entity_type, entity_id) VALUES (?, ?, ?)", [$tagId, $entityType, $entityId]);
        }
    }

    public function forEntity(string $entityType, int $entityId): array
    {
        return $this->db->fetchAll("
            SELECT t.name, t.slug
            FROM taggables tg
            JOIN tags t ON t.id = tg.tag_id
            WHERE tg.entity_type = ? AND tg.entity_id = ?
        ", [$entityType, $entityId]);
    }

    public function autocomplete(string $term, int $limit = 10): array
    {
        $like = $term . '%';
        return $this->db->fetchAll("SELECT name, slug FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT ?", [$like, $limit]);
    }

    private function slugify(string $value): string
    {
        $orig = trim($value);
        $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $orig);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$slug), '-'));
        if ($slug === '' && $orig !== '') {
            // Фолбэк: оставляем буквы/цифры любых алфавитов
            $slug = mb_strtolower(trim(preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $orig), '-'));
        }
        return $slug;
    }
}
