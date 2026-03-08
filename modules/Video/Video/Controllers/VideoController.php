<?php
declare(strict_types=1);

namespace Modules\Video\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;

class VideoController
{
    private Container $container;
    private Database  $db;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db        = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        $page    = max(1, (int)($request->params['page'] ?? $request->query['page'] ?? 1));
        $perPage = 12;
        $categorySlug = (string)($request->params['category'] ?? '');
        $locale = $this->container->get('lang')->current();

        $categories = $this->db->fetchAll(
            "SELECT slug, name_en, name_ru, image_url
             FROM video_categories
             WHERE enabled = 1
             ORDER BY sort_order ASC, id ASC"
        );

        $activeCategory = null;
        if ($categorySlug !== '') {
            foreach ($categories as $cat) {
                if (($cat['slug'] ?? '') === $categorySlug) {
                    $activeCategory = $cat;
                    break;
                }
            }
            if (!$activeCategory) {
                return new Response('Not found', 404);
            }
        }

        $where = 'v.enabled = 1';
        $params = [];
        if ($categorySlug !== '') {
            $where .= ' AND c.slug = :category_slug';
            $params[':category_slug'] = $categorySlug;
        }

        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) AS cnt
             FROM video_items v
             LEFT JOIN video_categories c ON c.id = v.category_id
             WHERE {$where}",
            $params
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT v.id, v.slug, v.title_en, v.title_ru, v.description_en, v.description_ru,
                    v.video_type, v.video_id, v.thumbnail_url, v.duration, v.views, v.likes, v.created_at,
                    c.slug AS category_slug, c.image_url AS category_image_url
             FROM video_items v
             LEFT JOIN video_categories c ON c.id = v.category_id
             WHERE {$where}
             ORDER BY v.views DESC, v.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $listTitle = $locale === 'ru' ? 'Видео' : 'Videos';
        $listDesc  = $locale === 'ru' ? 'Категории и популярные видео.' : 'Categories and top videos.';
        if ($activeCategory) {
            $listTitle = $locale === 'ru'
                ? ((string)($activeCategory['name_ru'] ?: $activeCategory['name_en']))
                : ((string)($activeCategory['name_en'] ?: $activeCategory['name_ru']));
            $listDesc = $locale === 'ru' ? 'Топ видео в категории.' : 'Top videos in this category.';
        }
        $canonicalBase = $categorySlug !== ''
            ? '/videos/category/' . rawurlencode($categorySlug)
            : '/videos';
        $canonicalPath = $page > 1
            ? $canonicalBase . '/page/' . $page
            : $canonicalBase;
        $canonical = $this->absoluteUrl($request, $canonicalPath);

        $html = $this->container->get('renderer')->render('video/list', [
            '_layout'     => true,
            'title'       => $listTitle,
            'description' => $listDesc,
            'sectionTitle'=> $locale === 'ru' ? 'Топ видео' : 'Top videos',
            'items'       => $items,
            'categories'  => $categories,
            'activeCategorySlug' => $categorySlug,
            'page'        => $page,
            'total'       => $total,
            'perPage'     => $perPage,
            'locale'      => $locale,
            'breadcrumbs' => $categorySlug !== ''
                ? [
                    ['label' => $locale === 'ru' ? 'Видео' : 'Videos', 'url' => '/videos'],
                    ['label' => $listTitle],
                ]
                : [['label' => $listTitle]],
        ], [
            'title'       => $listTitle,
            'description' => $listDesc,
            'canonical'   => $canonical,
            'styles'      => [self::videoCssUrl()],
        ]);

        return new Response($html);
    }

    public function viewByCategory(Request $request): Response
    {
        $slug = (string)($request->params['slug'] ?? '');
        $category = (string)($request->params['category'] ?? '');
        $item = $this->findEnabledByCategoryAndSlug($category, $slug);
        if (!$item) {
            return new Response('Not found', 404);
        }

        return $this->renderItem($request, $item);
    }

    private function renderItem(Request $request, array $item): Response
    {
        $this->db->execute("UPDATE video_items SET views = views + 1 WHERE id = ?", [(int)$item['id']]);

        $locale    = $this->container->get('lang')->current();
        $titleKey  = $locale === 'ru' ? 'title_ru' : 'title_en';
        $descKey   = $locale === 'ru' ? 'description_ru' : 'description_en';
        $itemTitle = $item[$titleKey] ?: ($item['title_en'] ?: $item['title_ru']);
        $itemDesc  = $item[$descKey] ?: ($item['description_en'] ?: $item['description_ru']);
        $canonical = $this->absoluteUrl($request, self::publicPath($item));
        $relatedVideos = $this->relatedVideos((int)($item['id'] ?? 0), (int)($item['category_id'] ?? 0), 8);

        $html = $this->container->get('renderer')->render('video/item', [
            '_layout'     => true,
            'title'       => $itemTitle,
            'item'        => $item,
            'locale'      => $locale,
            'embedHtml'   => self::buildEmbed($item),
            'thumbUrl'    => self::resolveThumbnail($item),
            'relatedVideos' => $relatedVideos,
            'breadcrumbs' => [
                ['label' => $locale === 'ru' ? 'Видео' : 'Videos', 'url' => '/videos'],
                ['label' => $itemTitle],
            ],
        ], [
            'title'       => $itemTitle,
            'description' => mb_substr(strip_tags((string)$itemDesc), 0, 160),
            'canonical'   => $canonical,
            'image'       => self::resolveThumbnail($item),
            'styles'      => [self::videoCssUrl()],
        ]);

        return new Response($html);
    }

    private static function videoCssUrl(): string
    {
        static $url = null;
        if ($url === null) {
            $file = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/css/video.css';
            $v    = @filemtime($file) ?: '1';
            $url  = '/assets/css/video.css?v=' . $v;
        }
        return $url;
    }

    private function relatedVideos(int $currentId, int $categoryId, int $limit = 8): array
    {
        if ($currentId <= 0) {
            return [];
        }

        $sameCategory = $this->db->fetchAll(
            "SELECT v.id, v.slug, v.title_en, v.title_ru, v.video_type, v.video_id, v.thumbnail_url, v.views,
                    c.slug AS category_slug, c.image_url AS category_image_url
             FROM video_items v
             INNER JOIN video_categories c ON c.id = v.category_id
             WHERE v.enabled = 1 AND v.id != :id AND v.category_id = :category_id
             ORDER BY v.views DESC, v.created_at DESC
             LIMIT {$limit}",
            [
                ':id' => $currentId,
                ':category_id' => $categoryId,
            ]
        );

        if (count($sameCategory) >= $limit) {
            return $sameCategory;
        }

        $left = $limit - count($sameCategory);
        $fallback = $this->db->fetchAll(
            "SELECT v.id, v.slug, v.title_en, v.title_ru, v.video_type, v.video_id, v.thumbnail_url, v.views,
                    c.slug AS category_slug, c.image_url AS category_image_url
             FROM video_items v
             INNER JOIN video_categories c ON c.id = v.category_id
             WHERE v.enabled = 1 AND v.id != :id
             ORDER BY v.views DESC, v.created_at DESC
             LIMIT {$left}",
            [':id' => $currentId]
        );

        $seen = [];
        $result = [];
        foreach (array_merge($sameCategory, $fallback) as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $row;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    private function findEnabledByCategoryAndSlug(string $category, string $slug): ?array
    {
        $item = $this->db->fetch(
            "SELECT v.*, c.slug AS category_slug, c.image_url AS category_image_url
             FROM video_items v
             INNER JOIN video_categories c ON c.id = v.category_id
             WHERE c.slug = ? AND v.slug = ? AND v.enabled = 1",
            [$category, $slug]
        );

        return is_array($item) ? $item : null;
    }

    public static function publicPath(array $item): string
    {
        $slug = (string)($item['slug'] ?? '');
        $category = trim((string)($item['category_slug'] ?? ''));
        return '/videos/' . rawurlencode($category) . '/' . rawurlencode($slug);
    }

    public static function buildEmbed(array $item): string
    {
        $type = $item['video_type'] ?? 'youtube';
        $id   = htmlspecialchars($item['video_id'] ?? '');
        $url  = htmlspecialchars($item['video_url'] ?? '');

        return match ($type) {
            'youtube' => '<iframe class="video-embed__iframe" src="https://www.youtube.com/embed/' . $id . '?rel=0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" loading="lazy"></iframe>',
            'vimeo'   => '<iframe class="video-embed__iframe" src="https://player.vimeo.com/video/' . $id . '" allowfullscreen allow="autoplay; fullscreen; picture-in-picture" loading="lazy"></iframe>',
            'mp4'     => '<video class="video-embed__iframe" src="' . $url . '" controls preload="metadata" playsinline controlsList="nodownload"></video>',
            'embed'   => (string)($item['embed_code'] ?? ''),
            default   => '',
        };
    }

    public static function resolveThumbnail(array $item): ?string
    {
        if (!empty($item['thumbnail_url'])) {
            return $item['thumbnail_url'];
        }
        if (!empty($item['category_image_url'])) {
            return $item['category_image_url'];
        }
        if ($item['video_type'] === 'youtube' && !empty($item['video_id'])) {
            return 'https://img.youtube.com/vi/' . rawurlencode($item['video_id']) . '/hqdefault.jpg';
        }
        return null;
    }

    public static function parseVideoUrl(string $url): array
    {
        $url = trim($url);
        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return ['type' => 'youtube', 'video_id' => $m[1]];
        }
        // Vimeo
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            return ['type' => 'vimeo', 'video_id' => $m[1]];
        }
        // Self-hosted video files
        if (preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i', $url)) {
            return ['type' => 'mp4', 'video_id' => ''];
        }
        return ['type' => 'embed', 'video_id' => ''];
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        $cfg  = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $base   = $scheme . '://' . ($request->server['HTTP_HOST'] ?? 'localhost');
        }
        return $base . (str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/'));
    }
}
