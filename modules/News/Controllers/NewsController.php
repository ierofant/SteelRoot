<?php
namespace Modules\News\Controllers;

use App\Services\SlugService;
use App\Services\SettingsService;
use App\Services\UserPublicSummaryService;
use Core\Meta\CommonSchemas;
use Core\Meta\JsonLdRenderer;
use Core\Request;
use Core\Response;
use Modules\ContentBase\Controllers\BasePublicController;
use Modules\Comments\Services\CommentService;
use Modules\News\Providers\NewsSchemaProvider;

class NewsController extends BasePublicController
{
    private UserPublicSummaryService $userSummaries;

    public function __construct(\Core\Container $container)
    {
        parent::__construct($container);
        $this->userSummaries = $container->get(UserPublicSummaryService::class);
    }

    protected function table(): string          { return 'news'; }
    protected function categoriesTable(): string { return 'news_categories'; }
    protected function moduleKey(): string       { return 'news'; }
    protected function publicBase(): string      { return '/news'; }
    protected function entityType(): string      { return 'news'; }

    protected function listTitle(string $locale): string
    {
        return $locale === 'ru' ? 'Новости' : 'News';
    }

    protected function listDescription(string $locale): string
    {
        return $locale === 'ru'
            ? 'Последние новости тату-индустрии.'
            : 'Latest tattoo industry news.';
    }

    protected function buildSelect(): array
    {
        $select = "a.id, a.slug, a.title_en, a.title_ru, a.created_at";
        $join   = '';
        if ($this->columnExists('views')) {
            $select .= ", a.views";
        }
        if ($this->columnExists('likes')) {
            $select .= ", a.likes";
        }
        if ($this->columnExists('preview_en')) {
            $select .= ", a.preview_en, a.preview_ru";
        }
        if ($this->columnExists('image_url')) {
            $select .= ", a.image_url";
        }
        if ($this->columnExists('cover_url')) {
            $select .= ", a.cover_url";
        }
        if ($this->columnExists('author_id') && $this->tableExists('users')) {
            $select .= ", a.author_id";
        }
        if ($this->columnExists('category_id')) {
            $cats = $this->categoriesTable();
            $select .= ", a.category_id, ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru";
            $join   .= " LEFT JOIN {$cats} ac ON ac.id = a.category_id";
        }
        return [$select, $join];
    }

    public function index(Request $request): Response
    {
        $settings = $this->moduleSettings->all($this->moduleKey());
        $perPage  = max(1, (int)($settings['per_page'] ?? 9));
        $gridCols = max(1, min(6, (int)($settings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $locale   = $this->container->get('lang')->current();
        $defaultTitle = $this->listTitle($locale);
        $defaultDesc = $this->listDescription($locale);
        $titlePrimary = trim((string)($locale === 'ru' ? ($settings['seo_title_ru'] ?? '') : ($settings['seo_title_en'] ?? '')));
        $titleFallback = trim((string)($locale === 'ru' ? ($settings['seo_title_en'] ?? '') : ($settings['seo_title_ru'] ?? '')));
        $descPrimary = trim((string)($locale === 'ru' ? ($settings['seo_desc_ru'] ?? '') : ($settings['seo_desc_en'] ?? '')));
        $descFallback = trim((string)($locale === 'ru' ? ($settings['seo_desc_en'] ?? '') : ($settings['seo_desc_ru'] ?? '')));
        $rootTitle = $titlePrimary !== '' ? $titlePrimary : ($titleFallback !== '' ? $titleFallback : $defaultTitle);
        $rootDesc = $descPrimary !== '' ? $descPrimary : ($descFallback !== '' ? $descFallback : $defaultDesc);

        [$select, $join] = $this->buildSelect();

        $total  = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()} a")['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table()} a {$join}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $items = $this->userSummaries->hydrateRows($items);

        $display           = $this->mergeDisplay($settings);
        $enabledCategories = $this->loadEnabledCategories();

        $html = $this->container->get('renderer')->render('news/list', [
            '_layout'           => true,
            'title'             => $rootTitle,
            'description'       => $rootDesc,
            'articles'          => $items,
            'page'              => $page,
            'total'             => $total,
            'perPage'           => $perPage,
            'gridCols'          => $gridCols,
            'locale'            => $locale,
            'display'           => $display,
            'enabledCategories' => $enabledCategories,
            'listBaseUrl'       => $this->publicBase(),
            'categoryBaseUrl'   => $this->publicBase() . '/category',
            'itemBaseUrl'       => $this->publicBase(),
            'breadcrumbs'       => [['label' => $rootTitle]],
        ], [
            'title'       => $page > 1
                ? ($rootTitle . ($locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page)))
                : $rootTitle,
            'description' => $rootDesc,
            'canonical'   => $this->canonical($request),
            'image'       => $this->defaultOgImage($request),
        ]);
        return new Response($html);
    }

    public function byCategory(Request $request): Response
    {
        $slug     = $request->params['slug'] ?? '';
        $category = $this->db->fetch(
            "SELECT * FROM {$this->categoriesTable()} WHERE slug = ? AND enabled = 1",
            [$slug]
        );
        if (!$category) {
            return new Response('Not found', 404);
        }

        $settings = $this->moduleSettings->all($this->moduleKey());
        $perPage  = max(1, (int)($settings['per_page'] ?? 9));
        $gridCols = max(1, min(6, (int)($settings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $locale   = $this->container->get('lang')->current();

        $total  = (int)($this->db->fetch(
            "SELECT COUNT(*) as cnt FROM {$this->table()} a WHERE a.category_id = ?",
            [(int)$category['id']]
        )['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        [$select, $join] = $this->buildSelect();
        $items = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table()} a {$join}
             WHERE a.category_id = ?
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [(int)$category['id']]
        );
        $items = $this->userSummaries->hydrateRows($items);

        $titleKey = $locale === 'ru' ? 'name_ru' : 'name_en';
        $catTitle = $category[$titleKey] ?: ($category['name_en'] ?: $category['name_ru']);
        $display  = $this->mergeDisplay($settings);
        $isPrimaryNewsCategory = ($slug === 'tattoo-press');
        $canonical = $isPrimaryNewsCategory
            ? $this->absolutePathCanonical($request, $this->publicBase())
            : $this->canonical($request);
        $seoTitle = $locale === 'ru' ? ($catTitle . ' — новости') : ($catTitle . ' — news');
        if ($page > 1) {
            $seoTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $seoDesc = $locale === 'ru'
            ? ('Новости в категории «' . $catTitle . '». Свежие публикации и материалы.')
            : ('News in "' . $catTitle . '" category. Fresh publications and updates.');

        $html = $this->container->get('renderer')->render('news/list', [
            '_layout'         => true,
            'title'           => $catTitle,
            'description'     => $seoDesc,
            'articles'        => $items,
            'page'            => $page,
            'total'           => $total,
            'perPage'         => $perPage,
            'gridCols'        => $gridCols,
            'locale'          => $locale,
            'display'         => $display,
            'category'        => $category,
            'listBaseUrl'     => $this->publicBase(),
            'categoryBaseUrl' => $this->publicBase() . '/category',
            'itemBaseUrl'     => $this->publicBase(),
            'breadcrumbs'     => [
                ['label' => $this->listTitle($locale), 'url' => $this->publicBase()],
                ['label' => $catTitle],
            ],
        ], [
            'title'     => $seoTitle,
            'description' => $seoDesc,
            'canonical' => $canonical,
            'image'     => $this->categoryImage($category, $request) ?: $this->defaultOgImage($request),
            'robots'    => $isPrimaryNewsCategory ? 'noindex,follow' : null,
        ]);
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = (string)($request->params['slug'] ?? '');
        $locale = $this->container->get('lang')->current();
        $lang   = $locale === 'ru' ? 'ru' : 'en';
        $cacheSettings = $this->container->get(SettingsService::class);
        $cacheEnabled  = $cacheSettings->get('cache_news', '0') === '1';
        $cacheTtl      = max(1, (int)$cacheSettings->get('cache_news_ttl', '60')) * 60;
        $cacheKey      = 'news_' . $slug . '_' . $lang;
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

        $payload = $this->loadPublicNewsPayload($slug);
        if ($payload === null) {
            return new Response('Not found', 404);
        }
        $item = $payload['item'];
        if (!empty($item['slug']) && $slug !== (string)$item['slug']) {
            return new Response('', 301, ['Location' => '/news/' . rawurlencode((string)$item['slug'])]);
        }

        if (!$isGuestViewer) {
            $item = $this->refreshNewsCounters($item);
        }

        $this->db->execute("UPDATE {$this->table()} SET views = views + 1, updated_at = updated_at WHERE id = ?", [$item['id']]);
        if (array_key_exists('views', $item)) {
            $item['views'] = (int)$item['views'] + 1;
        }
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $bodyKey  = $locale === 'ru' ? 'body_ru'  : 'body_en';
        $display  = $this->mergeDisplay($this->moduleSettings->all($this->moduleKey()));
        $canonical = $this->canonical($request);

        $tags = $payload['tags'];

        $desc = '';
        if ($this->columnExists('description_en')) {
            $desc = trim((string)($locale === 'ru' ? ($item['description_ru'] ?? '') : ($item['description_en'] ?? '')));
            if ($desc === '') {
                $desc = trim((string)($locale === 'ru' ? ($item['description_en'] ?? '') : ($item['description_ru'] ?? '')));
            }
        }
        if ($desc === '') {
            $desc = substr(strip_tags((string)($item[$bodyKey] ?? '')), 0, 160);
        }

        // OG image — absolute URL
        $ogImg = $this->newsMetaImage($item, $request);
        if (!$ogImg) {
            $settingsService = $this->container->get(SettingsService::class);
            $ogImg = $this->newsAbsoluteImage($settingsService->get('og_image', ''), $request);
        }

        // JSON-LD
        $cfg     = include APP_ROOT . '/app/config/app.php';
        $baseUrl = rtrim($cfg['url'] ?? '', '/');
        $globalSettings = $this->container->get(SettingsService::class)->all();

        $schemaProvider = new NewsSchemaProvider($baseUrl);
        $newsSchema     = $schemaProvider->getSchema($item, [
            'locale'     => $locale,
            'canonical'  => $canonical,
            'ogImg'      => $ogImg,
            'desc'       => $desc,
            'tags'       => $tags,
            'publicBase' => $this->publicBase(),
            'org'        => [
                'name' => $globalSettings['site_name'] ?? 'TattooRoot',
                'url'  => $baseUrl,
                'logo' => $baseUrl . '/assets/theme/logo.png',
            ],
        ]);
        $breadcrumbSchema = CommonSchemas::breadcrumbList(array_values(array_filter([
            ['name' => $this->listTitle($locale), 'url' => $baseUrl . $this->publicBase()],
            !empty($item['category_slug']) ? [
                'name' => $locale === 'ru'
                    ? ($item['category_name_ru'] ?: ($item['category_name_en'] ?? ''))
                    : ($item['category_name_en'] ?: ($item['category_name_ru'] ?? '')),
                'url'  => $baseUrl . $this->publicBase() . '/category/' . rawurlencode((string)$item['category_slug']),
            ] : null,
            ['name' => $item[$titleKey] ?? '', 'url' => $canonical],
        ])));
        $jsonLd = JsonLdRenderer::render(JsonLdRenderer::merge($newsSchema, $breadcrumbSchema));

        $html = $this->container->get('renderer')->render('articles/item', [
            '_layout'    => true,
            'title'      => $item[$titleKey] ?? '',
            'article'    => $item,
            'locale'     => $locale,
            'tags'       => $tags,
            'display'    => $display,
            'commentsHtml' => $this->renderComments((int)$item['id']),
            'authorProfileUrl' => $this->profileUrl($item['author_id'] ?? null, $item['author_username'] ?? null),
            'breadcrumbs' => array_values(array_filter([
                ['label' => $this->listTitle($locale), 'url' => $this->publicBase()],
                !empty($item['category_slug']) ? [
                    'label' => $locale === 'ru'
                        ? ($item['category_name_ru'] ?: ($item['category_name_en'] ?? ''))
                        : ($item['category_name_en'] ?: ($item['category_name_ru'] ?? '')),
                    'url'   => $this->publicBase() . '/category/' . rawurlencode((string)$item['category_slug']),
                ] : null,
                ['label' => $item[$titleKey] ?? ''],
            ])),
        ], [
            'title'       => $item[$titleKey] ?? '',
            'description' => $desc,
            'canonical'   => $canonical,
            'image'       => $ogImg,
            'jsonld'      => $jsonLd,
        ]);

        if ($cacheEnabled && $isGuestViewer) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }

        return new Response($html, 200, $cacheHeaders);
    }

    private function loadPublicNewsPayload(string $slug): ?array
    {
        $settings = $this->container->get(SettingsService::class);
        $enabled = $settings->get('cache_news', '0') === '1';
        $ttl = max(1, (int)$settings->get('cache_news_ttl', '60')) * 60;
        $locale = $this->container->get('lang')->current();
        $cacheKey = 'news_public_payload_' . sha1($locale . '|' . $slug);
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');

        if ($enabled) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $item = $this->resolveItemBySlug($slug);
        if (!$item) {
            return null;
        }

        $item = $this->userSummaries->hydrateRow($item);
        $tags = [];
        try {
            $tags = $this->db->fetchAll(
                "SELECT t.name, t.slug FROM taggables tg JOIN tags t ON t.id = tg.tag_id
                 WHERE tg.entity_type = ? AND tg.entity_id = ?",
                [$this->entityType(), (int)$item['id']]
            );
        } catch (\Throwable $e) {
            $tags = [];
        }

        $payload = [
            'item' => $item,
            'tags' => $tags,
        ];

        if ($enabled) {
            $cache->set($cacheKey, $payload, $ttl);
        }

        return $payload;
    }

    private function refreshNewsCounters(array $item): array
    {
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) {
            return $item;
        }

        $select = [];
        if ($this->columnExists('likes')) {
            $select[] = 'likes';
        }
        if ($this->columnExists('views')) {
            $select[] = 'views';
        }
        if ($select === []) {
            return $item;
        }

        try {
            $row = $this->db->fetch(
                'SELECT ' . implode(', ', $select) . " FROM {$this->table()} WHERE id = ?{$this->publicVisibilitySql()} LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            return $item;
        }

        if (!$row) {
            return $item;
        }

        foreach ($select as $column) {
            $item[$column] = (int)($row[$column] ?? 0);
        }

        return $item;
    }

    private function resolveItemBySlug(string $rawSlug): ?array
    {
        $select = 'a.*';
        $join = '';
        if ($this->columnExists('category_id') && $this->tableExists($this->categoriesTable())) {
            $select .= ', ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru';
            $join = " LEFT JOIN {$this->categoriesTable()} ac ON ac.id = a.category_id";
        }

        foreach (SlugService::candidates($rawSlug) as $candidate) {
            $item = $this->db->fetch("SELECT {$select} FROM {$this->table()} a {$join} WHERE a.slug = ? LIMIT 1", [$candidate]);
            if ($item) {
                return $item;
            }
        }

        $generated = SlugService::slugify(rawurldecode($rawSlug), '');
        if ($generated === '') {
            return null;
        }

        $rows = $this->db->fetchAll("
            SELECT {$select} FROM {$this->table()} a {$join}
            WHERE a.slug IS NULL OR a.slug = ''
            ORDER BY a.id DESC
            LIMIT 200
        ");
        foreach ($rows as $row) {
            $candidate = SlugService::slugify((string)($row['title_en'] ?: ($row['title_ru'] ?: ('news-' . (int)$row['id']))), '');
            if ($candidate !== '' && $candidate === $generated) {
                return $row;
            }
        }

        return null;
    }

    private function newsAbsoluteImage(?string $src, Request $request): ?string
    {
        if (!$src || trim($src) === '') {
            return null;
        }
        $src = trim($src);
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }
        $parsed = parse_url($this->canonical($request));
        $base   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (!empty($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }
        return $base . '/' . ltrim($src, '/');
    }

    private function newsMetaImage(array $item, Request $request): ?string
    {
        $cover = $this->newsAbsoluteImage($item['cover_url'] ?? null, $request);
        if ($cover) {
            return $cover;
        }

        return $this->newsAbsoluteImage($item['image_url'] ?? null, $request);
    }

    private function categoryImage(array $category, Request $request): ?string
    {
        return $this->newsAbsoluteImage($category['image_url'] ?? null, $request);
    }

    private function defaultOgImage(Request $request): ?string
    {
        try {
            $settings = $this->container->get(SettingsService::class);
            $image = (string)$settings->get('og_image', '');
            if ($image === '') {
                $image = (string)$settings->get('theme_logo', '');
            }
            return $this->newsAbsoluteImage($image, $request);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function profileUrl($id, $username): string
    {
        $username = trim((string)$username);
        if ($username !== '') {
            return '/users/' . rawurlencode($username);
        }
        return '/users/' . (int)$id;
    }

    private function absolutePathCanonical(Request $request, string $path): string
    {
        $cfg  = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $request->server['HTTP_HOST'] ?? 'localhost';
            $base   = $scheme . '://' . $host;
        }
        return $base . $path;
    }

    private function renderComments(int $newsId): string
    {
        try {
            return $this->container->get(CommentService::class)->renderForEntity('news', $newsId);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
