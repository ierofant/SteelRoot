<?php
namespace Modules\Gallery\Controllers;

use App\Services\SecurityLog;
use App\Services\SlugService;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Core\Meta\JsonLdRenderer;
use Core\Meta\CommonSchemas;
use App\Services\SettingsService;
use Modules\Comments\Services\CommentService;
use Modules\Gallery\Services\GalleryCategoryService;
use Modules\Gallery\Services\MasterLikeService;
use Modules\Users\Services\Auth;
use Modules\Users\Services\CollectionService;

class GalleryController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;
    private GalleryCategoryService $galleryCategories;
    private MasterLikeService $masterLikes;

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
        $this->galleryCategories = new GalleryCategoryService($this->db, $container->get('cache'));
        $this->masterLikes = new MasterLikeService($container);
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
        $tagInput = trim((string)($request->query['tag'] ?? ''));
        $tag = $tagInput !== '' ? $this->resolveTagSlug($tagInput) : '';
        if ($tagInput !== '' && $tag !== '' && $tag !== $tagInput) {
            $query = $request->query;
            $query['tag'] = $tag;
            $location = '/gallery';
            if ($query !== []) {
                $location .= '?' . http_build_query($query);
            }

            return new Response('', 301, ['Location' => $location]);
        }
        $category = trim($request->query['cat'] ?? '');
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $payload = $this->loadPublicListPayload($page, $perPage, $tag, $category, $sort);
        $display = $payload['display'];
        $mode = $payload['open_mode'];
        $total = $payload['total'];
        $items = $this->decorateMasterLikeEligibility($payload['items']);
        $enabledCategories = $payload['enabled_categories'];
        $popularTags = $payload['popular_tags'];

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
        $galleryOgImage = $this->galleryRootMenuImage();
        if ($page > 1) {
            $seoListTitle .= $currentLocale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $jsonld = ($tag === '' && $category === '')
            ? $this->galleryIndexJsonLd($items, $canonical, $seoListTitle, $listDesc, $total, $page, $perPage, $currentLocale)
            : '';

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
                'masterLikeState'   => $this->masterLikes->viewerState(),
                'masterLikeToken'   => !empty($_SESSION['user_id']) ? Csrf::token('gallery_master_like') : '',
                'enabledCategories' => $enabledCategories,
                'popularTags'       => $popularTags,
                'breadcrumbs'       => [
                    ['label' => $listTitle],
                ],
            ],
            [
                'title' => $seoListTitle,
                'description' => $listDesc,
                'canonical' => $canonical,
                'image' => ($tag === '' && $category === '') ? $galleryOgImage : '/icon/tags_og.jpg',
                'jsonld' => $jsonld,
            ]
        );
        return new Response($html);
    }

    private function galleryIndexJsonLd(
        array $items,
        string $canonical,
        string $title,
        string $description,
        int $total,
        int $page,
        int $perPage,
        string $locale
    ): string {
        $siteUrl = $this->siteBaseUrl();
        $siteName = trim((string)$this->settings->get('site_name', 'TattooToday'));
        if ($siteName === '') {
            $siteName = 'TattooToday';
        }

        $logo = $this->absoluteUrl((string)$this->settings->get('theme_logo', ''));
        $website = CommonSchemas::webSite([
            'name' => $siteName,
            'url' => $siteUrl,
        ]);
        $organization = CommonSchemas::organization([
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $logo,
        ]);

        $itemListElements = [];
        foreach (array_values($items) as $index => $item) {
            $itemUrl = $this->galleryItemPublicUrl($item);
            $itemTitle = $this->galleryItemSeoTitle($item, $locale);
            $itemImage = trim((string)($item['image_url'] ?? $item['path_medium'] ?? $item['path_thumb'] ?? $item['path'] ?? ''));

            $listItem = [
                '@type' => 'ListItem',
                'position' => (($page - 1) * max(1, $perPage)) + $index + 1,
                'url' => $siteUrl . $itemUrl,
            ];

            $work = [
                '@type' => 'ImageObject',
                'url' => $this->absoluteUrl($itemImage),
            ];

            if ($itemTitle !== '') {
                $work['name'] = $itemTitle;
                $listItem['name'] = $itemTitle;
            }

            if (!empty($item['created_at'])) {
                $timestamp = strtotime((string)$item['created_at']);
                if ($timestamp !== false) {
                    $work['datePublished'] = date('c', $timestamp);
                }
            }

            $listItem['item'] = $work;
            $itemListElements[] = $listItem;
        }

        $itemListSchema = [
            '@type' => 'ItemList',
            'name' => $locale === 'ru' ? 'Фотографии галереи' : 'Gallery photos',
            'numberOfItems' => max($total, count($itemListElements)),
            'itemListElement' => $itemListElements,
        ];

        $collectionPage = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl,
            ],
            'about' => [
                '@type' => 'Thing',
                'name' => $locale === 'ru' ? 'Галерея татуировок' : 'Tattoo gallery',
            ],
            'mainEntity' => $itemListSchema,
        ];

        $defaultImage = $this->galleryRootMenuImage() ?: $this->defaultOgImage();
        if (!empty($defaultImage)) {
            $collectionPage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $this->absoluteUrl((string)$defaultImage),
            ];
        }

        $breadcrumbSchema = CommonSchemas::breadcrumbList([
            ['name' => $locale === 'ru' ? 'Галерея' : 'Gallery', 'url' => $siteUrl . '/gallery'],
        ]);

        return JsonLdRenderer::render(
            JsonLdRenderer::merge($website, $organization, $collectionPage, $breadcrumbSchema)
        );
    }

    private function loadPublicListPayload(int $page, int $perPage, string $tag, string $category, string $sort): array
    {
        $cache = $this->container->get('cache');
        $cacheAllowedForSort = !in_array($sort, ['likes', 'views', 'master_likes', 'comments'], true);
        $enabled = $cacheAllowedForSort && $this->settings->get('cache_gallery_public_list', '1') === '1';
        $ttl = max(1, (int)$this->settings->get('cache_gallery_public_list_ttl', '10')) * 60;
        $cacheKey = 'gallery_public_list_' . sha1(json_encode([
            'page' => $page,
            'per_page' => $perPage,
            'tag' => $tag,
            'category' => $category,
            'sort' => $sort,
            'locale' => $this->container->get('lang')->current(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($enabled) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                if (isset($cached['items']) && is_array($cached['items'])) {
                    $cached['items'] = $this->refreshItemCounters($cached['items']);
                }
                return $cached;
            }
        }

        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';

        if ($tag !== '') {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image') JOIN tags t ON t.id = tg.tag_id WHERE t.slug = ?{$this->approvedFilterSql('gi')}", [$tag]);
        } elseif ($category !== '' && $this->hasItemCategoriesTable()) {
            try {
                $totalRow = $this->db->fetch("
                    SELECT COUNT(DISTINCT gi.id) as cnt
                    FROM gallery_items gi
                    JOIN gallery_item_categories gic ON gic.item_id = gi.id
                    JOIN gallery_categories gc ON gc.id = gic.category_id
                    WHERE gc.slug = ?{$this->approvedFilterSql('gi')}
                ", [$category]);
            } catch (\Throwable $e) {
                $totalRow = ['cnt' => 0];
            }
        } elseif ($category !== '' && $this->hasColumn('category_id')) {
            try {
                $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi JOIN gallery_categories gc ON gc.id = gi.category_id AND gc.slug = ? WHERE 1=1{$this->approvedFilterSql('gi', true)}", [$category]);
            } catch (\Throwable $e) {
                $totalRow = ['cnt' => 0];
            }
        } elseif ($category !== '' && $this->hasColumn('category')) {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items WHERE category = ?{$this->approvedFilterSql('gallery_items')}", [$category]);
        } else {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items WHERE 1=1{$this->approvedFilterSql('gallery_items', true)}");
        }
        $total = (int)($totalRow['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $limitSql = (int)$perPage;
        $offsetSql = (int)$offset;
        $orderSql = $this->orderSql($sort, true);
        $items = $this->fetchItems($tag, $category, $orderSql, $limitSql, $offsetSql);
        $payload = [
            'display' => $display,
            'open_mode' => $mode,
            'total' => $total,
            'items' => $this->refreshItemCounters($items),
            'enabled_categories' => $this->galleryCategories->enabled(),
            'popular_tags' => !empty($display['show_tags']) ? $this->popularTags() : [],
        ];

        if ($enabled) {
            $cache->set($cacheKey, $payload, $ttl);
        }

        return $payload;
    }

    private function refreshItemCounters(array $items): array
    {
        if ($items === []) {
            return $items;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $items;
        }

        $select = 'id';
        if ($this->hasColumn('likes')) {
            $select .= ', likes';
        }
        if ($this->hasColumn('views')) {
            $select .= ', views';
        }
        if ($this->hasColumn('master_likes_count')) {
            $select .= ', master_likes_count';
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            $rows = $this->db->fetchAll(
                "SELECT {$select} FROM gallery_items WHERE id IN ({$placeholders})",
                $ids
            );
        } catch (\Throwable $e) {
            return $items;
        }

        $statsById = [];
        foreach ($rows as $row) {
            $statsById[(int)($row['id'] ?? 0)] = $row;
        }

        foreach ($items as $index => $item) {
            $id = (int)($item['id'] ?? 0);
            if ($id <= 0 || !isset($statsById[$id])) {
                continue;
            }

            $stats = $statsById[$id];
            if (array_key_exists('likes', $stats)) {
                $items[$index]['likes'] = (int)$stats['likes'];
            }
            if (array_key_exists('views', $stats)) {
                $items[$index]['views'] = (int)$stats['views'];
            }
            if (array_key_exists('master_likes_count', $stats)) {
                $items[$index]['master_likes_count'] = (int)$stats['master_likes_count'];
            }
        }

        return $items;
    }

    public function view(Request $request): Response
    {
        $id = (int)($request->query['id'] ?? 0);
        $slugParam = isset($request->params['slug']) ? (string)$request->params['slug'] : null;
        $authorJoin = '';
        $authorSelect = '';
        if ($this->hasColumn('author_id')) {
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature";
        }
        if ($slugParam) {
            $item = $this->resolveItemBySlug($slugParam, $authorSelect, $authorJoin);
        } else {
            $item = $this->db->fetch("
                SELECT gi.*{$authorSelect}
                FROM gallery_items gi
                {$authorJoin}
                WHERE gi.id = ?{$this->approvedFilterSql('gi')}
            ", [$id]);
        }
        if (!$item) {
            return new Response('Not found', 404);
        }
        if ($slugParam && !empty($item['slug']) && $slugParam !== (string)$item['slug']) {
            return new Response('', 301, ['Location' => '/gallery/photo/' . rawurlencode((string)$item['slug'])]);
        }
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';
        if (($mode === 'page') && empty($slugParam) && !empty($item['slug'] ?? '')) {
            return new Response('', 302, ['Location' => '/gallery/photo/' . urlencode($item['slug'])]);
        }
        $this->db->execute("UPDATE gallery_items SET views = views + 1, updated_at = updated_at WHERE id = ?", [$item['id']]);
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
        $listTitle = $this->container->get('lang')->current() === 'ru' ? 'Галерея' : 'Gallery';
        $fallbackOg = $this->defaultOgImage();
        $ogToUse = $ogImage ?: $fallbackOg;
        $authorVisibility = trim((string)($item['author_profile_visibility'] ?? 'public'));
        if ($authorVisibility === '') {
            $authorVisibility = 'public';
        }
        $authorPrivate = $authorVisibility === 'private';
        $authorBypass = $this->canBypassPrivateProfile((int)($item['author_id'] ?? 0));
        $showSignature = !$authorPrivate || $authorBypass;
        $authorProfileUrl = $this->profileUrl($item['author_id'] ?? null, $item['author_username'] ?? null);
        $masterLikeState = $this->masterLikes->viewerState($item);
        $viewer = $this->container->get(Auth::class)->user();
        $collections = $this->container->get(CollectionService::class);
        $shareTargets = $this->buildShareTargets($item);
        $primaryCategory = $this->resolvePrimaryCategoryForItem($item);
        $categoryBreadcrumbs = $this->categoryBreadcrumbs($primaryCategory, $this->container->get('lang')->current());
        $pageBreadcrumbs = array_merge(
            [['label' => $listTitle, 'url' => '/gallery']],
            $categoryBreadcrumbs,
            [['label' => $itemTitle ?? 'Image']]
        );

        // ── JSON-LD ───────────────────────────────────────────────────────────
        $siteBase  = $this->siteBaseUrl();
        $imageSchema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'ImageObject',
            'name'          => $itemTitle,
            'contentUrl'    => $this->absoluteUrl($ogImage),
            'url'           => $canonical,
            'datePublished' => !empty($item['created_at'])
                ? date('c', strtotime((string)$item['created_at']))
                : null,
        ];
        if ($itemDesc !== '') {
            $imageSchema['description'] = $itemDesc;
        }
        if (!empty($item['author_name'])) {
            $authorEntry = [
                '@type' => 'Person',
                'name'  => $item['author_name'],
                'url'   => $siteBase . $this->profileUrl($item['author_id'] ?? null, $item['author_username'] ?? null),
            ];
            if (!empty($item['author_avatar'])) {
                $authorEntry['image'] = $this->absoluteUrl((string)$item['author_avatar']);
            }
            $imageSchema['author']  = $authorEntry;
            $imageSchema['creator'] = $authorEntry;
        }
        $breadcrumbItems = [['name' => $listTitle, 'url' => $siteBase . '/gallery']];
        foreach ($categoryBreadcrumbs as $crumb) {
            $breadcrumbItems[] = [
                'name' => (string)($crumb['label'] ?? ''),
                'url' => $siteBase . (string)($crumb['url'] ?? '/gallery'),
            ];
        }
        $ownerBreadcrumb = $this->ownerBreadcrumb($item, $siteBase);
        if ($ownerBreadcrumb !== null) {
            $breadcrumbItems[] = $ownerBreadcrumb;
        }
        $breadcrumbItems[] = ['name' => $itemTitle];
        $breadcrumbSchema = CommonSchemas::breadcrumbList($breadcrumbItems);
        $jsonld = JsonLdRenderer::render(
            JsonLdRenderer::merge($imageSchema, $breadcrumbSchema)
        );
        // ─────────────────────────────────────────────────────────────────────

        $html = $this->container->get('renderer')->render(
            'gallery/item',
            [
                '_layout' => true,
                'title' => $itemTitle,
                'item' => $item,
                'locale' => $this->container->get('lang')->current(),
                'display' => $display,
                'tags' => $tags,
                'commentsHtml' => $this->renderComments((int)$item['id']),
                'authorProfileUrl' => $authorProfileUrl,
                'authorSignature' => $showSignature ? ($item['author_signature'] ?? '') : '',
                'authorSignatureVisible' => $showSignature && !empty($item['author_signature']),
                'masterLikeState' => $masterLikeState,
                'masterLikeToken' => !empty($_SESSION['user_id']) ? Csrf::token('gallery_master_like') : '',
                'collectionToken' => $viewer ? Csrf::token('users_collections') : '',
                'collectionsAvailable' => $collections->available(),
                'collectionSaved' => $viewer ? $collections->isSaved((int)$viewer['id'], 'gallery', (int)$item['id']) : false,
                'viewer' => $viewer,
                'shareTargets' => $shareTargets,
                'shareCopyUrl' => $canonical,
                'message' => $request->query['msg'] ?? null,
                'error' => $request->query['err'] ?? null,
                'breadcrumbs' => $pageBreadcrumbs,
            ],
            [
                'title'       => $itemTitle,
                'description' => $itemDesc,
                'canonical'   => $canonical,
                'image'       => $ogToUse,
                'jsonld'      => $jsonld,
            ]
        );
        return new Response($html);
    }

    public function share(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $platform = strtolower(trim((string)($request->params['platform'] ?? '')));
        if ($id < 1 || $platform === '') {
            SecurityLog::log('gallery.share_invalid', ['item_id' => $id, 'platform' => $platform, 'reason' => 'missing_params']);
            return new Response('Not found', 404);
        }

        $item = $this->db->fetch("
            SELECT id, slug, title_ru, title_en
            FROM gallery_items
            WHERE id = ?{$this->approvedFilterSql('gallery_items')}
            LIMIT 1
        ", [$id]);
        if (!$item) {
            SecurityLog::log('gallery.share_invalid', ['item_id' => $id, 'platform' => $platform, 'reason' => 'item_not_found']);
            return new Response('Not found', 404);
        }

        $redirectUrl = $this->shareRedirectUrl($platform, $item);
        if ($redirectUrl === null) {
            SecurityLog::log('gallery.share_invalid', ['item_id' => $id, 'platform' => $platform, 'reason' => 'platform_rejected']);
            return new Response('Not found', 404);
        }

        return new Response('', 302, ['Location' => $redirectUrl]);
    }

    private function siteBaseUrl(): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        return rtrim($cfg['url'] ?? '', '/');
    }

    private function absoluteUrl(string $path): string
    {
        if ($path === '' || str_starts_with($path, 'http')) {
            return $path;
        }
        return $this->siteBaseUrl() . (str_starts_with($path, '/') ? '' : '/') . $path;
    }

    private function buildShareTargets(array $item): array
    {
        $labels = [
            'telegram' => __('gallery.share.telegram'),
            'whatsapp' => __('gallery.share.whatsapp'),
            'vk' => __('gallery.share.vk'),
        ];
        $targets = [];

        foreach ($this->allowedSharePlatforms() as $platform) {
            if (!isset($labels[$platform])) {
                continue;
            }
            $targets[] = [
                'platform' => $platform,
                'label' => (string)$labels[$platform],
                'href' => '/gallery/share/' . (int)($item['id'] ?? 0) . '/' . rawurlencode($platform),
            ];
        }

        return $targets;
    }

    private function allowedSharePlatforms(): array
    {
        $settings = $this->settings->all();
        $raw = trim((string)($settings['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp'));
        $platforms = array_values(array_unique(array_filter(array_map(static function (string $value): string {
            return strtolower(trim($value));
        }, explode(',', $raw)))));
        $supported = ['telegram', 'whatsapp', 'vk'];

        return array_values(array_intersect($supported, $platforms));
    }

    private function shareRedirectUrl(string $platform, array $item): ?string
    {
        if (!in_array($platform, $this->allowedSharePlatforms(), true)) {
            return null;
        }

        $sharePath = !empty($item['slug'])
            ? '/gallery/photo/' . rawurlencode((string)$item['slug'])
            : '/gallery/view?id=' . (int)($item['id'] ?? 0);
        $shareUrl = $this->siteBaseUrl() . $sharePath;
        $titleKey = $this->container->get('lang')->current() === 'ru' ? 'title_ru' : 'title_en';
        $fallbackKey = $titleKey === 'title_ru' ? 'title_en' : 'title_ru';
        $title = trim((string)($item[$titleKey] ?? ''));
        if ($title === '') {
            $title = trim((string)($item[$fallbackKey] ?? ''));
        }
        if ($title === '') {
            $title = (string)__('gallery.title');
        }

        return match ($platform) {
            'telegram' => 'https://t.me/share/url?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($title),
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode($title . ' ' . $shareUrl),
            'vk' => 'https://vk.com/share.php?url=' . rawurlencode($shareUrl) . '&title=' . rawurlencode($title),
            default => null,
        };
    }

    private function resolvePrimaryCategoryForItem(array $item): ?array
    {
        if ($this->hasItemCategoriesTable()) {
            $row = $this->db->fetch(
                "SELECT gc.*
                 FROM gallery_categories gc
                 JOIN gallery_item_categories gic ON gic.category_id = gc.id
                 WHERE gic.item_id = ? AND gc.enabled = 1
                 ORDER BY gc.position ASC, gc.id ASC
                 LIMIT 1",
                [(int)($item['id'] ?? 0)]
            );
            if ($row) {
                return $row;
            }
        }

        $categoryId = (int)($item['category_id'] ?? 0);
        if ($categoryId > 0) {
            $row = $this->galleryCategories->find($categoryId);
            if ($row && !empty($row['enabled'])) {
                return $row;
            }
        }

        $categorySlug = trim((string)($item['category'] ?? ''));
        if ($categorySlug !== '') {
            $row = $this->galleryCategories->findBySlug($categorySlug);
            if ($row && !empty($row['enabled'])) {
                return $row;
            }
        }

        return null;
    }

    private function categoryBreadcrumbs(?array $category, string $locale): array
    {
        if (!$category) {
            return [];
        }

        $trail = [$category];
        if ($this->hasCategoryParentColumn()) {
            $seen = [(int)($category['id'] ?? 0) => true];
            $parentId = (int)($category['parent_id'] ?? 0);
            while ($parentId > 0 && !isset($seen[$parentId])) {
                $parent = $this->galleryCategories->find($parentId);
                if (!$parent || empty($parent['enabled'])) {
                    break;
                }
                $trail[] = $parent;
                $seen[$parentId] = true;
                $parentId = (int)($parent['parent_id'] ?? 0);
            }
        }

        $trail = array_reverse($trail);
        $items = [];
        foreach ($trail as $row) {
            $label = $locale === 'ru'
                ? ((string)($row['name_ru'] ?: $row['name_en']))
                : ((string)($row['name_en'] ?: $row['name_ru']));
            $slug = trim((string)($row['slug'] ?? ''));
            if ($label === '' || $slug === '') {
                continue;
            }
            $items[] = [
                'label' => $label,
                'url' => '/gallery/category/' . rawurlencode($slug),
            ];
        }

        return $items;
    }

    private function ownerBreadcrumb(array $item, string $siteBase): ?array
    {
        if (!empty($item['submitted_by_master']) && !empty($item['author_name'])) {
            return [
                'name' => (string)$item['author_name'],
                'url' => $siteBase . $this->profileUrl($item['author_id'] ?? null, $item['author_username'] ?? null),
            ];
        }

        return [
            'name' => 'TattooRoot',
            'url' => $siteBase !== '' ? $siteBase . '/' : '/',
        ];
    }

    private function resolveItemBySlug(string $rawSlug, string $authorSelect, string $authorJoin): ?array
    {
        $sql = "
            SELECT gi.*{$authorSelect}
            FROM gallery_items gi
            {$authorJoin}
            WHERE gi.slug = ?{$this->approvedFilterSql('gi')}
            LIMIT 1
        ";

        foreach (SlugService::candidates($rawSlug) as $candidate) {
            $item = $this->db->fetch($sql, [$candidate]);
            if ($item) {
                return $item;
            }
        }

        $generated = SlugService::slugify(rawurldecode($rawSlug), '');
        if ($generated === '') {
            return null;
        }

        $rows = $this->db->fetchAll("
            SELECT gi.*{$authorSelect}
            FROM gallery_items gi
            {$authorJoin}
            WHERE (gi.slug IS NULL OR gi.slug = ''){$this->approvedFilterSql('gi')}
            ORDER BY gi.id DESC
            LIMIT 200
        ");

        foreach ($rows as $row) {
            $candidate = SlugService::slugify((string)($row['title_en'] ?: ($row['title_ru'] ?: ('photo-' . (int)$row['id']))), '');
            if ($candidate !== '' && $candidate === $generated) {
                return $row;
            }
        }

        return null;
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
        $items = $this->decorateMasterLikeEligibility($items);
        $locale = $this->container->get('lang')->current();
        $titleKey = $locale === 'ru' ? 'name_ru' : 'name_en';
        $catTitle = $category[$titleKey] ?: ($category['name_en'] ?: $category['name_ru']);
        $canonical = $this->canonical($request);
        $seoTitlePrimary = trim((string)($locale === 'ru'
            ? ($category['meta_title_ru'] ?? '')
            : ($category['meta_title_en'] ?? '')));
        $seoTitleFallback = trim((string)($locale === 'ru'
            ? ($category['meta_title_en'] ?? '')
            : ($category['meta_title_ru'] ?? '')));
        $seoDescPrimary = trim((string)($locale === 'ru'
            ? ($category['meta_description_ru'] ?? '')
            : ($category['meta_description_en'] ?? '')));
        $seoDescFallback = trim((string)($locale === 'ru'
            ? ($category['meta_description_en'] ?? '')
            : ($category['meta_description_ru'] ?? '')));

        $seoTitle = $seoTitlePrimary !== ''
            ? $seoTitlePrimary
            : ($seoTitleFallback !== '' ? $seoTitleFallback : ($locale === 'ru' ? ($catTitle . ' — галерея') : ($catTitle . ' — gallery')));
        if ($page > 1) {
            $seoTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $seoDesc = $seoDescPrimary !== ''
            ? $seoDescPrimary
            : ($seoDescFallback !== ''
                ? $seoDescFallback
                : ($locale === 'ru'
                    ? ('Фотографии в категории «' . $catTitle . '». Подборка работ и изображений.')
                    : ('Photos in "' . $catTitle . '" category. A curated gallery of works.')));
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
                'masterLikeState'   => $this->masterLikes->viewerState(),
                'masterLikeToken'   => !empty($_SESSION['user_id']) ? Csrf::token('gallery_master_like') : '',
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
        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) as cnt
             FROM gallery_items gi
             JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
             JOIN tags t ON t.id = tg.tag_id
             WHERE t.slug = ?{$this->approvedFilterSql('gi')}",
            [$slug]
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        $items = $this->decorateMasterLikeEligibility($this->refreshItemCounters($this->fetchItems($slug, '', $orderSql, $limitSql, $offsetSql)));
        $canonical = $this->canonical($request);
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';
        $locale = $this->container->get('lang')->current();
        $tagDisplay = $this->resolveTagDisplayName($slug);
        $tagTitle = $locale === 'ru' ? ('Тег галереи: ' . $tagDisplay) : ('Gallery tag: ' . $tagDisplay);
        if ($page > 1) {
            $tagTitle .= $locale === 'ru' ? (' | Страница ' . $page) : (' | Page ' . $page);
        }
        $tagDesc = $locale === 'ru'
            ? ('Изображения и работы по тегу «' . $tagDisplay . '».')
            : ('Images and works for tag "' . $tagDisplay . '".');
        $siteBase = $this->siteBaseUrl();
        $jsonld = JsonLdRenderer::render(
            CommonSchemas::breadcrumbList([
                ['name' => $locale === 'ru' ? 'Галерея' : 'Gallery', 'url' => $siteBase . '/gallery'],
                ['name' => $tagDisplay, 'url' => $siteBase . '/tags/' . rawurlencode($slug) . '/gallery'],
            ])
        );
        $tagImage = $this->resolveTagOgImage($items, $slug, $orderSql);
        $html = $this->container->get('renderer')->render(
            'gallery/list',
            [
                '_layout' => true,
                'title' => $tagTitle,
                'items' => $items,
                'page' => $page,
                'total' => $total,
                'perPage' => $perPage,
                'locale' => $locale,
                'tag' => $slug,
                'tagDisplayName' => $tagDisplay,
                'sort' => $sort,
                'openMode' => $mode,
                'display' => $display,
                'masterLikeState' => $this->masterLikes->viewerState(),
                'masterLikeToken' => !empty($_SESSION['user_id']) ? Csrf::token('gallery_master_like') : '',
                'popularTags' => !empty($display['show_tags']) ? $this->popularTags() : [],
                'enableLoadMore' => true,
                'loadMoreApi' => '/api/v1/tags/' . rawurlencode($slug) . '/gallery',
            ],
            [
                'title' => $tagTitle,
                'description' => $tagDesc,
                'canonical' => $canonical,
                'image' => $tagImage,
                'jsonld' => $jsonld,
            ]
        );
        return new Response($html);
    }

    private function resolveTagOgImage(array $items, string $slug, string $orderSql): string
    {
        foreach ($items as $item) {
            $image = trim((string)($item['image_url'] ?? ''));
            if ($image !== '') {
                return $image;
            }
        }

        try {
            $row = $this->db->fetch(
                "SELECT gi.image_url
                 FROM gallery_items gi
                 JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
                 JOIN tags t ON t.id = tg.tag_id
                 WHERE t.slug = :slug{$this->approvedFilterSql('gi')}
                   AND COALESCE(gi.image_url, '') <> ''
                 ORDER BY {$orderSql}
                 LIMIT 1",
                [':slug' => $slug]
            );
            $image = trim((string)($row['image_url'] ?? ''));
            if ($image !== '') {
                return $image;
            }
        } catch (\Throwable $e) {
        }

        return '/icon/tags_og.jpg';
    }

    public function tagApi(Request $request): Response
    {
        $slug = $this->resolveTagSlug((string)($request->params['slug'] ?? ''));
        if ($slug === '') {
            return Response::json(['error' => 'not_found'], 404);
        }

        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = max(1, (int)($this->moduleSettings->get('gallery', 'per_page') ?? 9));
        $sort = $this->sanitizeSort((string)($request->query['sort'] ?? 'new'));
        $orderSql = $this->orderSql($sort, true);
        $total = $this->countTagItems($slug);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $items = $this->decorateMasterLikeEligibility($this->refreshItemCounters($this->fetchItems($slug, '', $orderSql, $perPage, $offset)));
        $locale = $this->container->get('lang')->current();

        return Response::json([
            'items' => array_map(fn (array $item): array => $this->formatTagApiItem($item, $locale), $items),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page < $pages,
                'next_page' => $page < $pages ? ($page + 1) : null,
            ],
        ]);
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
            $tag = $this->db->fetch(
                "SELECT slug
                 FROM tags
                 WHERE slug = ? OR name = ?
                 ORDER BY CASE WHEN slug = ? THEN 0 ELSE 1 END, id ASC
                 LIMIT 1",
                [$candidate, $candidate, $candidate]
            );
            if ($tag && !empty($tag['slug'])) {
                return (string)$tag['slug'];
            }
        }

        return '';
    }

    private function countTagItems(string $slug): int
    {
        $row = $this->db->fetch(
            "SELECT COUNT(*) as cnt
             FROM gallery_items gi
             JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
             JOIN tags t ON t.id = tg.tag_id
             WHERE t.slug = ?{$this->approvedFilterSql('gi')}",
            [$slug]
        );

        return (int)($row['cnt'] ?? 0);
    }

    private function resolveTagDisplayName(string $slug): string
    {
        $row = $this->db->fetch(
            "SELECT name, slug
             FROM tags
             WHERE slug = ?
             LIMIT 1",
            [$slug]
        );

        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            return ltrim($name, "# \t\n\r\0\x0B");
        }

        return $slug;
    }

    private function formatTagApiItem(array $item, string $locale): array
    {
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $title = trim((string)($item[$titleKey] ?? ''));
        if ($title === '') {
            $title = trim((string)($item['title_en'] ?? $item['title_ru'] ?? ''));
        }

        $slug = trim((string)($item['slug'] ?? ''));
        $id = (int)($item['id'] ?? 0);

        return [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'thumb' => (string)($item['path_thumb'] ?? $item['path'] ?? ''),
            'full' => (string)($item['path_medium'] ?? $item['path'] ?? ''),
            'views' => (int)($item['views'] ?? 0),
            'likes' => (int)($item['likes'] ?? 0),
            'master_likes' => (int)($item['master_likes_count'] ?? 0),
            'can_master_like' => !empty($item['can_receive_master_like']),
            'submitted_by_master' => !empty($item['submitted_by_master']),
            'author_name' => (string)($item['author_name'] ?? ''),
            'author_avatar' => (string)($item['author_avatar'] ?? ''),
            'href' => $slug !== '' ? '/gallery/photo/' . rawurlencode($slug) : '/gallery/view?id=' . $id,
        ];
    }

    private function galleryItemPublicUrl(array $item): string
    {
        $slug = trim((string)($item['slug'] ?? ''));
        $id = (int)($item['id'] ?? 0);

        if ($slug !== '') {
            return '/gallery/photo/' . rawurlencode($slug);
        }

        return '/gallery/view?id=' . $id;
    }

    private function galleryItemSeoTitle(array $item, string $locale): string
    {
        $primaryKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $fallbackKey = $locale === 'ru' ? 'title_en' : 'title_ru';
        $title = trim((string)($item[$primaryKey] ?? ''));

        if ($title !== '') {
            return $title;
        }

        $title = trim((string)($item[$fallbackKey] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $id = (int)($item['id'] ?? 0);
        return $locale === 'ru' ? ('Фото #' . $id) : ('Photo #' . $id);
    }

    private function sanitizeSort(string $sort): string
    {
        $allowed = ['new', 'likes', 'views', 'master_likes', 'comments'];
        return in_array($sort, $allowed, true) ? $sort : 'new';
    }

    private function fetchItems(string $tag, string $category, string $orderSql, int $limitSql, int $offsetSql): array
    {
        $authorSelect = '';
        $authorJoin = '';
        if ($this->hasColumn('author_id')) {
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature, gi.author_id";
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
        }
        try {
            if ($tag !== '') {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
                    JOIN tags t ON t.id = tg.tag_id
                    {$authorJoin}
                    WHERE t.slug = :slug{$this->approvedFilterSql('gi')}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':slug' => $tag]);
            }
            if ($category !== '' && $this->hasItemCategoriesTable()) {
                return $this->db->fetchAll("
                    SELECT DISTINCT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN gallery_item_categories gic ON gic.item_id = gi.id
                    JOIN gallery_categories gc ON gc.id = gic.category_id AND gc.slug = :cat
                    {$authorJoin}
                    WHERE 1=1{$this->approvedFilterSql('gi', true)}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            if ($category !== '' && $this->hasColumn('category_id')) {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN gallery_categories gc ON gc.id = gi.category_id AND gc.slug = :cat
                    {$authorJoin}
                    WHERE 1=1{$this->approvedFilterSql('gi', true)}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            if ($category !== '' && $this->hasColumn('category')) {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    {$authorJoin}
                    WHERE gi.category = :cat{$this->approvedFilterSql('gi')}
                    ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':cat' => $category]);
            }
            return $this->db->fetchAll("SELECT gi.*{$authorSelect} FROM gallery_items gi {$authorJoin} WHERE 1=1{$this->approvedFilterSql('gi', true)} ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}");
        } catch (\Throwable $e) {
            // Fallback to id-based ordering if custom columns missing
            $fallbackOrder = "id DESC";
            if ($tag !== '') {
                return $this->db->fetchAll("
                    SELECT gi.*{$authorSelect} FROM gallery_items gi
                    JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
                    JOIN tags t ON t.id = tg.tag_id
                    {$authorJoin}
                    WHERE t.slug = :slug{$this->approvedFilterSql('gi')}
                    ORDER BY {$fallbackOrder} LIMIT {$limitSql} OFFSET {$offsetSql}
                ", [':slug' => $tag]);
            }
            return $this->db->fetchAll("SELECT gi.*{$authorSelect} FROM gallery_items gi {$authorJoin} WHERE 1=1{$this->approvedFilterSql('gi', true)} ORDER BY {$fallbackOrder} LIMIT {$limitSql} OFFSET {$offsetSql}");
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
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature, gi.author_id";
            $authorJoin = "LEFT JOIN users u ON u.id = gi.author_id";
        }

        $in = implode(',', array_fill(0, count($categoryIds), '?'));
        if ($this->hasItemCategoriesTable()) {
            return $this->db->fetchAll(
                "SELECT DISTINCT gi.*{$authorSelect}
                 FROM gallery_items gi
                 JOIN gallery_item_categories gic ON gic.item_id = gi.id
                 {$authorJoin}
                 WHERE gic.category_id IN ({$in}){$this->approvedFilterSql('gi')}
                 ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}",
                $categoryIds
            );
        }
        return $this->db->fetchAll(
            "SELECT gi.*{$authorSelect}
             FROM gallery_items gi
             {$authorJoin}
             WHERE gi.category_id IN ({$in}){$this->approvedFilterSql('gi')}
             ORDER BY {$orderSql} LIMIT {$limitSql} OFFSET {$offsetSql}",
            $categoryIds
        );
    }

    private function decorateMasterLikeEligibility(array $items): array
    {
        foreach ($items as $index => $item) {
            $items[$index]['can_receive_master_like'] = $this->masterLikes->itemCanReceiveMasterLike($item);
        }

        return $items;
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
                     WHERE gic.category_id IN ({$in}){$this->approvedFilterSql('gi')}",
                    $categoryIds
                );
                return (int)($row['cnt'] ?? 0);
            }
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS cnt FROM gallery_items WHERE category_id IN ({$in}){$this->approvedFilterSql('gallery_items')}",
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
        $hasMasterLikes = $this->hasColumn('master_likes_count');
        $hasViews = $this->hasColumn('views');
        $hasCreated = $this->hasColumn('created_at');
        $prefix = $withAlias ? 'gi.' : '';
        if ($sort === 'master_likes' && $hasMasterLikes) {
            return "{$prefix}master_likes_count DESC, {$prefix}id DESC";
        }
        if ($sort === 'likes' && $hasLikes) {
            return "{$prefix}likes DESC, {$prefix}id DESC";
        }
        if ($sort === 'views' && $hasViews) {
            return "{$prefix}views DESC, {$prefix}id DESC";
        }
        if ($sort === 'comments' && $this->tableExists('comments')) {
            return "(SELECT COUNT(*) FROM comments c WHERE c.entity_type = 'gallery' AND c.entity_id = {$prefix}id AND c.status = 'approved') DESC, {$prefix}id DESC";
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

    private function tableExists(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            try {
                $cache[$name] = (bool)$this->db->fetch("SHOW TABLES LIKE ?", [$name]);
            } catch (\Throwable $e) {
                $cache[$name] = false;
            }
        }
        return $cache[$name];
    }

    private function approvedFilterSql(string $alias, bool $withLeadingAnd = false): string
    {
        if (!$this->hasColumn('status')) {
            return '';
        }
        return ($withLeadingAnd ? ' AND ' : ' AND ') . $alias . ".status = 'approved'";
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

    private function galleryRootMenuImage(): ?string
    {
        try {
            $row = $this->db->fetch(
                "SELECT image_url
                 FROM settings_menu
                 WHERE url = ?
                   AND COALESCE(image_url, '') <> ''
                 ORDER BY enabled DESC, id ASC
                 LIMIT 1",
                ['/gallery']
            );
        } catch (\Throwable $e) {
            return null;
        }

        $image = trim((string)($row['image_url'] ?? ''));
        return $image !== '' ? $image : null;
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
                 ORDER BY uses DESC, t.name ASC
                 LIMIT 100"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function renderComments(int $galleryId): string
    {
        try {
            return $this->container->get(CommentService::class)->renderForEntity('gallery', $galleryId);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
