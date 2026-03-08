<?php
declare(strict_types=1);

namespace Modules\Video\Search;

use Core\Database;
use Core\Search\SearchProviderInterface;
use Core\Search\SearchResult;

class VideoSearchProvider implements SearchProviderInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getKey(): string
    {
        return 'video';
    }

    public function getLabel(): string
    {
        return 'Видео';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function search(string $query, array $options = []): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT v.id, v.slug, v.title_en, v.title_ru, v.description_en, v.description_ru,
                        v.thumbnail_url, v.video_type, v.video_id, c.slug AS category_slug
                 FROM video_items v
                 INNER JOIN video_categories c ON c.id = v.category_id
                 WHERE v.enabled = 1 AND (v.title_en LIKE :q OR v.title_ru LIKE :q OR v.description_en LIKE :q OR v.description_ru LIKE :q)
                 ORDER BY v.created_at DESC
                 LIMIT 20",
                [':q' => '%' . $query . '%']
            );
        } catch (\Throwable $e) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $title   = $row['title_ru'] ?: ($row['title_en'] ?? 'Video');
            $desc    = $row['description_ru'] ?: ($row['description_en'] ?? '');
            $snippet = mb_substr(strip_tags((string)$desc), 0, 160);
            $thumb   = !empty($row['thumbnail_url'])
                ? $row['thumbnail_url']
                : ($row['video_type'] === 'youtube' && !empty($row['video_id'])
                    ? 'https://img.youtube.com/vi/' . rawurlencode($row['video_id']) . '/hqdefault.jpg'
                    : null);
            $results[] = new SearchResult(
                $title,
                $snippet,
                '/videos/' . rawurlencode((string)$row['category_slug']) . '/' . rawurlencode((string)$row['slug']),
                $thumb,
                'Видео'
            );
        }
        return $results;
    }
}
