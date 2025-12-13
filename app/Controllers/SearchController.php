<?php
namespace App\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\RateLimiter;
use App\Services\SearchIndexService;

class SearchController
{
    private Container $container;
    private Database $db;
    private SearchIndexService $indexService;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->indexService = $container->get(SearchIndexService::class);
    }

    public function index(Request $request): Response
    {
        $q = trim($request->query['q'] ?? '');
        $cfgAll = $this->container->get('config')['limits'] ?? [];
        $settings = $this->container->get(\App\Services\SettingsService::class);
        $max = (int)($settings->get('rl_search', $cfgAll['rl_search']['max'] ?? 30));
        $win = (int)($cfgAll['rl_search']['window'] ?? 60);
        $limiter = new RateLimiter('search', $max, $win, true);
        if ($limiter->tooManyAttempts()) {
            return new Response('Too many requests', 429);
        }
        $selectedSources = $request->query['sources'] ?? [];
        $selectedSources = is_array($selectedSources) ? array_filter(array_map('trim', $selectedSources)) : [];
        $allowedSources = ['articles', 'gallery', 'tags'];
        $selectedSources = array_values(array_intersect($allowedSources, $selectedSources));

        $results = ['articles' => [], 'gallery' => [], 'tags' => []];
        $useSettings = empty($selectedSources);
        $includeArticles = $useSettings ? (($settings->get('search_include_articles', '1') === '1')) : in_array('articles', $selectedSources, true);
        $includeGallery = $useSettings ? (($settings->get('search_include_gallery', '1') === '1')) : in_array('gallery', $selectedSources, true);
        $includeTags = $useSettings ? (($settings->get('search_include_tags', '0') === '1')) : in_array('tags', $selectedSources, true);

        $cacheTtl = max(0, (int)$settings->get('search_cache_ttl', 10));
        $cacheKey = 'search_' . md5($q . '_' . ($includeArticles ? '1' : '0') . '_' . ($includeGallery ? '1' : '0') . '_' . ($includeTags ? '1' : '0'));
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($q !== '') {
            $limiter->hit();
            if ($cacheTtl > 0 && ($cached = $cache->get($cacheKey))) {
                $results = $cached;
            } else {
                $like = '%' . $q . '%';
                $max = (int)$settings->get('search_max_results', 20);
                if ($this->indexService->hasIndex()) {
                    if ($this->indexService->isEmpty()
                        || ($includeArticles && $this->indexService->countByType('article') === 0)
                        || ($includeGallery && $this->indexService->countByType('gallery') === 0)
                    ) {
                        $this->indexService->rebuildAll();
                    }
                    $results = $this->searchIndexed($q, $like, $settings, $max, $includeArticles, $includeGallery, $includeTags);
                } else {
                    if ($includeArticles) {
                        $articles = $this->db->fetchAll("
                            SELECT slug, title_en, title_ru FROM articles
                            WHERE MATCH(title_en, title_ru, body_en, body_ru) AGAINST(:q IN BOOLEAN MODE)
                               OR title_en LIKE :like OR title_ru LIKE :like
                            LIMIT {$max}
                        ", [':q' => $q . '*', ':like' => $like]);
                        $results['articles'] = $articles;
                    }
                    if ($includeGallery) {
                        $cols = "id, title_en, title_ru, path_thumb, path_medium";
                        if ($this->hasGalleryColumn('slug')) {
                            $cols .= ", slug";
                        }
                        $gallery = $this->db->fetchAll("
                            SELECT {$cols} FROM gallery_items
                            WHERE MATCH(title_en, title_ru, description_en, description_ru) AGAINST(:q IN BOOLEAN MODE)
                               OR title_en LIKE :like OR title_ru LIKE :like
                            LIMIT {$max}
                        ", [':q' => $q . '*', ':like' => $like]);
                        $results['gallery'] = $gallery;
                    }
                    if ($includeTags) {
                        $tags = $this->db->fetchAll("
                            SELECT name, slug FROM tags
                            WHERE name LIKE :like OR slug LIKE :like
                            LIMIT {$max}
                        ", [':like' => $like]);
                        $results['tags'] = $tags;
                    }
                }
                if ($cacheTtl > 0) {
                    $cache->set($cacheKey, $results, $cacheTtl * 60);
                }
            }
        }
        $html = $this->container->get('renderer')->render('search', [
            'title' => 'Search',
            'query' => $q,
            'results' => $results,
            'galleryMode' => $settings->get('gallery_open_mode', 'lightbox'),
            'selectedSources' => $selectedSources ?: array_keys(array_filter([
                'articles' => $includeArticles,
                'gallery' => $includeGallery,
                'tags' => $includeTags,
            ])),
            'meta' => ['title' => 'Search', 'canonical' => $this->canonical($request)],
        ]);
        return new Response($html);
    }

    public function autocomplete(Request $request): Response
    {
        $term = trim($request->query['term'] ?? '');
        if ($term === '') {
            return Response::json([]);
        }
        $cfgAll = $this->container->get('config')['limits'] ?? [];
        $settings = $this->container->get(\App\Services\SettingsService::class);
        $max = (int)($settings->get('rl_autocomplete', $cfgAll['rl_autocomplete']['max'] ?? 60));
        $win = (int)($cfgAll['rl_autocomplete']['window'] ?? 60);
        $limiter = new RateLimiter('autocomplete', $max, $win, true);
        if ($limiter->tooManyAttempts()) {
            return Response::json(['error' => 'Too many requests'], 429);
        }
        $limiter->hit();
        $like = $term . '%';
        $rows = $this->db->fetchAll("SELECT name, slug FROM tags WHERE name LIKE ? OR slug LIKE ? ORDER BY name ASC LIMIT 10", [$like, $like]);
        return Response::json($rows);
    }

    private function canonical(Request $request): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . '/search';
    }

    private function hasGalleryColumn(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
    }

    private function searchIndexed(string $q, string $like, $settings, int $max, bool $includeArticles, bool $includeGallery, bool $includeTags): array
    {
        $select = ['entity_type', 'entity_id', 'title_en', 'title_ru', 'snippet_en', 'snippet_ru', 'url'];
        if ($this->hasColumnSafe('search_index', 'slug')) {
            $select[] = 'slug';
        }
        if ($this->hasColumnSafe('search_index', 'path_thumb')) {
            $select[] = 'path_thumb';
            $select[] = 'path_medium';
        }
        $sqlBase = "
            SELECT %s
            FROM search_index
            WHERE MATCH(title_en, title_ru, snippet_en, snippet_ru) AGAINST(:q IN BOOLEAN MODE)
               OR title_en LIKE :like OR title_ru LIKE :like
            LIMIT {$max}
        ";
        $sql = sprintf($sqlBase, implode(', ', $select));
        $fallbackSql = sprintf($sqlBase, 'entity_type, entity_id, title_en, title_ru, snippet_en, snippet_ru, url');
        try {
            $rows = $this->db->fetchAll($sql, [':q' => $q . '*', ':like' => $like]);
        } catch (\Throwable $e) {
            // Column mismatch — try minimal select without optional columns.
            try {
                $rows = $this->db->fetchAll($fallbackSql, [':q' => $q . '*', ':like' => $like]);
            } catch (\Throwable $e2) {
                $rows = [];
            }
        }
        $locale = $this->container->get('lang')->current();
        $results = ['articles' => [], 'gallery' => [], 'tags' => []];
        foreach ($rows as $row) {
            $title = $locale === 'ru' ? ($row['title_ru'] ?? $row['title_en']) : ($row['title_en'] ?? $row['title_ru']);
            $snippet = $locale === 'ru' ? ($row['snippet_ru'] ?? $row['snippet_en']) : ($row['snippet_en'] ?? $row['snippet_ru']);
            if ($row['entity_type'] === 'article' && $includeArticles) {
                $results['articles'][] = [
                    'slug' => $row['slug'] ?? '',
                    'title_en' => $row['title_en'],
                    'title_ru' => $row['title_ru'],
                    'url' => $row['url'],
                    'preview_en' => $row['snippet_en'],
                    'preview_ru' => $row['snippet_ru'],
                ];
            } elseif ($row['entity_type'] === 'gallery' && $includeGallery) {
                $results['gallery'][] = [
                    'id' => $row['entity_id'],
                    'slug' => $row['slug'] ?? null,
                    'title_en' => $row['title_en'],
                    'title_ru' => $row['title_ru'],
                    'path_thumb' => $row['path_thumb'] ?? '',
                    'path_medium' => $row['path_medium'] ?? '',
                    'url' => $row['url'],
                ];
            } elseif ($row['entity_type'] === 'tag' && $includeTags) {
                $results['tags'][] = [
                    'name' => $title,
                    'slug' => $row['slug'],
                    'url' => $row['url'],
                ];
            }
        }
        // Fallback to DB if gallery requested but index returned none (e.g., index not populated).
        if ($settings->get('search_include_gallery', '1') === '1' && empty($results['gallery'])) {
            $cols = "id, title_en, title_ru, path_thumb, path_medium";
            if ($this->hasGalleryColumn('slug')) {
                $cols .= ", slug";
            }
            $maxDb = $max;
            try {
                $gallery = $this->db->fetchAll("
                    SELECT {$cols} FROM gallery_items
                    WHERE MATCH(title_en, title_ru, description_en, description_ru) AGAINST(:q IN BOOLEAN MODE)
                       OR title_en LIKE :like OR title_ru LIKE :like
                    LIMIT {$maxDb}
                ", [':q' => $q . '*', ':like' => $like]);
            } catch (\Throwable $e) {
                $gallery = [];
            }
            $results['gallery'] = $gallery;
        }
        return $results;
    }

    private function hasColumnSafe(string $table, string $name): bool
    {
        try {
            $row = $this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", [$name]);
            return (bool)$row;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
