<?php
namespace Modules\Gallery\Search;

use Core\Database;
use Core\Search\SearchProviderInterface;
use Core\Search\SearchResult;

class GallerySearchProvider implements SearchProviderInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getKey(): string
    {
        return 'gallery';
    }

    public function getLabel(): string
    {
        return 'Галерея';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function search(string $query, array $options = []): array
    {
        $sql = "SELECT title_ru, title_en, description_ru, description_en, slug, id, path_thumb FROM gallery_items WHERE title_ru LIKE :q OR title_en LIKE :q ORDER BY created_at DESC LIMIT 20";
        $rows = $this->db->fetchAll($sql, [':q' => '%' . $query . '%']);
        $results = [];
        foreach ($rows as $row) {
            $title = $row['title_ru'] ?: ($row['title_en'] ?? 'Image');
            $desc = $row['description_ru'] ?: ($row['description_en'] ?? '');
            $snippet = mb_substr(strip_tags((string)$desc), 0, 160);
            $url = !empty($row['slug']) ? '/gallery/photo/' . urlencode($row['slug']) : '/gallery/view?id=' . (int)$row['id'];
            $results[] = new SearchResult(
                $title,
                $snippet,
                $url,
                $row['path_thumb'] ?? null,
                'Галерея'
            );
        }
        return $results;
    }
}
