<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use Core\ModuleSettings;
use App\Services\SlugService;
use App\Services\SearchIndexService;
use App\Services\TagService;
use App\Services\SettingsService;
use Modules\Articles\Services\ArticleCategoryService;
use Modules\Comments\Services\EntityCommentPolicyService;

class AdminArticlesController
{
    private Container $container;
    private Database $db;
    private TagService $tags;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;
    private ArticleCategoryService $articleCategories;
    private string $uploadPath;
    private array $columnCache = [];
    private array $tableCache = [];
    private string $localeMode;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
        $this->settings = $container->get(SettingsService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->articleCategories = new ArticleCategoryService($this->db, $container->get('cache'));
        $this->localeMode = $this->settings->get('locale_mode', 'multi');
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
            'description_enabled' => true,
            'default_upload_folder' => '',
        ]);
    }

    public function index(Request $request): Response
    {
        $page    = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;

        $hasCategory = $this->hasColumn('category_id') && $this->tableExists('article_categories');
        $hasAuthor = $this->hasColumn('author_id') && $this->tableExists('users');
        $restrictedAuthorId = $this->restrictedAuthorId();
        $filters = [
            'category_id' => $hasCategory ? max(0, (int)($request->query['category_id'] ?? 0)) : 0,
            'author_id'   => $restrictedAuthorId ?? ($hasAuthor ? max(0, (int)($request->query['author_id'] ?? 0)) : 0),
            'q'           => trim((string)($request->query['q'] ?? '')),
        ];

        $allowedSort = ['created_at', 'title_en', 'title_ru', 'slug', 'views', 'likes', 'category', 'author'];
        $sort = in_array($request->query['sort'] ?? '', $allowedSort, true) ? $request->query['sort'] : 'created_at';
        $dir  = ($request->query['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $sortMap = [
            'created_at' => 'a.created_at',
            'title_en'   => 'a.title_en',
            'title_ru'   => 'a.title_ru',
            'slug'       => 'a.slug',
            'views'      => 'a.views',
            'likes'      => 'a.likes',
            'category'   => $this->localeMode === 'ru'
                ? "COALESCE(NULLIF(ac.name_ru, ''), ac.name_en, '')"
                : "COALESCE(NULLIF(ac.name_en, ''), ac.name_ru, '')",
            'author'     => "COALESCE(NULLIF(u.name, ''), '')",
        ];
        if (($sort === 'category' && !$hasCategory) || ($sort === 'author' && !$hasAuthor)) {
            $sort = 'created_at';
        }
        $sortSql = $sortMap[$sort] ?? 'a.created_at';

        $catSelect = $hasCategory ? ', ac.name_en AS category_name_en, ac.name_ru AS category_name_ru' : '';
        $catJoin = $hasCategory ? ' LEFT JOIN article_categories ac ON ac.id = a.category_id' : '';
        $authorSelect = $hasAuthor ? ', u.name AS author_name' : '';
        $authorJoin = $hasAuthor ? ' LEFT JOIN users u ON u.id = a.author_id' : '';
        $whereParts = [];
        $params = [];
        if ($filters['category_id'] > 0 && $hasCategory) {
            $whereParts[] = 'a.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if ($filters['author_id'] > 0 && $hasAuthor) {
            $whereParts[] = 'a.author_id = :author_id';
            $params[':author_id'] = $filters['author_id'];
        }
        if ($filters['q'] !== '') {
            $whereParts[] = '(a.title_en LIKE :q OR a.title_ru LIKE :q OR a.slug LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        $whereSql = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';

        $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM articles a{$whereSql}", $params);
        $total    = (int)($totalRow['cnt'] ?? 0);
        $offset   = ($page - 1) * $perPage;
        $limitSql = (int)$perPage;
        $offsetSql = (int)$offset;

        $articles = $this->db->fetchAll(
            "SELECT a.*{$catSelect}{$authorSelect}
             FROM articles a
             {$catJoin}
             {$authorJoin}
             {$whereSql}
             ORDER BY {$sortSql} {$dir}
             LIMIT {$limitSql} OFFSET {$offsetSql}",
            $params
        );
        $html = $this->container->get('renderer')->render('articles/form', [
            'title'      => 'Manage Articles',
            'articles'   => $articles,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'mode'       => 'list',
            'categories' => $this->articleCategories->all(),
            'users'      => $this->loadUsers(),
            'localeMode' => $this->localeMode,
            'page'       => $page,
            'total'      => $total,
            'perPage'    => $perPage,
            'sort'       => $sort,
            'dir'        => $dir,
            'filters'    => $filters,
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('articles/form', [
            'title'      => 'Create Article',
            'mode'       => 'create',
            'article'    => null,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'categories' => $this->articleCategories->all(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => ['mode' => 'default', 'group_ids' => []],
            'commentsUi' => $this->commentsUiState('article'),
            'uploadFolder' => $this->defaultUploadFolder(),
            'return'     => $this->resolveReturnUrl($request),
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slugInput = trim($request->body['slug'] ?? '');
        $slugSource = $slugInput !== '' ? $slugInput : ($request->body['title_en'] ?? $request->body['title_ru'] ?? 'item');
        $slug = $this->slugify($slugSource);
        $titleEn = trim($request->body['title_en'] ?? '');
        $titleRu = trim($request->body['title_ru'] ?? '');
        $titleInvalid = match ($this->localeMode) {
            'en'    => $titleEn === '',
            'ru'    => $titleRu === '',
            default => $titleEn === '' && $titleRu === '',
        };
        if ($titleInvalid) {
            return new Response('Title is required', 422);
        }
        $categoryId = (int)($request->body['category_id'] ?? 0) ?: null;
        $exists = $this->db->fetch("SELECT id FROM articles WHERE slug = ?", [$slug]);
        if ($exists) {
            return new Response('Slug already exists', 409);
        }
        $imageUrl = $this->hasColumn('image_url') ? $this->handleUpload($request, 'image', trim($request->body['image_url'] ?? '') ?: null) : null;
        $coverUrl = $this->hasColumn('cover_url') ? $this->handleUpload($request, 'cover', trim($request->body['cover_url'] ?? '') ?: null) : null;
        $hasImg = $this->hasColumn('image_url');
        $hasCover = $this->hasColumn('cover_url');
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
            $restrictedAuthorId = $this->restrictedAuthorId();
            $authorId = $restrictedAuthorId ?? ((int)($request->body['author_id'] ?? 0) ?: null);
            $params[':author_id'] = $authorId;
        }
        if ($hasPreview) {
            $cols[] = 'preview_en';
            $cols[] = 'preview_ru';
            $vals[] = ':preview_en';
            $vals[] = ':preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($this->hasColumn('description_en')) {
            $cols[] = 'description_en';
            $vals[] = ':description_en';
            $params[':description_en'] = $request->body['description_en'] ?? '';
        }
        if ($this->hasColumn('description_ru')) {
            $cols[] = 'description_ru';
            $vals[] = ':description_ru';
            $params[':description_ru'] = $request->body['description_ru'] ?? '';
        }
        if ($this->hasColumn('category_id')) {
            $cols[] = 'category_id';
            $vals[] = ':category_id';
            $params[':category_id'] = $categoryId;
        }
        if ($hasImg) {
            $cols[] = 'image_url';
            $vals[] = ':image_url';
            $params[':image_url'] = $imageUrl;
        }
        if ($hasCover) {
            $cols[] = 'cover_url';
            $vals[] = ':cover_url';
            $params[':cover_url'] = $coverUrl;
        }
        $sql = "INSERT INTO articles (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $this->db->execute($sql, $params);
        $articleId = (int)$this->db->pdo()->lastInsertId();
        $this->tags->sync('article', $articleId, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $commentsUi = $this->commentsUiState('article');
        $this->commentPolicyService()->save(
            'article',
            $articleId,
            !empty($commentsUi['available']) ? (string)($request->body['comments_mode'] ?? 'default') : 'default',
            !empty($commentsUi['available']) ? (array)($request->body['comments_group_ids'] ?? []) : []
        );
        $this->container->get(SearchIndexService::class)->upsertEntity('article', $articleId);
        try {
            $this->container->get('events')->dispatch('article.saved', ['id' => $articleId, 'slug' => $slug]);
        } catch (\Throwable $e) {
            \Core\Logger::log('Event dispatch failed: ' . $e->getMessage());
        }
        $this->clearCache($slug);
        return new Response('', 302, ['Location' => $this->resolvePostSaveUrl($request, $slug)]);
    }

    public function edit(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("SELECT * FROM articles WHERE slug = ?", [$slug]);
        $restrictedAuthorId = $this->restrictedAuthorId();
        if ($restrictedAuthorId !== null && (int)($article['author_id'] ?? 0) !== $restrictedAuthorId) {
            return new Response('Access denied', 403);
        }
        $html = $this->container->get('renderer')->render('articles/form', [
            'title'      => 'Edit Article',
            'mode'       => 'edit',
            'article'    => $article,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'tags'       => $this->tags->forEntity('article', (int)($article['id'] ?? 0)),
            'categories' => $this->articleCategories->all(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => $this->commentPolicyService()->load('article', (int)($article['id'] ?? 0)),
            'commentsUi' => $this->commentsUiState('article'),
            'uploadFolder' => $this->detectUploadFolder($article) ?: $this->defaultUploadFolder(),
            'return'     => $this->resolveReturnUrl($request),
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
            'seo_title_en' => '',
            'seo_title_ru' => '',
            'seo_desc_en' => '',
            'seo_desc_ru' => '',
            'default_upload_folder' => '',
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
        $boolMap = [
            'show_author'         => 'articles_show_author',
            'show_date'           => 'articles_show_date',
            'show_likes'          => 'articles_show_likes',
            'show_views'          => 'articles_show_views',
            'show_tags'           => 'articles_show_tags',
            'description_enabled' => 'articles_description_enabled',
        ];
        foreach ($boolMap as $key => $settingKey) {
            $this->moduleSettings->set('articles', $key, !empty($request->body[$settingKey]));
        }
        $gridCols = max(1, min(6, (int)($request->body['articles_grid_cols'] ?? 3)));
        $perPage  = max(1, (int)($request->body['articles_per_page'] ?? 6));
        $this->moduleSettings->set('articles', 'grid_cols', $gridCols);
        $this->moduleSettings->set('articles', 'per_page', $perPage);
        $this->moduleSettings->set('articles', 'seo_title_en', trim((string)($request->body['articles_seo_title_en'] ?? '')));
        $this->moduleSettings->set('articles', 'seo_title_ru', trim((string)($request->body['articles_seo_title_ru'] ?? '')));
        $this->moduleSettings->set('articles', 'seo_desc_en', trim((string)($request->body['articles_seo_desc_en'] ?? '')));
        $this->moduleSettings->set('articles', 'seo_desc_ru', trim((string)($request->body['articles_seo_desc_ru'] ?? '')));
        $this->moduleSettings->set('articles', 'default_upload_folder', $this->normalizeUploadFolder((string)($request->body['articles_default_upload_folder'] ?? '')));
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/articles/settings?msg=saved']);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $article = $this->db->fetch("SELECT * FROM articles WHERE slug = ?", [$slug]);
        $restrictedAuthorId = $this->restrictedAuthorId();
        if ($restrictedAuthorId !== null && (int)($article['author_id'] ?? 0) !== $restrictedAuthorId) {
            return new Response('Access denied', 403);
        }
        $slugInput = trim($request->body['slug'] ?? '');
        $slugSource = $slugInput !== '' ? $slugInput : ($request->body['title_en'] ?? $request->body['title_ru'] ?? $slug);
        $newSlug = $this->slugify($slugSource);
        $existing = $this->db->fetch("SELECT id FROM articles WHERE slug = ? AND slug != ?", [$newSlug, $slug]);
        if ($existing) {
            return new Response('Slug already exists', 409);
        }
        $categoryId = (int)($request->body['category_id'] ?? 0) ?: null;
        $imageUrl = $this->hasColumn('image_url') ? $this->handleUpload($request, 'image', trim($request->body['image_url'] ?? '') ?: ($article['image_url'] ?? null)) : ($article['image_url'] ?? null);
        $coverUrl = $this->hasColumn('cover_url') ? $this->handleUpload($request, 'cover', trim($request->body['cover_url'] ?? '') ?: ($article['cover_url'] ?? null)) : ($article['cover_url'] ?? null);
        $hasImg = $this->hasColumn('image_url');
        $hasCover = $this->hasColumn('cover_url');
        $hasPreview = $this->hasColumn('preview_en');
        $hasAuthor = $this->hasColumn('author_id');
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
        if ($hasAuthor) {
            $sets[] = 'author_id = :author_id';
            $params[':author_id'] = $restrictedAuthorId ?? ((int)($request->body['author_id'] ?? 0) ?: null);
        }
        if ($hasPreview) {
            $sets[] = 'preview_en = :preview_en';
            $sets[] = 'preview_ru = :preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($this->hasColumn('category_id')) {
            $sets[] = 'category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }
        if ($hasImg) {
            $sets[] = 'image_url = :image_url';
            $params[':image_url'] = $imageUrl;
        }
        if ($hasCover) {
            $sets[] = 'cover_url = :cover_url';
            $params[':cover_url'] = $coverUrl;
        }
        if ($this->hasColumn('description_en')) {
            $sets[] = 'description_en = :description_en';
            $params[':description_en'] = $request->body['description_en'] ?? '';
        }
        if ($this->hasColumn('description_ru')) {
            $sets[] = 'description_ru = :description_ru';
            $params[':description_ru'] = $request->body['description_ru'] ?? '';
        }
        $sql = "UPDATE articles SET " . implode(', ', $sets) . " WHERE slug = :current";
        $this->db->execute($sql, $params);
        if (is_array($article)) {
            $this->deleteReplacedLocalUpload((string)($article['image_url'] ?? ''), (string)($imageUrl ?? ''));
            $this->deleteReplacedLocalUpload((string)($article['cover_url'] ?? ''), (string)($coverUrl ?? ''));
        }
        $article = $this->db->fetch("SELECT id FROM articles WHERE slug = ?", [$newSlug]);
        if ($article) {
            $this->tags->sync('article', (int)$article['id'], $this->tags->normalizeInput($request->body['tags'] ?? ''));
            $commentsUi = $this->commentsUiState('article');
            $this->commentPolicyService()->save(
                'article',
                (int)$article['id'],
                !empty($commentsUi['available']) ? (string)($request->body['comments_mode'] ?? 'default') : 'default',
                !empty($commentsUi['available']) ? (array)($request->body['comments_group_ids'] ?? []) : []
            );
            $this->container->get(SearchIndexService::class)->upsertEntity('article', (int)$article['id']);
            try {
                $this->container->get('events')->dispatch('article.saved', ['id' => (int)$article['id'], 'slug' => $newSlug]);
            } catch (\Throwable $e) {
                \Core\Logger::log('Event dispatch failed: ' . $e->getMessage());
            }
        }
        $this->clearCache($slug);
        $this->clearCache($newSlug);
        return new Response('', 302, ['Location' => $this->resolvePostSaveUrl($request, $newSlug)]);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $restrictedAuthorId = $this->restrictedAuthorId();
        if ($restrictedAuthorId !== null) {
            $ownerCheck = $this->db->fetch("SELECT author_id FROM articles WHERE slug = ?", [$slug]);
            if (!$ownerCheck || (int)($ownerCheck['author_id'] ?? 0) !== $restrictedAuthorId) {
                return new Response('Access denied', 403);
            }
        }
        $select = ['id'];
        if ($this->hasColumn('image_url')) {
            $select[] = 'image_url';
        }
        if ($this->hasColumn('cover_url')) {
            $select[] = 'cover_url';
        }
        $article = $this->db->fetch("SELECT " . implode(', ', $select) . " FROM articles WHERE slug = ?", [$slug]);
        $this->db->execute("DELETE FROM articles WHERE slug = ?", [$slug]);
        if ($article) {
            $this->deleteLocalUpload((string)($article['image_url'] ?? ''));
            $this->deleteLocalUpload((string)($article['cover_url'] ?? ''));
            $this->db->execute("DELETE FROM taggables WHERE entity_type = 'article' AND entity_id = ?", [(int)$article['id']]);
            if ($this->tableExists('comment_entity_group_map')) {
                $this->db->execute("DELETE FROM comment_entity_group_map WHERE entity_type = 'article' AND entity_id = ?", [(int)$article['id']]);
            }
            if ($this->tableExists('comments')) {
                $this->db->execute("DELETE FROM comments WHERE entity_type = 'article' AND entity_id = ?", [(int)$article['id']]);
            }
            if ($this->tableExists('likes')) {
                $this->db->execute("DELETE FROM likes WHERE entity_type = 'article' AND entity_id = ?", [(int)$article['id']]);
            }
            $this->container->get(SearchIndexService::class)->deleteEntity('article', (int)$article['id']);
        }
        $this->clearCache($slug);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $this->resolveReturnUrl($request) ?? ($prefix . '/articles')]);
    }

    private function slugify(string $string): string
    {
        return SlugService::slugify($string, 'item');
    }

    private function clearCache(string $slug): void
    {
        $cache = $this->container->get('cache');
        $cache->delete('article_' . $slug);
        $cache->delete('article_public_payload_' . sha1($slug));
    }

    private function handleUpload(Request $request, string $field, ?string $existing = null): ?string
    {
        if (empty($request->files[$field]['tmp_name'])) {
            return $existing;
        }
        $cfg = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if (($request->files[$field]['size'] ?? 0) > $maxSize) {
            return $existing;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($request->files[$field]['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return $existing;
        }
        $ext = $allowed[$mime];
        $name = uniqid($field . '_', true) . '.' . $ext;
        $relativeFolder = $this->resolveUploadFolder((string)($request->body['upload_folder'] ?? ''));
        $targetDir = $this->uploadPath . ($relativeFolder !== '' ? '/' . $relativeFolder : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $target = $targetDir . '/' . $name;
        if (!move_uploaded_file($request->files[$field]['tmp_name'], $target)) {
            return $existing;
        }
        $maxW = (int)($cfg['upload_max_width'] ?? 8000);
        $maxH = (int)($cfg['upload_max_height'] ?? 8000);
        [$w,$h] = @getimagesize($target) ?: [0,0];
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return $existing;
        }
        return '/storage/uploads/articles/' . ($relativeFolder !== '' ? ($relativeFolder . '/') : '') . $name;
    }

    private function normalizeUploadFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder));
        if ($folder === '') {
            return '';
        }

        $parts = preg_split('~/+~', $folder) ?: [];
        $safe = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $part = preg_replace('~[^a-zA-Z0-9._-]+~', '-', $part) ?? '';
            $part = trim($part, '-.');
            if ($part !== '') {
                $safe[] = $part;
            }
        }

        return implode('/', $safe);
    }

    private function detectUploadFolder(?array $article): string
    {
        foreach (['image_url', 'cover_url'] as $field) {
            $path = trim((string)($article[$field] ?? ''));
            if ($path === '' || !str_starts_with($path, '/storage/uploads/articles/')) {
                continue;
            }

            $relative = trim(substr($path, strlen('/storage/uploads/articles/')), '/');
            if ($relative === '' || !str_contains($relative, '/')) {
                return '';
            }

            return trim(dirname($relative), '/.');
        }

        return '';
    }

    private function defaultUploadFolder(): string
    {
        return $this->normalizeUploadFolder((string)$this->moduleSettings->get('articles', 'default_upload_folder', ''));
    }

    private function resolveUploadFolder(string $rawFolder): string
    {
        $folder = $this->normalizeUploadFolder($rawFolder);
        return $folder !== '' ? $folder : $this->defaultUploadFolder();
    }

    private function deleteLocalUpload(string $path): void
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/storage/uploads/articles/')) {
            return;
        }

        $absolutePath = APP_ROOT . $path;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function deleteReplacedLocalUpload(string $oldPath, string $newPath): void
    {
        $oldPath = trim($oldPath);
        $newPath = trim($newPath);
        if ($oldPath === '' || $oldPath === $newPath) {
            return;
        }

        $this->deleteLocalUpload($oldPath);
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

    /**
     * Returns author_id to restrict access to own articles only, or null for full access.
     * Restriction applies when user is logged in via frontend session with admin.articles.own
     * but without admin.articles (full access).
     */
    private function restrictedAuthorId(): ?int
    {
        if (!empty($_SESSION['admin_auth'])) {
            return null;
        }
        try {
            $auth = $this->container->get(\Modules\Users\Services\Auth::class);
            $user = $auth->user();
            if (!$user) {
                return null;
            }
            $access = $this->container->get(\Modules\Users\Services\UserAccessService::class);
            if ($access->can($user, 'admin.articles')) {
                return null;
            }
            return (int)$user['id'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadUsers(): array
    {
        if (!$this->hasColumn('author_id')) {
            return [];
        }
        return $this->db->fetchAll("SELECT id, name FROM users ORDER BY name ASC");
    }

    private function loadCommentGroups(): array
    {
        return $this->commentPolicyService()->groups();
    }

    private function commentsUiState(string $entityType): array
    {
        $settings = $this->moduleSettings->all('comments');
        $enabled = !empty($settings['enabled']);
        $entityEnabled = in_array($entityType, (array)($settings['enabled_entity_types'] ?? []), true);

        return [
            'available' => $enabled && $entityEnabled,
            'enabled' => $enabled,
            'entity_enabled' => $entityEnabled,
            'settings_url' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/comments/settings',
        ];
    }

    private function hasColumn(string $name): bool
    {
        if (!isset($this->columnCache[$name])) {
            $row = $this->db->fetch("SHOW COLUMNS FROM articles LIKE ?", [$name]);
            $this->columnCache[$name] = $row ? true : false;
        }
        return $this->columnCache[$name];
    }

    private function tableExists(string $name): bool
    {
        if (!isset($this->tableCache[$name])) {
            $row = $this->db->fetch("SHOW TABLES LIKE ?", [$name]);
            $this->tableCache[$name] = $row ? true : false;
        }
        return $this->tableCache[$name];
    }

    private function commentPolicyService(): EntityCommentPolicyService
    {
        return $this->container->get(EntityCommentPolicyService::class);
    }

    private function resolveReturnUrl(Request $request): ?string
    {
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        $candidate = (string)($request->query['return'] ?? $request->body['return'] ?? '');
        if ($candidate === '') {
            return null;
        }
        if (strpos($candidate, '://') !== false) {
            return null;
        }
        if ($candidate[0] !== '/') {
            return null;
        }
        $path = (string)parse_url($candidate, PHP_URL_PATH);
        if ($path === '' || strpos($path, $prefix . '/articles') !== 0) {
            return null;
        }
        return $candidate;
    }

    private function resolvePostSaveUrl(Request $request, string $slug): string
    {
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        $saveMode = (string)($request->body['save_mode'] ?? 'close');
        if ($saveMode === 'stay') {
            $location = $prefix . '/articles/edit/' . rawurlencode($slug);
            $returnUrl = $this->resolveReturnUrl($request);
            if ($returnUrl !== null) {
                $location .= '?return=' . rawurlencode($returnUrl);
            }
            return $location;
        }

        return $this->resolveReturnUrl($request) ?? ($prefix . '/articles');
    }
}
