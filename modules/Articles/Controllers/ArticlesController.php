<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
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
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'description_enabled' => true,
        ]);
    }

    public function byTag(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $tag = $this->db->fetch("SELECT id, name FROM tags WHERE slug = ?", [$slug]);
        if (!$tag) {
            return new Response('Not found', 404);
        }
        $articles = $this->db->fetchAll("
            SELECT a.slug, a.title_en, a.title_ru, a.created_at
            FROM articles a
            JOIN taggables tg ON tg.entity_id = a.id AND tg.entity_type = 'article'
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
            ORDER BY a.created_at DESC LIMIT 100
        ", [$slug]);
        $gallery = $this->db->fetchAll("
            SELECT gi.* FROM gallery_items gi
            JOIN taggables tg ON tg.entity_id = gi.id AND tg.entity_type IN ('gallery','gallery_item','image')
            JOIN tags t ON t.id = tg.tag_id
            WHERE t.slug = ?
            ORDER BY gi.id DESC LIMIT 100
        ", [$slug]);
        $canonical = $this->canonical($request);
        $titleText = 'Tag: ' . ($tag['name'] ?? $slug);
        $html = $this->container->get('renderer')->render(
            'tags/show',
            [
                '_layout' => true,
                'title' => $titleText,
                'articles' => $articles,
                'gallery' => $gallery,
                'locale' => $this->container->get('lang')->current(),
                'slug' => $slug,
                'tagName' => $tag['name'] ?? $slug,
                'openMode' => $this->container->get(\App\Services\SettingsService::class)->get('gallery_open_mode', 'lightbox'),
            ],
            [
                'title' => $titleText,
                'canonical' => $canonical,
                'description' => '',
                'image' => $this->defaultOgImage(),
            ]
        );
        return new Response($html);
    }

    public function index(Request $request): Response
    {
        $cache = $this->container->get('cache');
        $cached = $cache->get('articles_list');
        if ($cached) {
            return new Response($cached);
        }
        $select = "a.slug, a.title_en, a.title_ru, a.created_at";
        $join = '';
        if ($this->hasColumn('author_id')) {
            $select .= ", a.author_id, u.name as author_name, u.avatar as author_avatar, u.username as author_username, u.profile_visibility as author_profile_visibility";
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
        $hasCat = $this->hasColumn('category');
        if ($hasPreview) {
            $select .= ", preview_en, preview_ru";
        }
        if ($hasImg) {
            $select .= ", image_url";
        }
        if ($hasCat) {
            $select .= ", category";
        }
        $articles = $this->db->fetchAll("SELECT {$select} FROM articles a {$join} ORDER BY a.created_at DESC LIMIT 50");
        $display = $this->moduleSettings->all('articles');
        $display = array_merge([
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ], $display);
        $canonical = $this->canonical($request);
        $currentLocale = $this->container->get('lang')->current();
        $listTitle = $currentLocale === 'ru' ? 'Статьи' : 'Articles';
        $listDesc = $currentLocale === 'ru'
            ? 'Свежие материалы и новости.'
            : 'Latest articles and updates.';
        $html = $this->container->get('renderer')->render(
            'articles/list',
            [
                '_layout' => true,
                'title' => $listTitle,
                'description' => $listDesc,
                'articles' => $articles,
                'locale' => $currentLocale,
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
        $cache->set('articles_list', $html, 300);
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("
            SELECT a.*, u.name AS author_name, u.avatar AS author_avatar, u.username AS author_username, u.profile_visibility AS author_profile_visibility, u.signature AS author_signature
            FROM articles a
            LEFT JOIN users u ON u.id = a.author_id
            WHERE a.slug = ?
        ", [$slug]);
        if (!$article) {
            return new Response('Not found', 404);
        }
        $this->db->execute("UPDATE articles SET views = views + 1 WHERE id = ?", [$article['id']]);
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
                'breadcrumbs' => [
                    ['label' => 'Articles', 'url' => '/articles'],
                    ['label' => $article[$titleKey] ?? 'Article'],
                ],
            ],
            [
                'title' => $article[$titleKey] ?? '',
                'description' => $desc,
                'canonical' => $canonical,
                'image' => $ogImg,
            ]
        );
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
