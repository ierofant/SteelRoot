<?php
namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use App\Services\SettingsService;
use Modules\Users\Services\Auth;

class GalleryController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;

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
        ]);
        $this->uploadPath = APP_ROOT . '/storage/uploads/gallery';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 9;
        $tag = trim($request->query['tag'] ?? '');
        $category = trim($request->query['cat'] ?? '');
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';

        if ($tag !== '') {
            $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image') JOIN tags t ON t.id = tg.tag_id WHERE t.slug = ?", [$tag]);
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
        $listTitle = $this->container->get('lang')->current() === 'ru' ? 'Галерея' : 'Gallery';
        $listDesc = $this->container->get('lang')->current() === 'ru'
            ? 'Подборка изображений и альбомов.'
            : 'Collection of images and albums.';
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
                'tag' => $tag,
                'sort' => $sort,
                'category' => $category,
                'openMode' => $mode,
                'display' => $display,
                'breadcrumbs' => [
                    ['label' => $listTitle],
                ],
            ],
            [
                'title' => $listTitle,
                'description' => $listDesc,
                'canonical' => $canonical,
                'image' => $this->defaultOgImage(),
            ]
        );
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
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature";
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

    public function byTag(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 9;
        $offset = ($page - 1) * $perPage;
        $limitSql = (int)$perPage;
        $offsetSql = (int)$offset;
        $sort = $this->sanitizeSort($request->query['sort'] ?? 'new');
        $orderSql = $this->orderSql($sort, true);
        $items = $this->fetchItems($slug, '', $orderSql, $limitSql, $offsetSql);
        $canonical = $this->canonical($request);
        $display = $this->displaySettings();
        $mode = !empty($display['enable_lightbox']) ? $this->settings->get('gallery_open_mode', 'lightbox') : 'page';
        $html = $this->container->get('renderer')->render(
            'gallery/list',
            [
                '_layout' => true,
                'title' => 'Gallery tag: ' . $slug,
                'items' => $items,
                'page' => $page,
                'total' => count($items),
                'perPage' => $perPage,
                'locale' => $this->container->get('lang')->current(),
                'tag' => $slug,
                'sort' => $sort,
                'openMode' => $mode,
                'display' => $display,
            ],
            [
                'title' => 'Gallery tag: ' . $slug,
                'description' => '',
                'canonical' => $canonical,
                'image' => $this->defaultOgImage(),
            ]
        );
        return new Response($html);
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
            $authorSelect = ", u.name AS author_name, u.avatar AS author_avatar, gi.author_id, u.username AS author_username";
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
}
