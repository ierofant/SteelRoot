<?php
namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use App\Services\SettingsService;
use Modules\Gallery\Services\GalleryCategoryService;
use Modules\Users\Services\Auth;

class GalleryController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;
    private GalleryCategoryService $galleryCategories;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->settings = $container->get(SettingsService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->moduleSettings->loadDefaults('gallery', [
            'show_title' => true,
            'show_description' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'enable_lightbox' => true,
            'lightbox_likes' => true,
            'per_page' => 9,
            'seo_title_en' => '',
            'seo_title_ru' => '',
            'seo_desc_en' => '',
            'seo_desc_ru' => '',
        ]);
        $this->galleryCategories = new GalleryCategoryService($this->db);
        $this->uploadPath = APP_ROOT . '/storage/uploads/gallery';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->params['page'] ?? 1));
        $seoSettings = $this->moduleSettings->all('gallery');
        $perPage = max(1, (int)($seoSettings['per_page'] ?? 9));
        $tag = trim($request->query['tag'] ?? '');
        $category = trim($request->query['cat'] ?? '');
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';

        if ($tag !== '') {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image') JOIN tags t ON t.id = tg.tag_id WHERE t.slug = ?", [$tag]);
        } elseif ($category !== '' && $this->hasItemCategoriesTable()) {
            try {
                $totalRow = $this->db->fetch("
                    SELECT COUNT(DISTINCT gi.id) as cnt
                    FROM gallery_items gi
                    JOIN gallery_item_categories gic ON gic.item_id = gi.id
                    JOIN gallery_categories gc ON gc.id = gic.category_id
                    WHERE gc.slug = ?
                ", [$category]);
            } catch (\Throwable $e) {
                $totalRow = ['cnt' => 0];
            }
        } elseif ($category !== '' && $this->hasColumn('category_id')) {
            try {
                $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi JOIN gallery_categories gc ON gc.id = gi.category_id AND gc.slug = ?", [$category]);
            } catch (\Throwable $e) {
                $totalRow = ['cnt' => 0];
            }
        } elseif ($category !== '' && $this->hasColumn('category')) {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items WHERE category = ?", [$category]);
        } else {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items");
        }
        $total = (int)($totalRow['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $limitSql = (int)$perPage;
        $offsetSql = (int)$offset;
        $orderSql = $this->orderSql($sort, true);
        $items = $this->fetchItems($tag, $category, $orderSql, $limitSql, $offsetSql);

        $canonical = $this->canonical($request);
        $currentLocale = $this->container->get('lang')->current();
        $defaultTitle = $currentLocale === 'ru' ? 'Галерея' : 'Gallery';
        $defaultDesc = $currentLocale === 'ru'
            ? 'Подборка изображений и альбомов.'
            : 'Collection of images and albums.';
        $titlePrimary = trim((string)($currentLocale === 'ru' ? ($seoSettings['seo_title_ru'] ?? '') : ($seoSettings['seo_title_en'] ?? '')));
        $titleFallback = trim((string)($currentLocale === 'ru' ? ($seoSettings['seo_title_en'] ?? '') : ($seoSettings['seo_title_ru'] ?? '')));
        $descPrimary = trim((string)($currentLocale === 'ru' ? ($seoSettings['seo_desc_ru'] ?? '') : ($seoSettings['seo_desc_en'] ?? '')));
        $descFallback = trim((string)($currentLocale === 'ru' ? ($seoSettings['seo_desc_en'] ?? '') : ($seoSettings['seo_desc_ru'] ?? '')));
        $listTitle = $titlePrimary !== '' ? $titlePrimary : ($titleFallback !== '' ? $titleFallback : $defaultTitle);
        $listDesc = $descPrimary !== '' ? $descPrimary : ($descFallback !== '' ? $descFallback : $defaultDesc);
        $seoListTitle = $listTitle;
        if ($page > 1) {
            $seoListTitle .= $currentLocale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }

        // Cache check (только первая страница без фильтров)
        $cacheSettings  = $this->container->get(\App\Services\SettingsService::class);
        $cacheEnabled   = $cacheSettings->get('cache_gallery', '0') === '1';
        $cacheTtl       = max(1, (int)$cacheSettings->get('cache_gallery_ttl', '60')) * 60;
        $lang           = $this->container->get('lang')->current();
        $cacheKey       = 'gallery_list_p' . $page . '_' . $lang . '_' . $sort
                        . ($tag !== '' ? '_t' . md5($tag) : '')
                        . ($category !== '' ? '_c' . md5($category) : '');
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($cacheEnabled && ($cached = $cache->get($cacheKey))) {
            return new Response($cached);
        }

        $html = $this->container->get('renderer')->render(
            'gallery/list',
            [
                '_layout' => true,
                'title' => $listTitle,
                'description' => $listDesc,
                'items' => $items,
                'page' => $page,
                'total' => $total,
                'perPage' => $perPage,
                'locale' => $this->container->get('lang')->current(),
                'tag'               => $tag,
                'sort'              => $sort,
                'category'          => $category,
                'openMode'          => $mode,
                'display'           => $display,
                'enabledCategories' => $this->galleryCategories->enabled(),
                'popularTags'       => !empty($display['show_tags']) ? $this->popularTags() : [],
                'breadcrumbs'       => [
                    ['label' => $listTitle],
                ],
            ],
            [
                'title' => $seoListTitle,
                'description' => $listDesc,
                'canonical' => $canonical,
                'image' => $this->defaultOgImage(),
            ]
        );
        if ($cacheEnabled) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $id = (int)($request->query['id'] ?? 0);
        $slugParam = $request->params['slug'] ?? null;
        $authorJoin = '';
        $authorSelect = '';
        if ($this->hasColumn('author_id')) {
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar";
        }
        if ($slugParam) {
            $item = $this->db->fetch("
                SELECT gi.*{$authorSelect}
                FROM gallery_items gi
                {$authorJoin}
                WHERE gi.slug = ?
            ", [$slugParam]);
        } else {
            $item = $this->db->fetch("
                SELECT gi.*{$authorSelect}
                FROM gallery_items gi
                {$authorJoin}
                WHERE gi.id = ?
            ", [$id]);
        }
        if (!$item) {
            return new Response('Not found', 404);
        }
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';
        if (($mode === 'page') && empty($slugParam) && !empty($item['slug'] ?? '')) {
            return new Response('', 302, ['Location' => '/gallery/photo/' . urlencode($item['slug'])]);
        }
        $this->db->execute("UPDATE gallery_items SET views = views + 1 WHERE id = ?", [$item['id']]);
        $canonical = $this->canonical($request);
        $ogImage = $item['path_medium'] ?? $item['path'] ?? '';
        $tags = [];
        if (!empty($display['show_tags'])) {
            try {
                $tags = $this->db->fetchAll("
                    SELECT t.*
                    FROM tags t
                    JOIN taggables tg ON tg.tag_id = t.id
                    WHERE tg.entity_type IN ('gallery','gallery_item','image') AND tg.entity_id = ?
                ", [$item['id']]);
            } catch (\Throwable $e) {
                $tags = [];
            }
        }
        $itemTitle = $this->container->get('lang')->current() === 'ru' ? ($item['title_ru'] ?? 'Image') : ($item['title_en'] ?? 'Image');
        $itemDesc = $this->container->get('lang')->current() === 'ru' ? ($item['description_ru'] ?? '') : ($item['description_en'] ?? '');
        if ($itemDesc === '') {
            $altDesc = $this->container->get('lang')->current() === 'ru' ? ($item['description_en'] ?? '') : ($item['description_ru'] ?? '');
            $itemDesc = $altDesc;
        }
        if ($itemDesc === '') {
            $itemDesc = $listDesc ?? '';
        }
        if (!isset($listTitle)) {
            $listTitle = $this->container->get('lang')->current() === 'ru' ? 'Галерея' : 'Gallery';
        }
        $fallbackOg = $this->defaultOgImage();
        $ogToUse = $ogImage ?: $fallbackOg;
        $authorVisibility = $item['author_profile_visibility'] ?? 'public';
        $authorPrivate = $authorVisibility === 'private';
        $authorBypass = $this->canBypassPrivateProfile((int)($item['author_id'] ?? 0));
        $showSignature = !$authorPrivate || $authorBypass;
        $authorProfileUrl = $this->profileUrl($item['author_id'] ?? null, $item['author_username'] ?? null);
        $html = $this->container->get('renderer')->render(
            'gallery/item',
            [
                '_layout' => true,
                'title' => $itemTitle,
                'item' => $item,
                'locale' => $this->container->get('lang')->current(),
                'display' => $display,
                'tags' => $tags,
                'authorProfileUrl' => $authorProfileUrl,
                'authorSignature' => $showSignature ? ($item['author_signature'] ?? '') : '',
                'authorSignatureVisible' => $showSignature && !empty($item['author_signature']),
                'breadcrumbs' => [
                    ['label' => $listTitle, 'url' => '/gallery'],
                    ['label' => $itemTitle ?? 'Image'],
                ],
            ],
            [
                'title' => $itemTitle,
                'description' => $itemDesc,
                'canonical' => $canonical,
                'image' => $ogToUse,
            ]
        );
        return new Response($html);
    }

    public function byCategory(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $category = $this->galleryCategories->findBySlug($slug);
        if (!$category || !$category['enabled']) {
            return new Response('Not found', 404);
        }
        $page = max(1, (int)($request->params['page'] ?? 1));
        $perPage = max(1, (int)($this->moduleSettings->get('gallery', 'per_page') ?? 9));
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';

        $categoryIds = [(int)$category['id']];
        if ($this->hasCategoryParentColumn()) {
            $categoryIds = $this->categoryIdsWithChildren((int)$category['id']);
        }

        $totalRow = ['cnt' => $this->countByCategoryIds($categoryIds)];
        $total = (int)($totalRow['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $orderSql = $this->orderSql($sort, true);
        if (count($categoryIds) === 1) {
            $items = $this->fetchItems('', $slug, $orderSql, $perPage, $offset);
        } else {
            $items = $this->fetchItemsByCategoryIds($categoryIds, $orderSql, $perPage, $offset);
        }
        $locale = $this->container->get('lang')->current();
        $titleKey = $locale === 'ru' ? 'name_ru' : 'name_en';
        $catTitle = $category[$titleKey] ?: ($category['name_en'] ?: $category['name_ru']);
        $canonical = $this->canonical($request);
        $seoTitle = $locale === 'ru' ? ($catTitle . ' — галерея') : ($catTitle . ' — gallery');
        if ($page > 1) {
            $seoTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $seoDesc = $locale === 'ru'
            ? ('Фотографии в категории «' . $catTitle . '». Подборка работ и изображений.')
            : ('Photos in "' . $catTitle . '" category. A curated gallery of works.');
        $html = $this->container->get('renderer')->render(
            'gallery/list',
            [
                '_layout'           => true,
                'title'             => $catTitle,
                'description'       => $seoDesc,
                'items'             => $items,
                'page'              => $page,
                'total'             => $total,
                'perPage'           => $perPage,
                'locale'            => $locale,
                'tag'               => '',
                'sort'              => $sort,
                'category'          => $slug,
                'currentCategory'   => $category,
                'openMode'          => $mode,
                'display'           => $display,
                'enabledCategories' => $this->galleryCategories->enabled(),
                'popularTags'       => !empty($display['show_tags']) ? $this->popularTags() : [],
                'breadcrumbs'       => [
                    ['label' => $locale === 'ru' ? 'Галерея' : 'Gallery', 'url' => '/gallery'],
                    ['label' => $catTitle],
                ],
            ],
            [
                'title'       => $seoTitle,
                'description' => $seoDesc,
                'canonical'   => $canonical,
                'image'       => !empty($category['image_url']) ? $category['image_url'] : $this->defaultOgImage(),
            ]
        );
        return new Response($html);
    }

    public function byTag(Request $request): Response
    {
        $slug = $this->resolveTagSlug((string)($request->params['slug'] ?? ''));
        if ($slug === '') {
            return new Response('Not found', 404);
        }
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = max(1, (int)($this->moduleSettings->get('gallery', 'per_page') ?? 9));
        $offset = ($page - 1) * $perPage;
        $limitSql = (int)$perPage;
        $offsetSql = (int)$offset;
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $orderSql = $this->orderSql($sort, true);
        $items = $this->fetchItems($slug, '', $orderSql, $limitSql, $offsetSql);
        $canonical = $this->canonical($request);
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';
        $locale = $this->container->get('lang')->current();
        $tagTitle = $locale === 'ru' ? ('Тег галереи: ' . $slug) : ('Gallery tag: ' . $slug);
        if ($page > 1) {
            $tagTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $tagDesc = $locale === 'ru'
            ? ('Изображения и работы по тегу «' . $slug . '».')
            : ('Images and works for tag "' . $slug . '".');
        $html = $this->container->get('renderer')->render(
            'gallery/list',
            [
                '_layout' => true,
                'title' => $tagTitle,
                'items' => $items,
                'page' => $page,
                'total' => count($items),
                'perPage' => $perPage,
                'locale' => $locale,
                'tag' => $slug,
                'sort' => $sort,
                'openMode' => $mode,
                'display' => $display,
                'popularTags' => !empty($display['show_tags']) ? $this->popularTags() : [],
            ],
            [
                'title' => $tagTitle,
                'description' => $tagDesc,
                'canonical' => $canonical,
                'image' => $this->defaultOgImage(),
            ]
        );
        return new Response($html);
    }

    private function resolveTagSlug(string $rawSlug): string
    {
        $candidates = [];
        $decoded = rawurldecode($rawSlug);
        foreach ([$rawSlug, $decoded, mb_strtolower($decoded, 'UTF-8')] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && !in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $decoded);
        $translit = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$translit), '-'));
        if ($translit !== '' && !in_array($translit, $candidates, true)) {
            $candidates[] = $translit;
        }

        foreach ($candidates as $candidate) {
            $tag = $this->db->fetch("SELECT slug FROM tags WHERE slug = ? LIMIT 1", [$candidate]);
            if ($tag && !empty($tag['slug'])) {
                return (string)$tag['slug'];
            }
        }

        return '';
    }

    private function sanitizeSort(string $sort): string
    {
        $allowed = ['new', 'likes', 'views'];
        return in_array($sort, $allowed, true) ? $sort : 'new';
    }

    private function fetchItems(string $tag, string $category, string $orderSql, int $limitSql, int $offsetSql): array
    {
        $authorSelect = '';
        $authorJoin = '';
        if ($this->hasColumn('author_id')) {
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, gi.author_id";
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
        }
        try {
            if ($tag !== '') {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
                    JOIN tags t ON t.id = tg.tag_id
                    {$authorJoin}
                    WHERE t.slug = :slug
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':slug' => $tag]);
            }
            if ($category !== '' && $this->hasItemCategoriesTable()) {
                return $this->db->fetchAll("
                    SELECT DISTINCT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN gallery_item_categories gic ON gic.item_id = gi.id
                    JOIN gallery_categories gc ON gc.id = gic.category_id AND gc.slug = :cat
                    {$authorJoin}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            if ($category !== '' && $this->hasColumn('category_id')) {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN gallery_categories gc ON gc.id = gi.category_id AND gc.slug = :cat
                    {$authorJoin}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            if ($category !== '' && $this->hasColumn('category')) {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    {$authorJoin}
                    WHERE gi.category = :cat
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            return $this->db->fetchAll("SELECT gi.*{$authorSelect} FROM gallery_items gi {$authorJoin} ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}");
        } catch (\Throwable $e) {
            // Fallback to id-based ordering if custom columns missing
            $fallbackOrder = "id DESC";
            if ($tag !== '') {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
                    JOIN tags t ON t.id = tg.tag_id
                    {$authorJoin}
                    WHERE t.slug = :slug
                    ORDER BY {$fallbackOrder} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':slug' => $tag]);
            }
            return $this->db->fetchAll("SELECT gi.*{$authorSelect} FROM gallery_items gi {$authorJoin} ORDER BY {$fallbackOrder} LIMIT {$limitSql} OFFSET {$offsetSql}");
        }
    }

    private function fetchItemsByCategoryIds(array $categoryIds, string $orderSql, int $limitSql, int $offsetSql): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $authorSelect = '';
        $authorJoin = '';
        if ($this->hasColumn('author_id')) {
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, gi.author_id";
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
        }

        $in = implode(',', array_fill(0, count($categoryIds), '?'));
        if ($this->hasItemCategoriesTable()) {
            return $this->db->fetchAll(
                "SELECT DISTINCT gi.*{$authorSelect}
                 FROM gallery_items gi
                 JOIN gallery_item_categories gic ON gic.item_id = gi.id
                 {$authorJoin}
                 WHERE gic.category_id IN ({$in})
                 ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}",
                $categoryIds
            );
        }
        return $this->db->fetchAll(
            "SELECT gi.*{$authorSelect}
             FROM gallery_items gi
             {$authorJoin}
             WHERE gi.category_id IN ({$in})
             ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}",
            $categoryIds
        );
    }

    private function countByCategoryIds(array $categoryIds): int
    {
        if ($categoryIds === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($categoryIds), '?'));
        try {
            if ($this->hasItemCategoriesTable()) {
                $row = $this->db->fetch(
                    "SELECT COUNT(DISTINCT gi.id) AS cnt
                     FROM gallery_items gi
                     JOIN gallery_item_categories gic ON gic.item_id = gi.id
                     WHERE gic.category_id IN ({$in})",
                    $categoryIds
                );
                return (int)($row['cnt'] ?? 0);
            }
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS cnt FROM gallery_items WHERE category_id IN ({$in})",
                $categoryIds
            );
            return (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function orderSql(string $sort, bool $withAlias = false): string
    {
        $hasLikes = $this->hasColumn('likes');
        $hasViews = $this->hasColumn('views');
        $hasCreated = $this->hasColumn('created_at');
        $prefix = $withAlias ? 'gi.' : '';
        if ($sort === 'likes' && $hasLikes) {
            return "{$prefix}likes DESC, {$prefix}id DESC";
        }
        if ($sort === 'views' && $hasViews) {
            return "{$prefix}views DESC, {$prefix}id DESC";
        }
        if ($hasCreated) {
            return "{$prefix}created_at DESC, {$prefix}id DESC";
        }
        return "{$prefix}id DESC";
    }

    private function hasColumn(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            try {
                $row = $this->db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$name]);
                $cache[$name] = $row ? true : false;
            } catch (\Throwable $e) {
                $cache[$name] = false;
            }
        }
        return $cache[$name];
    }

    private function hasItemCategoriesTable(): bool
    {
        static $hasTable = null;
        if ($hasTable === null) {
            try {
                $row = $this->db->fetch("SHOW TABLES LIKE 'gallery_item_categories'");
                $hasTable = $row ? true : false;
            } catch (\Throwable $e) {
                $hasTable = false;
            }
        }
        return $hasTable;
    }

    private function hasCategoryParentColumn(): bool
    {
        static $hasParent = null;
        if ($hasParent === null) {
            try {
                $row = $this->db->fetch("SHOW COLUMNS FROM gallery_categories LIKE 'parent_id'");
                $hasParent = $row ? true : false;
            } catch (\Throwable $e) {
                $hasParent = false;
            }
        }
        return $hasParent;
    }

    private function categoryIdsWithChildren(int $rootId): array
    {
        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue !== []) {
            $current = array_shift($queue);
            $children = $this->db->fetchAll(
                "SELECT id FROM gallery_categories WHERE parent_id = ? AND enabled = 1",
                [$current]
            );
            foreach ($children as $child) {
                $cid = (int)($child['id'] ?? 0);
                if ($cid > 0 && !in_array($cid, $ids, true)) {
                    $ids[] = $cid;
                    $queue[] = $cid;
                }
            }
        }

        return $ids;
    }

    private function displaySettings(): array
    {
        $defaults = [
            'show_title' => true,
            'show_description' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'enable_lightbox' => true,
            'lightbox_likes' => true,
        ];
        $stored = $this->moduleSettings->all('gallery');
        $merged = array_merge($defaults, $stored);
        foreach ($merged as $key => $val) {
            $merged[$key] = (bool)$val;
        }
        return $merged;
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
        $uri = $request->path ?? '/';
        $qs = $request->query;
        unset($qs['page']);
        $query = http_build_query($qs);
        return $base . $uri . ($query ? '?' . $query : '');
    }

    private function defaultOgImage(): ?string
    {
        $settings = $this->container->get(\App\Services\SettingsService::class);
        $img = $settings->get('og_image', '');
        if ($img) {
            return $img;
        }
        $logo = $settings->get('theme_logo', '');
        return $logo ?: null;
    }

    private function canBypassPrivateProfile(int $ownerId): bool
    {
        if ($ownerId <= 0) {
            return false;
        }
        try {
            /** @var Auth $auth */
            $auth = $this->container->get(Auth::class);
        } catch (\Throwable $e) {
            return false;
        }
        $viewer = $auth->user();
        if (!$viewer) {
            return false;
        }
        return $auth->checkRole('admin') || (int)($viewer['id'] ?? 0) === $ownerId;
    }

    private function profileUrl($id, $username): string
    {
        $username = trim((string)$username);
        if ($username !== '') {
            return '/users/' . rawurlencode($username);
        }
        return '/users/' . (int)$id;
    }

    private function popularTags(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT t.name, t.slug, COUNT(*) AS uses
                 FROM tags t
                 JOIN taggables tg ON tg.tag_id = t.id
                 WHERE tg.entity_type IN ('gallery','gallery_item','image')
                 GROUP BY t.id, t.name, t.slug
                 ORDER BY t.name ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
}
