<?php
namespace Modules\Articles\Search;

use Core\Database;
use Core\Search\SearchProviderInterface;
use Core\Search\SearchResult;

class ArticlesSearchProvider implements SearchProviderInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getKey(): string
    {
        return 'articles';
    }

    public function getLabel(): string
    {
        return 'Статьи';
    }

    public function getOptions(): array
    {
        return [];
    }

    public function search(string $query, array $options = []): array
    {
        $sql = "SELECT title_ru, title_en, body_ru, body_en, slug FROM articles WHERE title_ru LIKE :q OR title_en LIKE :q ORDER BY created_at DESC LIMIT 20";
        $rows = $this->db->fetchAll($sql, [':q' => '%' . $query . '%']);
        $results = [];
        foreach ($rows as $row) {
            $title = $row['title_ru'] ?: ($row['title_en'] ?? '');
            $body = $row['body_ru'] ?: ($row['body_en'] ?? '');
            $snippet = mb_substr(strip_tags((string)$body), 0, 160);
            $results[] = new SearchResult(
                $title,
                $snippet,
                '/articles/' . urlencode($row['slug']),
                null,
                'Статья'
            );
        }
        return $results;
    }
}
