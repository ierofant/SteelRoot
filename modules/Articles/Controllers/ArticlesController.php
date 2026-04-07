<?php
namespace Modules\Articles\Controllers;

use App\Services\SlugService;
use App\Services\UserPublicSummaryService;
use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Core\Meta\JsonLdRenderer;
use Core\Meta\CommonSchemas;
use Modules\Articles\Providers\ArticleSchemaProvider;
use Modules\Articles\Services\ArticleCategoryService;
use Modules\Comments\Services\CommentService;
use Modules\Users\Services\Auth;

class ArticlesController
{
    private Container $container;
    private Database $db;
    private ModuleSettings $moduleSettings;
    private UserPublicSummaryService $userSummaries;
    private ArticleCategoryService $articleCategories;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->userSummaries = $container->get(UserPublicSummaryService::class);
        $this->articleCategories = new ArticleCategoryService($this->db, $container->get('cache'));
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
        $sort     = $this->resolveSort($request);
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
        $hasAuthor = $this->hasColumn('author_id');
        if ($hasAuthor) {
            $select .= ", a.author_id";
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
        $orderBy = $this->orderBySql($sort, true);
        $typeFilter = $this->hasColumn('type') ? "AND (a.type = 'article' OR a.type IS NULL OR a.type = '')" : '';
        $articles = $this->db->fetchAll(
            "SELECT {$select} FROM articles a {$join}
             WHERE a.category_id = ? {$typeFilter}
             ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            [(int)$category['id']]
        );
        if ($hasAuthor) {
            $articles = $this->userSummaries->hydrateRows($articles);
        }
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
                'sort'        => $sort,
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
        $nPage   = max(1, (int)($request->query['np'] ?? 1));
        $gPage   = max(1, (int)($request->query['gp'] ?? 1));
        $pPage   = max(1, (int)($request->query['pp'] ?? 1));

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
        $nTotal = (int)($this->db->fetch("
            SELECT COUNT(*) AS cnt FROM news n
            JOIN taggables tg ON tg.entity_id = n.id AND tg.entity_type = 'news'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
        ", [$slug])['cnt'] ?? 0);
        $pTotal = (int)($this->db->fetch("
            SELECT COUNT(*) AS cnt FROM pages p
            JOIN taggables tg ON tg.entity_id = p.id AND tg.entity_type = 'page'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ? AND p.visible = 1
        ", [$slug])['cnt'] ?? 0);

        $aPages = max(1, (int)ceil($aTotal / $perPage));
        $gPages = max(1, (int)ceil($gTotal / $perPage));
        $nPages = max(1, (int)ceil($nTotal / $perPage));
        $pPages = max(1, (int)ceil($pTotal / $perPage));
        $aPage = min($aPage, $aPages);
        $gPage = min($gPage, $gPages);
        $nPage = min($nPage, $nPages);
        $pPage = min($pPage, $pPages);

        $aOffset = ($aPage - 1) * $perPage;
        $gOffset = ($gPage - 1) * $perPage;
        $nOffset = ($nPage - 1) * $perPage;
        $pOffset = ($pPage - 1) * $perPage;

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
        $news = $this->db->fetchAll("
            SELECT n.slug, n.title_en, n.title_ru, n.preview_en, n.preview_ru, n.image_url, n.created_at, n.views, n.likes
            FROM news n
            JOIN taggables tg ON tg.entity_id = n.id AND tg.entity_type = 'news'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
            ORDER BY n.created_at DESC, n.id DESC
            LIMIT {$perPage} OFFSET {$nOffset}
        ", [$slug]);
        $pages = $this->db->fetchAll("
            SELECT p.slug, p.title_en, p.title_ru, p.updated_at
            FROM pages p
            JOIN taggables tg ON tg.entity_id = p.id AND tg.entity_type = 'page'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ? AND p.visible = 1
            ORDER BY COALESCE(p.updated_at, p.created_at) DESC, p.id DESC
            LIMIT {$perPage} OFFSET {$pOffset}
        ", [$slug]);
        $tagName = $tag['name'] ?? $slug;
        $currentLocale = $this->container->get('lang')->current();
        $titleText = $currentLocale === 'ru' ? ('Тег: ' . $tagName) : ('Tag: ' . $tagName);
        if ($pPage > 1) {
            $titleText .= $currentLocale === 'ru'
                ? (' | Страницы, стр. ' . $pPage)
                : (' | Pages, page ' . $pPage);
        }
        if ($aPage > 1) {
            $titleText .= $currentLocale === 'ru'
                ? (' | Статьи, стр. ' . $aPage)
                : (' | Articles, page ' . $aPage);
        }
        if ($nPage > 1) {
            $titleText .= $currentLocale === 'ru'
                ? (' | Новости, стр. ' . $nPage)
                : (' | News, page ' . $nPage);
        }
        if ($gPage > 1) {
            $titleText .= $currentLocale === 'ru'
                ? (' | Галерея, стр. ' . $gPage)
                : (' | Gallery, page ' . $gPage);
        }
        $tagDesc = $currentLocale === 'ru'
            ? ('Материалы по тегу «' . $tagName . '»: страницы, статьи, новости и изображения.')
            : ('Content for tag "' . $tagName . '": pages, articles, news and images.');
        $tagBase   = '/tags/' . rawurlencode($slug);
        $canonical = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage, $nPage, $gPage);
        $paginationMeta = $this->tagPaginationMeta($request, $tagBase, $pPage, $pPages, $aPage, $aPages, $nPage, $nPages, $gPage, $gPages);
        $html = $this->container->get('renderer')->render(
            'tags/show',
            [
                '_layout'   => true,
                'title'     => $titleText,
                'pages'     => $pages,
                'articles'  => $articles,
                'news'      => $news,
                'gallery'   => $gallery,
                'locale'    => $this->container->get('lang')->current(),
                'slug'      => $slug,
                'tagName'   => $tag['name'] ?? $slug,
                'openMode'  => $this->container->get(\App\Services\SettingsService::class)->get('gallery_open_mode', 'lightbox'),
                'pPage'     => $pPage,
                'pTotal'    => $pTotal,
                'aPage'     => $aPage,
                'aTotal'    => $aTotal,
                'nPage'     => $nPage,
                'nTotal'    => $nTotal,
                'gPage'     => $gPage,
                'gTotal'    => $gTotal,
                'perPage'   => $perPage,
                'tagBase'   => $tagBase,
            ],
            [
                'title' => $titleText,
                'canonical' => $canonical,
                'description' => $tagDesc,
                'image' => '/icon/tags_og.jpg',
                'prev' => $paginationMeta['prev'],
                'next' => $paginationMeta['next'],
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
        $sort     = $this->resolveSort($request);
        $select = "a.slug, a.title_en, a.title_ru, a.created_at";
        $join = '';
        $hasAuthor = $this->hasColumn('author_id');
        if ($hasAuthor) {
            $select .= ", a.author_id";
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
        $orderBy = $this->orderBySql($sort, false);
        $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM articles a {$typeWhere}");
        $total    = (int)($totalRow['cnt'] ?? 0);
        $offset   = ($page - 1) * $perPage;
        $articles = $this->db->fetchAll(
            "SELECT {$select} FROM articles a {$join} {$typeWhere} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}"
        );
        if ($hasAuthor) {
            $articles = $this->userSummaries->hydrateRows($articles);
        }
        $enabledCategories = [];
        if ($hasCatId) {
            $enabledCategories = $this->articleCategories->enabled();
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
                'sort'               => $sort,
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

    private function resolveSort(Request $request): string
    {
        $sort = trim((string)($request->query['sort'] ?? 'new'));
        return in_array($sort, ['new', 'views', 'likes'], true) ? $sort : 'new';
    }

    private function orderBySql(string $sort, bool $prefixed = false): string
    {
        $prefix = $prefixed ? 'a.' : '';
        if ($sort === 'views' && $this->hasColumn('views')) {
            return "{$prefix}views DESC, {$prefix}created_at DESC";
        }
        if ($sort === 'likes' && $this->hasColumn('likes')) {
            return "{$prefix}likes DESC, {$prefix}created_at DESC";
        }
        return "{$prefix}created_at DESC";
    }

    public function view(Request $request): Response
    {
        $slug = (string)($request->params['slug'] ?? '');
        $catJoin    = $this->hasColumn('category_id')
            ? " LEFT JOIN article_categories ac ON ac.id = a.category_id"
            : "";
        $catSelect  = $this->hasColumn('category_id')
            ? ", ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru"
            : "";
        $authorJoin   = $this->hasColumn('author_id') ? " LEFT JOIN users u ON u.id = a.author_id" : "";
        $authorSelect = $this->hasColumn('author_id')
            ? ", u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature"
            : "";
        $payload = $this->loadPublicArticlePayload($slug, $authorSelect, $catSelect, $authorJoin, $catJoin);
        if ($payload === null) {
            return new Response('Not found', 404);
        }
        $article = $payload['article'];
        $tags = $payload['tags'];
        $article = $this->userSummaries->hydrateRow($article);
        if (!empty($article['slug']) && $slug !== (string)$article['slug']) {
            return new Response('', 301, ['Location' => '/articles/' . rawurlencode((string)$article['slug'])]);
        }
        // Новость — редирект на /news/{slug}
        if ($this->hasColumn('type') && ($article['type'] ?? 'article') === 'news') {
            $newsSlug = (string)($article['slug'] ?: SlugService::slugify((string)($article['title_en'] ?: ($article['title_ru'] ?: ('news-' . (int)$article['id']))), 'news-' . (int)$article['id']));
            return new Response('', 301, ['Location' => '/news/' . rawurlencode($newsSlug)]);
        }
        // Cache check
        $cacheSettings = $this->container->get(\App\Services\SettingsService::class);
        $cacheEnabled  = $cacheSettings->get('cache_articles', '0') === '1';
        $cacheTtl      = max(1, (int)$cacheSettings->get('cache_articles_ttl', '60')) * 60;
        $lang          = $this->container->get('lang')->current();
        $cacheKey      = 'article_' . $slug . '_' . $lang;
        $isGuestViewer = empty($_SESSION['user_id']) && empty($_SESSION['admin_auth']);
        $cacheHeaders  = ['X-Page-Cache' => 'BYPASS'];
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($cacheEnabled && $isGuestViewer && ($cached = $cache->get($cacheKey))) {
            return new Response($cached, 200, ['X-Page-Cache' => 'HIT']);
        }
        if ($cacheEnabled && $isGuestViewer) {
            $cacheHeaders['X-Page-Cache'] = 'MISS';
        }

        $this->db->execute("UPDATE articles SET views = views + 1, updated_at = updated_at WHERE id = ?", [$article['id']]);
        if (isset($article['views'])) {
            $article['views'] = (int)$article['views'] + 1;
        }
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
        $authorVisibility = trim((string)($article['author_profile_visibility'] ?? 'public'));
        if ($authorVisibility === '') {
            $authorVisibility = 'public';
        }
        $authorPrivate = $authorVisibility === 'private';
        $authorBypass = $this->canBypassPrivateProfile((int)($article['author_id'] ?? 0));
        $showSignature = !$authorPrivate || $authorBypass;
        $authorProfileUrl = $this->profileUrl($article['author_id'] ?? null, $article['author_username'] ?? null);

        // Generate JSON-LD structured data
        $cfg     = include APP_ROOT . '/app/config/app.php';
        $baseUrl = rtrim($cfg['url'] ?? '', '/');
        $settings = $this->container->get(\App\Services\SettingsService::class)->all();

        $schemaProvider = new ArticleSchemaProvider($baseUrl);
        $articleSchema  = $schemaProvider->getSchema($article, [
            'locale'     => $locale,
            'canonical'  => $canonical,
            'ogImg'      => $ogImg,
            'desc'       => $desc,
            'tags'       => $tags,
            'publicBase' => '/articles',
            'org'        => [
                'name' => $settings['site_name'] ?? 'TattooRoot',
                'url'  => $baseUrl,
                'logo' => $baseUrl . '/assets/theme/logo.png',
            ],
        ]);

        $breadcrumbItems = array_values(array_filter([
            ['name' => 'Articles', 'url' => $baseUrl . '/articles'],
            !empty($article['category_slug']) ? [
                'name' => $locale === 'ru'
                    ? ($article['category_name_ru'] ?: ($article['category_name_en'] ?? ''))
                    : ($article['category_name_en'] ?: ($article['category_name_ru'] ?? '')),
                'url'  => $baseUrl . '/articles/category/' . rawurlencode($article['category_slug']),
            ] : null,
            ['name' => $article[$titleKey] ?? 'Article', 'url' => $canonical],
        ]));
        $breadcrumbSchema = CommonSchemas::breadcrumbList($breadcrumbItems);
        $jsonLd = JsonLdRenderer::render(JsonLdRenderer::merge($articleSchema, $breadcrumbSchema));

        $html = $this->container->get('renderer')->render(
            'articles/item',
            [
                '_layout' => true,
                'title' => $article[$titleKey] ?? '',
                'article' => $article,
                'locale' => $locale,
                'tags' => $tags,
                'display' => $display,
                'commentsHtml' => $this->renderComments((int)$article['id']),
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
        if ($cacheEnabled && $isGuestViewer) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }
        return new Response($html, 200, $cacheHeaders);
    }

    private function loadPublicArticlePayload(string $slug, string $authorSelect, string $catSelect, string $authorJoin, string $catJoin): ?array
    {
        $cache = $this->container->get('cache');
        $settings = $this->container->get(\App\Services\SettingsService::class);
        $enabled = $settings->get('cache_article_public', '1') === '1';
        $ttl = max(1, (int)$settings->get('cache_article_public_ttl', '30')) * 60;
        $cacheKey = 'article_public_payload_' . sha1($slug);
        if ($enabled) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached) && isset($cached['article']) && isset($cached['tags'])) {
                return $cached;
            }
        }

        $article = $this->resolveArticleBySlug($slug, $authorSelect, $catSelect, $authorJoin, $catJoin);
        if (!$article) {
            return null;
        }
        $tags = $this->db->fetchAll("
            SELECT t.name, t.slug
            FROM taggables tg
            JOIN tags t ON t.id = tg.tag_id
            WHERE tg.entity_type = 'article' AND tg.entity_id = ?
        ", [(int)$article['id']]);

        $payload = [
            'article' => $article,
            'tags' => $tags,
        ];
        if ($enabled) {
            $cache->set($cacheKey, $payload, $ttl);
        }

        return $payload;
    }

    private function resolveArticleBySlug(string $rawSlug, string $authorSelect, string $catSelect, string $authorJoin, string $catJoin): ?array
    {
        $sql = "
            SELECT a.* {$authorSelect} {$catSelect}
            FROM articles a
            {$authorJoin}
            {$catJoin}
            WHERE a.slug = ?
            LIMIT 1
        ";

        foreach (SlugService::candidates($rawSlug) as $candidate) {
            $article = $this->db->fetch($sql, [$candidate]);
            if ($article) {
                return $article;
            }
        }

        $generated = SlugService::slugify(rawurldecode($rawSlug), '');
        if ($generated === '') {
            return null;
        }

        $rows = $this->db->fetchAll("
            SELECT a.* {$authorSelect} {$catSelect}
            FROM articles a
            {$authorJoin}
            {$catJoin}
            WHERE a.slug IS NULL OR a.slug = ''
            ORDER BY a.id DESC
            LIMIT 200
        ");

        foreach ($rows as $row) {
            $candidate = SlugService::slugify((string)($row['title_en'] ?: ($row['title_ru'] ?: ('article-' . (int)$row['id']))), '');
            if ($candidate !== '' && $candidate === $generated) {
                return $row;
            }
        }

        return null;
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

    private function tagPaginationMeta(
        Request $request,
        string $tagBase,
        int $pPage,
        int $pPages,
        int $aPage,
        int $aPages,
        int $nPage,
        int $nPages,
        int $gPage,
        int $gPages
    ): array {
        $prev = null;
        $next = null;

        // Two independent paginators cannot be expressed safely via one prev/next chain.
        $seriesCount = 0;
        foreach ([$pPages, $aPages, $nPages, $gPages] as $pages) {
            if ($pages > 1) {
                $seriesCount++;
            }
        }
        if ($seriesCount > 1) {
            return ['prev' => null, 'next' => null];
        }

        if ($pPages > 1) {
            if ($pPage > 1) {
                $prev = $this->tagPaginationUrl($request, $tagBase, $pPage - 1, $aPage, $nPage, $gPage);
            }
            if ($pPage < $pPages) {
                $next = $this->tagPaginationUrl($request, $tagBase, $pPage + 1, $aPage, $nPage, $gPage);
            }
        } elseif ($aPages > 1) {
            if ($aPage > 1) {
                $prev = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage - 1, $nPage, $gPage);
            }
            if ($aPage < $aPages) {
                $next = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage + 1, $nPage, $gPage);
            }
        } elseif ($nPages > 1) {
            if ($nPage > 1) {
                $prev = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage, $nPage - 1, $gPage);
            }
            if ($nPage < $nPages) {
                $next = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage, $nPage + 1, $gPage);
            }
        } elseif ($gPages > 1) {
            if ($gPage > 1) {
                $prev = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage, $nPage, $gPage - 1);
            }
            if ($gPage < $gPages) {
                $next = $this->tagPaginationUrl($request, $tagBase, $pPage, $aPage, $nPage, $gPage + 1);
            }
        }

        return ['prev' => $prev, 'next' => $next];
    }

    private function tagPaginationUrl(Request $request, string $tagBase, int $pPage, int $aPage, int $nPage, int $gPage): string
    {
        $params = [];
        if ($pPage > 1) {
            $params['pp'] = $pPage;
        }
        if ($aPage > 1) {
            $params['ap'] = $aPage;
        }
        if ($nPage > 1) {
            $params['np'] = $nPage;
        }
        if ($gPage > 1) {
            $params['gp'] = $gPage;
        }

        $path = $tagBase;
        if ($params !== []) {
            $path .= '?' . http_build_query($params);
        }

        return $this->absoluteUrl($request, $path);
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }

        return $base . $path;
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

    private function renderComments(int $articleId): string
    {
        try {
            return $this->container->get(CommentService::class)->renderForEntity('article', $articleId);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
