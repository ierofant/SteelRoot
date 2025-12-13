<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use Core\ModuleSettings;
use App\Services\TagService;
use App\Services\SettingsService;

class AdminArticlesController
{
    private Container $container;
    private Database $db;
    private TagService $tags;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;
    private string $uploadPath;
    private array $columnCache = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
        $this->settings = $container->get(SettingsService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->uploadPath = APP_ROOT . '/storage/uploads/articles';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
        $this->moduleSettings->loadDefaults('articles', [
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ]);
    }

    public function index(Request $request): Response
    {
        $articles = $this->db->fetchAll("SELECT * FROM articles ORDER BY created_at DESC");
        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Manage Articles',
            'articles' => $articles,
            'csrf' => Csrf::token('articles_form'),
            'mode' => 'list',
            'categories' => $this->categories(),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Create Article',
            'mode' => 'create',
            'article' => null,
            'csrf' => Csrf::token('articles_form'),
            'categories' => $this->categories(),
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $this->slugify($request->body['slug'] ?? $request->body['title_en'] ?? 'item');
        $titleEn = trim($request->body['title_en'] ?? '');
        $titleRu = trim($request->body['title_ru'] ?? '');
        if ($titleEn === '' || $titleRu === '') {
            return new Response('Titles are required', 422);
        }
        $category = trim($request->body['category_new'] ?? '') ?: trim($request->body['category'] ?? '');
        $this->rememberCategory($category);
        $exists = $this->db->fetch("SELECT id FROM articles WHERE slug = ?", [$slug]);
        if ($exists) {
            return new Response('Slug already exists', 409);
        }
        $imageUrl = $this->hasColumn('image_url') ? $this->handleUpload($request, trim($request->body['image_url'] ?? '') ?: null) : null;
        $hasCat = $this->hasColumn('category');
        $hasImg = $this->hasColumn('image_url');
        $hasPreview = $this->hasColumn('preview_en');
        $hasAuthor = $this->hasColumn('author_id');
        $cols = ['slug', 'title_en', 'title_ru', 'body_en', 'body_ru', 'created_at', 'updated_at'];
        $vals = [':slug', ':title_en', ':title_ru', ':body_en', ':body_ru', 'NOW()', 'NOW()'];
        $params = [
            ':slug' => $slug,
            ':title_en' => $request->body['title_en'] ?? '',
            ':title_ru' => $request->body['title_ru'] ?? '',
            ':body_en' => $request->body['body_en'] ?? '',
            ':body_ru' => $request->body['body_ru'] ?? '',
        ];
        if ($hasAuthor) {
            $cols[] = 'author_id';
            $vals[] = ':author_id';
            $params[':author_id'] = $this->currentAuthorId();
        }
        if ($hasPreview) {
            $cols[] = 'preview_en';
            $cols[] = 'preview_ru';
            $vals[] = ':preview_en';
            $vals[] = ':preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($hasCat) {
            $cols[] = 'category';
            $vals[] = ':category';
            $params[':category'] = $category ?: null;
        }
        if ($hasImg) {
            $cols[] = 'image_url';
            $vals[] = ':image_url';
            $params[':image_url'] = $imageUrl;
        }
        $sql = "INSERT INTO articles (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $this->db->execute($sql, $params);
        $articleId = (int)$this->db->pdo()->lastInsertId();
        $this->tags->sync('article', $articleId, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        try {
            $this->container->get('events')->dispatch('article.saved', ['id' => $articleId, 'slug' => $slug]);
        } catch (\Throwable $e) {
            \Core\Logger::log('Event dispatch failed: ' . $e->getMessage());
        }
        $this->clearCache($slug);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles']);
    }

    public function edit(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("SELECT * FROM articles WHERE slug = ?", [$slug]);
        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Edit Article',
            'mode' => 'edit',
            'article' => $article,
            'csrf' => Csrf::token('articles_form'),
            'tags' => $this->tags->forEntity('article', (int)($article['id'] ?? 0)),
            'categories' => $this->categories(),
        ]);
        return new Response($html);
    }

    public function settings(Request $request): Response
    {
        $settings = $this->moduleSettings->all('articles');
        $defaults = [
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ];
        $merged = array_merge($defaults, $settings);
        $html = $this->container->get('renderer')->render('articles/settings', [
            'title' => 'Articles Settings',
            'csrf' => Csrf::token('articles_settings'),
            'settings' => $merged,
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('articles_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $map = [
            'show_author' => 'articles_show_author',
            'show_date' => 'articles_show_date',
            'show_likes' => 'articles_show_likes',
            'show_views' => 'articles_show_views',
            'show_tags' => 'articles_show_tags',
        ];
        foreach ($map as $key => $settingKey) {
            $val = !empty($request->body[$settingKey]);
            $this->moduleSettings->set('articles', $key, $val);
        }
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/articles/settings?msg=saved']);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $newSlug = $this->slugify($request->body['slug'] ?? $slug);
        $existing = $this->db->fetch("SELECT id FROM articles WHERE slug = ? AND slug != ?", [$newSlug, $slug]);
        if ($existing) {
            return new Response('Slug already exists', 409);
        }
        $category = trim($request->body['category_new'] ?? '') ?: trim($request->body['category'] ?? '');
        $this->rememberCategory($category);
        $imageUrl = $this->hasColumn('image_url') ? $this->handleUpload($request, trim($request->body['image_url'] ?? '') ?: ($article['image_url'] ?? null)) : ($article['image_url'] ?? null);
        $hasCat = $this->hasColumn('category');
        $hasImg = $this->hasColumn('image_url');
        $hasPreview = $this->hasColumn('preview_en');
        $sets = [
            'slug = :slug',
            'title_en = :title_en',
            'title_ru = :title_ru',
            'body_en = :body_en',
            'body_ru = :body_ru',
            'updated_at = NOW()',
        ];
        $params = [
            ':slug' => $newSlug,
            ':title_en' => $request->body['title_en'] ?? '',
            ':title_ru' => $request->body['title_ru'] ?? '',
            ':body_en' => $request->body['body_en'] ?? '',
            ':body_ru' => $request->body['body_ru'] ?? '',
            ':current' => $slug,
        ];
        if ($hasPreview) {
            $sets[] = 'preview_en = :preview_en';
            $sets[] = 'preview_ru = :preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($hasCat) {
            $sets[] = 'category = :category';
            $params[':category'] = $category ?: null;
        }
        if ($hasImg) {
            $sets[] = 'image_url = :image_url';
            $params[':image_url'] = $imageUrl;
        }
        $sql = "UPDATE articles SET " . implode(', ', $sets) . " WHERE slug = :current";
        $this->db->execute($sql, $params);
        $article = $this->db->fetch("SELECT id FROM articles WHERE slug = ?", [$newSlug]);
        if ($article) {
            $this->tags->sync('article', (int)$article['id'], $this->tags->normalizeInput($request->body['tags'] ?? ''));
            try {
                $this->container->get('events')->dispatch('article.saved', ['id' => (int)$article['id'], 'slug' => $newSlug]);
            } catch (\Throwable $e) {
                \Core\Logger::log('Event dispatch failed: ' . $e->getMessage());
            }
        }
        $this->clearCache($slug);
        $this->clearCache($newSlug);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("SELECT id FROM articles WHERE slug = ?", [$slug]);
        $this->db->execute("DELETE FROM articles WHERE slug = ?", [$slug]);
        if ($article) {
            $this->db->execute("DELETE FROM taggables WHERE entity_type = 'article' AND entity_id = ?", [(int)$article['id']]);
        }
        $this->clearCache($slug);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles']);
    }

    private function slugify(string $string): string
    {
        $source = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $source), '-'));
        return $slug ?: 'item';
    }

    private function clearCache(string $slug): void
    {
        $cache = $this->container->get('cache');
        $cache->delete('articles_list');
        $cache->delete('article_' . $slug);
    }

    private function categories(): array
    {
        $json = $this->settings->get('article_categories', '[]');
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    private function rememberCategory(string $category): void
    {
        if ($category === '') {
            return;
        }
        $cats = $this->categories();
        if (!in_array($category, $cats, true)) {
            $cats[] = $category;
            $this->settings->set('article_categories', json_encode($cats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    private function handleUpload(Request $request, ?string $existing = null): ?string
    {
        if (empty($request->files['image']['tmp_name'])) {
            return $existing;
        }
        if (!$this->hasColumn('image_url')) {
            return $existing;
        }
        $cfg = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($request->files['image']['size'] > $maxSize) {
            return $existing;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($request->files['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return $existing;
        }
        $ext = $allowed[$mime];
        $name = uniqid('article_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $name;
        if (!move_uploaded_file($request->files['image']['tmp_name'], $target)) {
            return $existing;
        }
        $maxW = (int)($cfg['upload_max_width'] ?? 8000);
        $maxH = (int)($cfg['upload_max_height'] ?? 8000);
        [$w,$h] = @getimagesize($target) ?: [0,0];
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return $existing;
        }
        return '/storage/uploads/articles/' . $name;
    }

    private function currentAuthorId(): ?int
    {
        try {
            $auth = $this->container->get(\Modules\Users\Services\Auth::class);
            $user = $auth->user();
            return $user ? (int)$user['id'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hasColumn(string $name): bool
    {
        if (!isset($this->columnCache[$name])) {
            $row = $this->db->fetch("SHOW COLUMNS FROM articles LIKE ?", [$name]);
            $this->columnCache[$name] = $row ? true : false;
        }
        return $this->columnCache[$name];
    }
}
