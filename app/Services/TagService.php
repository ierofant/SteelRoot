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

    public function normalizeInput(?string $input, int $limit = 0): array
    {
        $parts = $this->splitInput((string)$input);
        $tags = [];
        foreach ($parts as $tag) {
            $name = $this->normalizeName($tag);
            $slug = SlugService::slugify($name, '');
            if ($slug !== '') {
                $tags[$slug] = ['slug' => $slug, 'name' => $name];
                if ($limit > 0 && count($tags) >= $limit) {
                    break;
                }
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
            $existing = $this->db->fetch("SELECT id, slug FROM tags WHERE name = ? ORDER BY id ASC LIMIT 1", [$name]);
            if (!$existing) {
                $existing = $this->db->fetch("SELECT id, slug FROM tags WHERE slug = ? ORDER BY id ASC LIMIT 1", [$slug]);
            }
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
        $limit = max(1, min(100, $limit));
        $like = $term . '%';
        return $this->db->fetchAll("SELECT name, slug FROM tags WHERE name LIKE ? ORDER BY name ASC LIMIT {$limit}", [$like]);
    }

    public function popular(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll("
            SELECT t.name, t.slug, COUNT(*) AS usage_count
            FROM tags t
            JOIN taggables tg ON tg.tag_id = t.id
            GROUP BY t.id, t.name, t.slug
            ORDER BY usage_count DESC, t.name ASC
            LIMIT {$limit}
        ");
    }

    public function formatInput(array $tags): string
    {
        $parts = [];
        foreach ($tags as $tag) {
            $name = '';
            if (is_array($tag)) {
                $name = (string)($tag['name'] ?? $tag['slug'] ?? '');
            } elseif (is_string($tag)) {
                $name = $tag;
            }
            $name = $this->normalizeName($name);
            if ($name !== '') {
                $parts[] = '#' . $name;
            }
        }

        return implode(' ', $parts);
    }

    private function splitInput(string $input): array
    {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $chunks = preg_split('/[\r\n,;]+/u', $input) ?: [];
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (str_contains($chunk, '#')) {
                if (preg_match_all('/#([\p{L}\p{Nd}_-]+)/u', $chunk, $matches)) {
                    foreach ($matches[1] as $match) {
                        $parts[] = $match;
                    }
                    $cleaned = trim((string)preg_replace('/#[\p{L}\p{Nd}_-]+/u', '', $chunk));
                    if ($cleaned !== '') {
                        $parts[] = $cleaned;
                    }
                    continue;
                }
            }

            $parts[] = $chunk;
        }

        return $parts;
    }

    private function normalizeName(string $value): string
    {
        $value = trim($value);
        $value = ltrim($value, "# \t\n\r\0\x0B");
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, 64);
    }
}
