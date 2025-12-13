<?php
namespace App\Services;

use Core\Database;

class SearchIndexService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function hasIndex(): bool
    {
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'search_index'");
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isEmpty(): bool
    {
        try {
            $row = $this->db->fetch("SELECT COUNT(*) as c FROM search_index");
            return (int)($row['c'] ?? 0) === 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function rebuildAll(): void
    {
        $this->db->execute("TRUNCATE TABLE search_index");
        $this->indexArticles();
        $this->indexGallery();
        $this->indexTags();
    }

    public function countByType(string $type): int
    {
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS c FROM search_index WHERE entity_type = ?", [$type]);
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function indexArticles(): void
    {
        $hasPreview = $this->hasColumn('articles', 'preview_en');
        $select = "id, slug, title_en, title_ru, body_en, body_ru";
        if ($hasPreview) {
            $select .= ", preview_en, preview_ru";
        }
        $rows = $this->db->fetchAll("SELECT {$select} FROM articles");
        foreach ($rows as $row) {
            $snippetEn = $row['preview_en'] ?? mb_substr((string)($row['body_en'] ?? ''), 0, 240);
            $snippetRu = $row['preview_ru'] ?? mb_substr((string)($row['body_ru'] ?? ''), 0, 240);
            $this->insertIndex('article', (int)$row['id'], [
                'slug' => $row['slug'] ?? '',
                'title_en' => $row['title_en'] ?? '',
                'title_ru' => $row['title_ru'] ?? '',
                'snippet_en' => $snippetEn,
                'snippet_ru' => $snippetRu,
                'url' => '/articles/' . ($row['slug'] ?? ''),
            ]);
        }
    }

    private function indexGallery(): void
    {
        $hasSlug = $this->hasColumn('gallery_items', 'slug');
        $select = "id, title_en, title_ru, description_en, description_ru, path_thumb, path_medium";
        if ($hasSlug) {
            $select = "id, slug, title_en, title_ru, description_en, description_ru, path_thumb, path_medium";
        }
        $rows = $this->db->fetchAll("SELECT {$select} FROM gallery_items");
        foreach ($rows as $row) {
            $this->insertIndex('gallery', (int)$row['id'], [
                'slug' => $hasSlug ? ($row['slug'] ?? '') : '',
                'title_en' => $row['title_en'] ?? '',
                'title_ru' => $row['title_ru'] ?? '',
                'snippet_en' => $row['description_en'] ?? '',
                'snippet_ru' => $row['description_ru'] ?? '',
                'url' => ($hasSlug && !empty($row['slug']))
                    ? '/gallery/photo/' . $row['slug']
                    : '/gallery/view?id=' . (int)$row['id'],
                'path_thumb' => $row['path_thumb'] ?? null,
                'path_medium' => $row['path_medium'] ?? null,
            ]);
        }
    }

    private function indexTags(): void
    {
        $rows = $this->db->fetchAll("SELECT id, name, slug FROM tags");
        foreach ($rows as $row) {
            $this->insertIndex('tag', (int)$row['id'], [
                'slug' => $row['slug'] ?? '',
                'title_en' => $row['name'] ?? '',
                'title_ru' => $row['name'] ?? '',
                'snippet_en' => '',
                'snippet_ru' => '',
                'url' => '/tags/' . ($row['slug'] ?? ''),
            ]);
        }
    }

    private function insertIndex(string $type, int $id, array $data): void
    {
        $hasThumb = $this->hasColumn('search_index', 'path_thumb');
        $cols = "entity_type, entity_id, slug, title_en, title_ru, snippet_en, snippet_ru, url, created_at, updated_at";
        $vals = ":type, :id, :slug, :te, :tr, :se, :sr, :url, NOW(), NOW()";
        $params = [
            ':type' => $type,
            ':id' => $id,
            ':slug' => $data['slug'] ?? '',
            ':te' => $data['title_en'] ?? '',
            ':tr' => $data['title_ru'] ?? '',
            ':se' => $data['snippet_en'] ?? '',
            ':sr' => $data['snippet_ru'] ?? '',
            ':url' => $data['url'] ?? '',
        ];
        if ($hasThumb) {
            $cols .= ", path_thumb, path_medium";
            $vals .= ", :pth, :pmd";
            $params[':pth'] = $data['path_thumb'] ?? null;
            $params[':pmd'] = $data['path_medium'] ?? null;
        }
        $sql = "
            INSERT INTO search_index ({$cols})
            VALUES ({$vals})
            ON DUPLICATE KEY UPDATE
                slug = VALUES(slug),
                title_en = VALUES(title_en),
                title_ru = VALUES(title_ru),
                snippet_en = VALUES(snippet_en),
                snippet_ru = VALUES(snippet_ru),
                url = VALUES(url),
                updated_at = NOW()";
        if ($hasThumb) {
            $sql .= ", path_thumb = VALUES(path_thumb), path_medium = VALUES(path_medium)";
        }
        $this->db->execute($sql, $params);
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
