<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;

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
        $html = $this->container->get('renderer')->render('tags/show', [
            'title' => $titleText,
            'articles' => $articles,
            'gallery' => $gallery,
            'locale' => $this->container->get('lang')->current(),
            'slug' => $slug,
            'tagName' => $tag['name'] ?? $slug,
            'openMode' => $this->container->get(\App\Services\SettingsService::class)->get('gallery_open_mode', 'lightbox'),
            'meta' => [
                'title' => $titleText,
                'canonical' => $canonical,
                'og' => ['title' => $titleText, 'url' => $canonical],
            ],
        ]);
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
        $html = $this->container->get('renderer')->render('articles/list', [
            'title' => 'Articles',
            'articles' => $articles,
            'locale' => $this->container->get('lang')->current(),
            'display' => $display,
            'breadcrumbs' => [
                ['label' => 'Articles'],
            ],
            'meta' => [
                'title' => 'Articles',
                'canonical' => $canonical,
                'og' => ['title' => 'Articles', 'url' => $canonical],
            ],
        ]);
        $cache->set('articles_list', $html, 300);
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("
            SELECT a.*, u.name AS author_name, u.avatar AS author_avatar
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
        $desc = substr(strip_tags((string)$article[$bodyKey]), 0, 150);
        $ogImage = $this->resolveOgImage($article);
        $display = $this->moduleSettings->all('articles');
        $display = array_merge([
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ], $display);
        $html = $this->container->get('renderer')->render('articles/item', [
            'title' => $article[$titleKey] ?? '',
            'article' => $article,
            'locale' => $locale,
            'tags' => $tags,
            'display' => $display,
            'breadcrumbs' => [
                ['label' => 'Articles', 'url' => '/articles'],
                ['label' => $article[$titleKey] ?? 'Article'],
            ],
            'meta' => [
                'title' => $article[$titleKey] ?? '',
                'description' => $desc,
                'canonical' => $canonical,
                'og' => [
                    'title' => $article[$titleKey] ?? '',
                    'description' => $desc,
                    'url' => $canonical,
                    'image' => $ogImage,
                ],
            ],
        ]);
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
        // Placeholder: could map to attachment or specific field later
        return null;
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
}
