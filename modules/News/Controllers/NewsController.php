<?php
namespace Modules\News\Controllers;

use App\Services\SettingsService;
use Core\Request;
use Core\Response;
use Modules\ContentBase\Controllers\BasePublicController;

class NewsController extends BasePublicController
{
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

        $total  = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()}")['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table()} a {$join}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );

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
            "SELECT COUNT(*) as cnt FROM {$this->table()} WHERE category_id = ?",
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
            'robots'    => $isPrimaryNewsCategory ? 'noindex,follow' : null,
        ]);
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        if (!$item) {
            return new Response('Not found', 404);
        }

        $locale = $this->container->get('lang')->current();
        $lang   = $locale === 'ru' ? 'ru' : 'en';
        $cacheSettings = $this->container->get(SettingsService::class);
        $cacheEnabled  = $cacheSettings->get('cache_news', '0') === '1';
        $cacheTtl      = max(1, (int)$cacheSettings->get('cache_news_ttl', '60')) * 60;
        $cacheKey      = 'news_' . $slug . '_' . $lang;
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        if ($cacheEnabled && ($cached = $cache->get($cacheKey))) {
            return new Response($cached);
        }

        $this->db->execute("UPDATE {$this->table()} SET views = views + 1 WHERE id = ?", [$item['id']]);
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $bodyKey  = $locale === 'ru' ? 'body_ru'  : 'body_en';
        $display  = $this->mergeDisplay($this->moduleSettings->all($this->moduleKey()));

        $tags = [];
        try {
            $tags = $this->db->fetchAll(
                "SELECT t.name, t.slug FROM taggables tg JOIN tags t ON t.id = tg.tag_id
                 WHERE tg.entity_type = ? AND tg.entity_id = ?",
                [$this->entityType(), (int)$item['id']]
            );
        } catch (\Throwable $e) {}

        $html = $this->container->get('renderer')->render('articles/item', [
            '_layout'    => true,
            'title'      => $item[$titleKey] ?? '',
            'article'    => $item,
            'locale'     => $locale,
            'tags'       => $tags,
            'display'    => $display,
            'breadcrumbs' => [
                ['label' => $this->listTitle($locale), 'url' => $this->publicBase()],
                ['label' => $item[$titleKey] ?? ''],
            ],
        ], [
            'title'       => $item[$titleKey] ?? '',
            'description' => substr(strip_tags((string)($item[$bodyKey] ?? '')), 0, 160),
            'canonical'   => $this->canonical($request),
            'image'       => $item['image_url'] ?? null,
        ]);

        if ($cacheEnabled) {
            $cache->set($cacheKey, $html, $cacheTtl);
        }

        return new Response($html);
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
}
