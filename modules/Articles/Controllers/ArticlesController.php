<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Core\Meta\JsonLdRenderer;
use Core\Meta\CommonSchemas;
use Modules\Articles\Providers\ArticleSchemaProvider;
use Modules\Users\Services\Auth;

class ArticlesController
{
    private Container $container;
    private Database $db;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->moduleSettings->loadDefaults('articles', [
            'show_author'         => true,
            'show_date'           => true,
            'show_likes'          => true,
            'show_views'          => true,
            'show_tags'           => true,
            'description_enabled' => true,
            'grid_cols'           => 3,
            'per_page'            => 6,
        ]);
    }

    public function byCategory(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $category = $this->db->fetch(
            "SELECT * FROM article_categories WHERE slug = ? AND enabled = 1",
            [$slug]
        );
        if (!$category) {
            return new Response('Not found', 404);
        }
        $catSettings = $this->moduleSettings->all('articles');
        $perPage  = max(1, (int)($catSettings['per_page'] ?? 6));
        $gridCols = max(1, min(6, (int)($catSettings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $typeFilter = $this->hasColumn('type') ? "AND (a.type = 'article' OR a.type IS NULL OR a.type = '')" : '';
        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) as cnt FROM articles a WHERE a.category_id = ? {$typeFilter}",
            [(int)$category['id']]
        );
        $total  = (int)($totalRow['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $locale = $this->container->get('lang')->current();
        $select = "a.slug, a.title_en, a.title_ru, a.created_at";
        $join = '';
        if ($this->hasColumn('author_id')) {
            $select .= ", a.author_id, u.name as author_name, u.avatar as author_avatar";
            $join = "LEFT JOIN users u ON u.id = a.author_id";
        }
        if ($this->hasColumn('views')) {
            $select .= ", a.views";
        }
        if ($this->hasColumn('likes')) {
            $select .= ", a.likes";
        }
        if ($this->hasColumn('preview_en')) {
            $select .= ", a.preview_en, a.preview_ru";
        }
        if ($this->hasColumn('image_url')) {
            $select .= ", a.image_url";
        }
        $typeFilter = $this->hasColumn('type') ? "AND (a.type = 'article' OR a.type IS NULL OR a.type = '')" : '';
        $articles = $this->db->fetchAll(
            "SELECT {$select} FROM articles a {$join}
             WHERE a.category_id = ? {$typeFilter}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [(int)$category['id']]
        );
        $display = array_merge([
            'show_author' => true, 'show_date' => true,
            'show_likes' => true, 'show_views' => true, 'show_tags' => true,
        ], $catSettings);
        $titleKey = $locale === 'ru' ? 'name_ru' : 'name_en';
        $catTitle = $category[$titleKey] ?: ($category['name_en'] ?: $category['name_ru']);
        $canonical = $this->canonical($request);
        $seoTitle = $locale === 'ru' ? ($catTitle . ' — статьи') : ($catTitle . ' — articles');
        if ($page > 1) {
            $seoTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $seoDesc = $locale === 'ru'
            ? ('Статьи в категории «' . $catTitle . '». Подборка публикаций, обзоров и материалов.')
            : ('Articles in "' . $catTitle . '" category. A curated list of publications and guides.');
        $html = $this->container->get('renderer')->render(
            'articles/list',
            [
                '_layout'     => true,
                'title'       => $catTitle,
                'description' => $seoDesc,
                'articles'    => $articles,
                'page'        => $page,
                'total'       => $total,
                'perPage'     => $perPage,
                'gridCols'    => $gridCols,
                'locale'      => $locale,
                'display'     => $display,
                'category'    => $category,
                'breadcrumbs' => [
                    ['label' => $locale === 'ru' ? 'Статьи' : 'Articles', 'url' => '/articles'],
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
        $rawSlug = (string)($request->params['slug'] ?? '');
        $tag = $this->resolveTagBySlug($rawSlug);
        if (!$tag) {
            return new Response('Not found', 404);
        }
        $slug = (string)$tag['slug'];

        $perPage = 12;
        $aPage   = max(1, (int)($request->query['ap'] ?? 1));
        $gPage   = max(1, (int)($request->query['gp'] ?? 1));

        $aTotal = (int)($this->db->fetch("
            SELECT COUNT(*) AS cnt FROM articles a
            JOIN taggables tg ON tg.entity_id = a.id AND tg.entity_type = 'article'
            JOIN tags t ON t.id = tg.tag_id WHERE t.slug = ?
        ", [$slug])['cnt'] ?? 0);

        $gTotal = (int)($this->db->fetch("
            SELECT COUNT(*) AS cnt FROM gallery_items gi
            JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
            JOIN tags t ON t.id = tg.tag_id WHERE t.slug = ?
        ", [$slug])['cnt'] ?? 0);

        $aOffset = ($aPage - 1) * $perPage;
        $gOffset = ($gPage - 1) * $perPage;

        $articles = $this->db->fetchAll("
            SELECT a.slug, a.title_en, a.title_ru, a.created_at
            FROM articles a
            JOIN taggables tg ON tg.entity_id = a.id AND tg.entity_type = 'article'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
            ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$aOffset}
        ", [$slug]);
        $gallery = $this->db->fetchAll("
            SELECT gi.* FROM gallery_items gi
            JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
            ORDER BY gi.id DESC LIMIT {$perPage} OFFSET {$gOffset}
        ", [$slug]);
        $canonical = $this->canonical($request);
        $tagName = $tag['name'] ?? $slug;
        $currentLocale = $this->container->get('lang')->current();
        $titleText = $currentLocale === 'ru' ? ('Тег: ' . $tagName) : ('Tag: ' . $tagName);
        $tagDesc = $currentLocale === 'ru'
            ? ('Материалы по тегу «' . $tagName . '»: статьи и изображения.')
            : ('Content for tag "' . $tagName . '": articles and images.');
        $tagBase   = '/tags/' . rawurlencode($slug);
        $html = $this->container->get('renderer')->render(
            'tags/show',
            [
                '_layout'   => true,
                'title'     => $titleText,
                'articles'  => $articles,
                'gallery'   => $gallery,
                'locale'    => $this->container->get('lang')->current(),
                'slug'      => $slug,
                'tagName'   => $tag['name'] ?? $slug,
                'openMode'  => $this->container->get(\App\Services\SettingsService::class)->get('gallery_open_mode', 'lightbox'),
                'aPage'     => $aPage,
                'aTotal'    => $aTotal,
                'gPage'     => $gPage,
                'gTotal'    => $gTotal,
                'perPage'   => $perPage,
                'tagBase'   => $tagBase,
            ],
            [
                'title' => $titleText,
                'canonical' => $canonical,
                'description' => $tagDesc,
                'image' => $this->defaultOgImage(),
            ]
        );
        return new Response($html);
    }

    private function resolveTagBySlug(string $rawSlug): ?array
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
            $tag = $this->db->fetch("SELECT id, name, slug FROM tags WHERE slug = ? LIMIT 1", [$candidate]);
            if ($tag) {
                return $tag;
            }
        }

        return null;
    }

    public function index(Request $request): Response
    {
        $settings = $this->moduleSettings->all('articles');
        $perPage  = max(1, (int)($settings['per_page'] ?? 6));
        $gridCols = max(1, min(6, (int)($settings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $select = "a.slug, a.title_en, a.title_ru, a.created_at";
        $join = '';
        if ($this->hasColumn('author_id')) {
            $select .= ", a.author_id, u.name as author_name, u.avatar as author_avatar";
            $join = "LEFT JOIN users u ON u.id = a.author_id";
        }
        if ($this->hasColumn('views')) {
            $select .= ", views";
        }
        if ($this->hasColumn('likes')) {
            $select .= ", likes";
        }
        $hasPreview = $this->hasColumn('preview_en');
        $hasImg = $this->hasColumn('image_url');
        $hasCatId = $this->hasColumn('category_id');
        if ($hasPreview) {
            $select .= ", preview_en, preview_ru";
        }
        if ($hasImg) {
            $select .= ", a.image_url";
        }
        if ($hasCatId) {
            $select .= ", a.category_id, ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru";
            $join .= " LEFT JOIN article_categories ac ON ac.id = a.category_id";
        }
        $typeWhere = $this->hasColumn('type') ? "WHERE (a.type = 'article' OR a.type IS NULL OR a.type = '')" : '';
        $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM articles a {$typeWhere}");
        $total    = (int)($totalRow['cnt'] ?? 0);
        $offset   = ($page - 1) * $perPage;
        $articles = $this->db->fetchAll(
            "SELECT {$select} FROM articles a {$join} {$typeWhere} ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $enabledCategories = [];
        if ($hasCatId) {
            try {
                $enabledCategories = $this->db->fetchAll(
                    "SELECT id, slug, name_en, name_ru, image_url FROM article_categories WHERE enabled = 1 ORDER BY position ASC, id ASC"
                );
            } catch (\Throwable $e) {}
        }
        $display = array_merge([
            'show_author' => true,
            'show_date'   => true,
            'show_likes'  => true,
            'show_views'  => true,
            'show_tags'   => true,
        ], $settings);
        $canonical = $this->canonical($request);
        $currentLocale = $this->container->get('lang')->current();
        $defaultTitle = $currentLocale === 'ru' ? 'Статьи' : 'Articles';
        $defaultDesc = $currentLocale === 'ru'
            ? 'Свежие материалы и новости.'
            : 'Latest articles and updates.';
        $titlePrimary = trim((string)($currentLocale === 'ru' ? ($settings['seo_title_ru'] ?? '') : ($settings['seo_title_en'] ?? '')));
        $titleFallback = trim((string)($currentLocale === 'ru' ? ($settings['seo_title_en'] ?? '') : ($settings['seo_title_ru'] ?? '')));
        $descPrimary = trim((string)($currentLocale === 'ru' ? ($settings['seo_desc_ru'] ?? '') : ($settings['seo_desc_en'] ?? '')));
        $descFallback = trim((string)($currentLocale === 'ru' ? ($settings['seo_desc_en'] ?? '') : ($settings['seo_desc_ru'] ?? '')));
        $listTitle = $titlePrimary !== '' ? $titlePrimary : ($titleFallback !== '' ? $titleFallback : $defaultTitle);
        $listDesc = $descPrimary !== '' ? $descPrimary : ($descFallback !== '' ? $descFallback : $defaultDesc);
        $seoListTitle = $listTitle;
        if ($page > 1) {
            $seoListTitle .= $currentLocale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $html = $this->container->get('renderer')->render(
            'articles/list',
            [
                '_layout' => true,
                'title' => $listTitle,
                'description' => $listDesc,
                'articles'           => $articles,
                'page'               => $page,
                'total'              => $total,
                'perPage'            => $perPage,
                'gridCols'           => $gridCols,
                'locale'             => $currentLocale,
                'display'            => $display,
                'enabledCategories'  => $enabledCategories,
                'breadcrumbs'        => [
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
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $catJoin    = $this->hasColumn('category_id')
            ? " LEFT JOIN article_categories ac ON ac.id = a.category_id"
            : "";
        $catSelect  = $this->hasColumn('category_id')
            ? ", ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru"
            : "";
        $authorJoin   = $this->hasColumn('author_id') ? " LEFT JOIN users u ON u.id = a.author_id" : "";
        $authorSelect = $this->hasColumn('author_id') ? ", u.name AS author_name, u.avatar AS author_avatar" : "";
        $article = $this->db->fetch("
            SELECT a.* {$authorSelect} {$catSelect}
            FROM articles a
            {$authorJoin}
            {$catJoin}
            WHERE a.slug = ?
        ", [$slug]);
        if (!$article) {
            return new Response('Not found', 404);
        }
        // Новость — редирект на /news/{slug}
        if ($this->hasColumn('type') && ($article['type'] ?? 'article') === 'news') {
            return new Response('', 301, ['Location' => '/news/' . rawurlencode($slug)]);
        }
        $this->db->execute("UPDATE articles SET views = views + 1 WHERE id = ?", [$article['id']]);

        // Cache check
        $cacheSettings = $this->container->get(\App\Services\SettingsService::class);
        $cacheEnabled  = $cacheSettings->get('cache_articles', '0') === '1';
        $cacheTtl      = max(1, (int)$cacheSettings->get('cache_articles_ttl', '60')) * 60;
        $lang          = $this->container->get('lang')->current();
        $cacheKey      = 'article_' . $slug . '_' . $lang;
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($cacheEnabled && ($cached = $cache->get($cacheKey))) {
            return new Response($cached);
        }

        $tags = $this->db->fetchAll("
            SELECT t.name, t.slug
            FROM taggables tg
            JOIN tags t ON t.id = tg.tag_id
            WHERE tg.entity_type = 'article' AND tg.entity_id = ?
        ", [(int)$article['id']]);
        $locale = $this->container->get('lang')->current();
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $bodyKey = $locale === 'ru' ? 'body_ru' : 'body_en';
        $canonical = $this->canonical($request);
        $desc = '';
        if ($this->hasColumn('description_en')) {
            $desc = trim($locale === 'ru' ? ($article['description_ru'] ?? '') : ($article['description_en'] ?? ''));
            if ($desc === '') {
                $desc = trim($locale !== 'ru' ? ($article['description_ru'] ?? '') : ($article['description_en'] ?? ''));
            }
        }
        if ($desc === '') {
            $previewKey = $locale === 'ru' ? 'preview_ru' : 'preview_en';
            if (!empty($article[$previewKey])) {
                $desc = $article[$previewKey];
            } elseif ($this->hasColumn('preview_en')) {
                $fallbackPreview = $locale === 'ru' ? ($article['preview_en'] ?? '') : ($article['preview_ru'] ?? '');
                if (!empty($fallbackPreview)) {
                    $desc = $fallbackPreview;
                }
            }
        }
        if ($desc === '') {
            $desc = substr(strip_tags((string)$article[$bodyKey]), 0, 150);
        }
        $display = $this->moduleSettings->all('articles');
        $display = array_merge([
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ], $display);
        $ogImg = $this->absoluteImage($this->resolveOgImage($article), $request);
        if (!$ogImg) {
            $ogImg = $this->absoluteImage($this->defaultOgImage(), $request);
        }
        if (!$ogImg) {
            $ogImg = $this->absoluteImage('/assets/theme/og-default.png', $request);
        }
        $authorVisibility = $article['author_profile_visibility'] ?? 'public';
        $authorPrivate = $authorVisibility === 'private';
        $authorBypass = $this->canBypassPrivateProfile((int)($article['author_id'] ?? 0));
        $showSignature = !$authorPrivate || $authorBypass;
        $authorProfileUrl = $this->profileUrl($article['author_id'] ?? null, $article['author_username'] ?? null);

        // Generate JSON-LD structured data
        $cfg = include APP_ROOT . '/app/config/app.php';
        $baseUrl = rtrim($cfg['url'] ?? '', '/');
        $schemaProvider = new ArticleSchemaProvider($baseUrl);
        $articleSchema = $schemaProvider->getSchema($article);

        $settings = $this->container->get(\App\Services\SettingsService::class)->all();
        $orgSchema = CommonSchemas::organization([
            'name' => $settings['site_title'] ?? 'SteelRoot',
            'url' => $baseUrl,
            'logo' => $baseUrl . '/assets/theme/logo.png'
        ]);

        $mergedSchema = JsonLdRenderer::merge($articleSchema, $orgSchema);
        $jsonLd = JsonLdRenderer::render($mergedSchema);

        $html = $this->container->get('renderer')->render(
            'articles/item',
            [
                '_layout' => true,
                'title' => $article[$titleKey] ?? '',
                'article' => $article,
                'locale' => $locale,
                'tags' => $tags,
                'display' => $display,
                'authorProfileUrl' => $authorProfileUrl,
                'authorSignature' => $showSignature ? ($article['author_signature'] ?? '') : '',
                'authorSignatureVisible' => $showSignature && !empty($article['author_signature']),
                'breadcrumbs' => array_filter([
                    ['label' => 'Articles', 'url' => '/articles'],
                    !empty($article['category_slug']) ? [
                        'label' => ($locale === 'ru' ? ($article['category_name_ru'] ?: $article['category_name_en']) : ($article['category_name_en'] ?: $article['category_name_ru'])),
                        'url'   => '/articles/category/' . rawurlencode($article['category_slug']),
                    ] : null,
                    ['label' => $article[$titleKey] ?? 'Article'],
                ]),
            ],
            [
                'title' => $article[$titleKey] ?? '',
                'description' => $desc,
                'canonical' => $canonical,
                'image' => $ogImg,
                'jsonld' => $jsonLd,
            ]
        );
        if ($cacheEnabled) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }
        return new Response($html);
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
        return $base . $uri;
    }

    private function resolveOgImage(array $article): ?string
    {
        if (!empty($article['image_url'])) {
            return $article['image_url'];
        }
        return $this->defaultOgImage();
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

    private function absoluteImage(?string $src, Request $request): ?string
    {
        if (!$src) {
            return null;
        }
        $src = trim($src);
        if ($src === '') {
            return null;
        }
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }
        $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? '';
        if ($host === '') {
            $base = $this->canonical($request);
            $parsed = parse_url($base);
            if (!empty($parsed['host'])) {
                $host = $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                $scheme = $parsed['scheme'] ?? $scheme;
            }
        }
        if ($host === '') {
            return null;
        }
        if (str_starts_with($src, '/')) {
            return $scheme . '://' . $host . $src;
        }
        return $scheme . '://' . $host . '/' . ltrim($src, '/');
    }

    private function hasColumn(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM articles LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
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
}
