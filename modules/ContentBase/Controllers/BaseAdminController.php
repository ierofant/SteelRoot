<?php
namespace Modules\ContentBase\Controllers;

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
use Modules\Comments\Services\EntityCommentPolicyService;

abstract class BaseAdminController
{
    protected Container $container;
    protected Database $db;
    protected TagService $tags;
    protected SettingsService $settings;
    protected ModuleSettings $moduleSettings;
    protected string $uploadPath;
    protected string $localeMode;
    private array $columnCache = [];
    private array $tableCache = [];

    abstract protected function table(): string;
    abstract protected function categoriesTable(): string;
    abstract protected function moduleKey(): string;
    abstract protected function adminBase(): string;
    abstract protected function entityType(): string;
    abstract protected function uploadSubdir(): string;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
        $this->settings = $container->get(SettingsService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->localeMode = $this->settings->get('locale_mode', 'multi');
        $this->uploadPath = APP_ROOT . '/storage/uploads/' . $this->uploadSubdir();
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
        $this->moduleSettings->loadDefaults($this->moduleKey(), [
            'default_upload_folder' => '',
        ]);
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $q = trim((string)($request->query['q'] ?? ''));

        $catSelect = $this->columnExists('category_id') && $this->tableExists($this->categoriesTable())
            ? ", ac.name_en AS category_name_en, ac.name_ru AS category_name_ru" : '';
        $catJoin = $catSelect !== '' ? " LEFT JOIN {$this->categoriesTable()} ac ON ac.id = a.category_id" : '';
        $authorSelect = $this->columnExists('author_id') && $this->tableExists('users') ? ", u.name AS author_name" : '';
        $authorJoin = $authorSelect !== '' ? " LEFT JOIN users u ON u.id = a.author_id" : '';

        $whereParts = [];
        $params = [];
        if ($q !== '') {
            $whereParts[] = '(a.title_en LIKE :q OR a.title_ru LIKE :q OR a.slug LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $total = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()} a {$whereSql}", $params)['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT a.*{$catSelect}{$authorSelect}
             FROM {$this->table()} a {$catJoin} {$authorJoin}
             {$whereSql}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Manage ' . ucfirst($this->moduleKey()),
            'articles' => $items,
            'csrf' => Csrf::token('articles_form'),
            'mode' => 'list',
            'categories' => $this->loadCategories(),
            'users' => $this->loadUsers(),
            'localeMode' => $this->localeMode,
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
            'sort' => 'created_at',
            'dir' => 'desc',
            'filters' => ['category_id' => 0, 'author_id' => 0, 'q' => $q],
            'adminBase' => $this->adminBase(),
            'contentType' => $this->moduleKey(),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Create ' . ucfirst($this->moduleKey()),
            'mode' => 'create',
            'article' => null,
            'csrf' => Csrf::token('articles_form'),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users' => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => ['mode' => 'default', 'group_ids' => []],
            'uploadFolder' => $this->defaultUploadFolder(),
            'return' => $this->resolveReturnUrl($request),
            'adminBase' => $this->adminBase(),
            'contentType' => $this->moduleKey(),
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
        $invalid = match ($this->localeMode) {
            'en' => $titleEn === '',
            'ru' => $titleRu === '',
            default => $titleEn === '' && $titleRu === '',
        };
        if ($invalid) {
            return new Response('Title is required', 422);
        }
        if ($this->db->fetch("SELECT id FROM {$this->table()} WHERE slug = ?", [$slug])) {
            return new Response('Slug already exists', 409);
        }

        $cols = ['slug', 'title_en', 'title_ru', 'body_en', 'body_ru', 'created_at', 'updated_at'];
        $vals = [':slug', ':title_en', ':title_ru', ':body_en', ':body_ru', 'NOW()', 'NOW()'];
        $params = [
            ':slug' => $slug,
            ':title_en' => $request->body['title_en'] ?? '',
            ':title_ru' => $request->body['title_ru'] ?? '',
            ':body_en' => $request->body['body_en'] ?? '',
            ':body_ru' => $request->body['body_ru'] ?? '',
        ];
        $this->addOptionalCols($request, $cols, $vals, $params, null);

        $this->db->execute(
            "INSERT INTO {$this->table()} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")",
            $params
        );
        $id = (int)$this->db->pdo()->lastInsertId();
        $this->tags->sync($this->entityType(), $id, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->commentPolicyService()->save(
            $this->entityType(),
            $id,
            (string)($request->body['comments_mode'] ?? 'default'),
            (array)($request->body['comments_group_ids'] ?? [])
        );
        $this->container->get(SearchIndexService::class)->upsertEntity($this->entityType(), $id);
        $this->clearCache($slug);

        return new Response('', 302, ['Location' => $this->resolveReturnUrl($request) ?? $this->adminBase()]);
    }

    public function edit(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        $html = $this->container->get('renderer')->render('articles/form', [
            'title' => 'Edit ' . ucfirst($this->moduleKey()),
            'mode' => 'edit',
            'article' => $item,
            'csrf' => Csrf::token('articles_form'),
            'tags' => $this->tags->forEntity($this->entityType(), (int)($item['id'] ?? 0)),
            'tagsInput' => $this->tags->formatInput($this->tags->forEntity($this->entityType(), (int)($item['id'] ?? 0))),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users' => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => $this->commentPolicyService()->load($this->entityType(), (int)($item['id'] ?? 0)),
            'uploadFolder' => $this->detectUploadFolder($item) ?: $this->defaultUploadFolder(),
            'return' => $this->resolveReturnUrl($request),
            'adminBase' => $this->adminBase(),
            'contentType' => $this->moduleKey(),
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        $slugInput = trim($request->body['slug'] ?? '');
        $slugSource = $slugInput !== '' ? $slugInput : ($request->body['title_en'] ?? $request->body['title_ru'] ?? $slug);
        $newSlug = $this->slugify($slugSource);

        if ($this->db->fetch("SELECT id FROM {$this->table()} WHERE slug = ? AND slug != ?", [$newSlug, $slug])) {
            return new Response('Slug already exists', 409);
        }

        $sets = ['slug = :slug', 'title_en = :title_en', 'title_ru = :title_ru', 'body_en = :body_en', 'body_ru = :body_ru', 'updated_at = NOW()'];
        $params = [
            ':slug' => $newSlug,
            ':title_en' => $request->body['title_en'] ?? '',
            ':title_ru' => $request->body['title_ru'] ?? '',
            ':body_en' => $request->body['body_en'] ?? '',
            ':body_ru' => $request->body['body_ru'] ?? '',
            ':current' => $slug,
        ];
        $this->addOptionalSets($request, $sets, $params, $item);

        $this->db->execute("UPDATE {$this->table()} SET " . implode(', ', $sets) . " WHERE slug = :current", $params);
        if (is_array($item)) {
            $this->deleteReplacedLocalUpload((string)($item['image_url'] ?? ''), (string)($params[':image_url'] ?? ''));
            $this->deleteReplacedLocalUpload((string)($item['cover_url'] ?? ''), (string)($params[':cover_url'] ?? ''));
        }
        $updated = $this->db->fetch("SELECT id FROM {$this->table()} WHERE slug = ?", [$newSlug]);
        if ($updated) {
            $this->tags->sync($this->entityType(), (int)$updated['id'], $this->tags->normalizeInput($request->body['tags'] ?? ''));
            $this->commentPolicyService()->save(
                $this->entityType(),
                (int)$updated['id'],
                (string)($request->body['comments_mode'] ?? 'default'),
                (array)($request->body['comments_group_ids'] ?? [])
            );
            $this->container->get(SearchIndexService::class)->upsertEntity($this->entityType(), (int)$updated['id']);
        }
        $this->clearCache($slug);
        $this->clearCache($newSlug);

        return new Response('', 302, ['Location' => $this->resolveReturnUrl($request) ?? $this->adminBase()]);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('articles_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $slug = $request->params['slug'] ?? '';
        $select = ['id'];
        if ($this->columnExists('image_url')) {
            $select[] = 'image_url';
        }
        if ($this->columnExists('cover_url')) {
            $select[] = 'cover_url';
        }
        $item = $this->db->fetch("SELECT " . implode(', ', $select) . " FROM {$this->table()} WHERE slug = ?", [$slug]);
        $this->db->execute("DELETE FROM {$this->table()} WHERE slug = ?", [$slug]);
        if ($item) {
            $this->deleteLocalUpload((string)($item['image_url'] ?? ''));
            $this->deleteLocalUpload((string)($item['cover_url'] ?? ''));
            $this->db->execute("DELETE FROM taggables WHERE entity_type = ? AND entity_id = ?", [$this->entityType(), (int)$item['id']]);
            if ($this->tableExists('comment_entity_group_map')) {
                $this->db->execute("DELETE FROM comment_entity_group_map WHERE entity_type = ? AND entity_id = ?", [$this->entityType(), (int)$item['id']]);
            }
            if ($this->tableExists('comments')) {
                $this->db->execute("DELETE FROM comments WHERE entity_type = ? AND entity_id = ?", [$this->entityType(), (int)$item['id']]);
            }
            if ($this->tableExists('likes')) {
                $this->db->execute("DELETE FROM likes WHERE entity_type = ? AND entity_id = ?", [$this->entityType(), (int)$item['id']]);
            }
            $this->container->get(SearchIndexService::class)->deleteEntity($this->entityType(), (int)$item['id']);
        }
        $this->clearCache($slug);
        return new Response('', 302, ['Location' => $this->resolveReturnUrl($request) ?? $this->adminBase()]);
    }

    private function addOptionalCols(Request $request, array &$cols, array &$vals, array &$params, ?array $existing): void
    {
        if ($this->columnExists('author_id')) {
            $cols[] = 'author_id';
            $vals[] = ':author_id';
            $params[':author_id'] = (int)($request->body['author_id'] ?? 0) ?: null;
        }
        if ($this->columnExists('preview_en')) {
            $cols[] = 'preview_en';
            $vals[] = ':preview_en';
            $cols[] = 'preview_ru';
            $vals[] = ':preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($this->columnExists('description_en')) {
            $cols[] = 'description_en';
            $vals[] = ':description_en';
            $params[':description_en'] = $request->body['description_en'] ?? '';
        }
        if ($this->columnExists('description_ru')) {
            $cols[] = 'description_ru';
            $vals[] = ':description_ru';
            $params[':description_ru'] = $request->body['description_ru'] ?? '';
        }
        if ($this->columnExists('category_id')) {
            $cols[] = 'category_id';
            $vals[] = ':category_id';
            $params[':category_id'] = (int)($request->body['category_id'] ?? 0) ?: null;
        }
        if ($this->columnExists('image_url')) {
            $cols[] = 'image_url';
            $vals[] = ':image_url';
            $params[':image_url'] = $this->handleUpload($request, 'image', $existing['image_url'] ?? null);
        }
        if ($this->columnExists('cover_url')) {
            $cols[] = 'cover_url';
            $vals[] = ':cover_url';
            $params[':cover_url'] = $this->handleUpload($request, 'cover', $existing['cover_url'] ?? null);
        }
    }

    private function addOptionalSets(Request $request, array &$sets, array &$params, ?array $existing): void
    {
        if ($this->columnExists('author_id')) {
            $sets[] = 'author_id = :author_id';
            $params[':author_id'] = (int)($request->body['author_id'] ?? 0) ?: null;
        }
        if ($this->columnExists('preview_en')) {
            $sets[] = 'preview_en = :preview_en';
            $sets[] = 'preview_ru = :preview_ru';
            $params[':preview_en'] = $request->body['preview_en'] ?? '';
            $params[':preview_ru'] = $request->body['preview_ru'] ?? '';
        }
        if ($this->columnExists('description_en')) {
            $sets[] = 'description_en = :description_en';
            $params[':description_en'] = $request->body['description_en'] ?? '';
        }
        if ($this->columnExists('description_ru')) {
            $sets[] = 'description_ru = :description_ru';
            $params[':description_ru'] = $request->body['description_ru'] ?? '';
        }
        if ($this->columnExists('category_id')) {
            $sets[] = 'category_id = :category_id';
            $params[':category_id'] = (int)($request->body['category_id'] ?? 0) ?: null;
        }
        if ($this->columnExists('image_url')) {
            $sets[] = 'image_url = :image_url';
            $params[':image_url'] = $this->handleUpload($request, 'image', $existing['image_url'] ?? null);
        }
        if ($this->columnExists('cover_url')) {
            $sets[] = 'cover_url = :cover_url';
            $params[':cover_url'] = $this->handleUpload($request, 'cover', $existing['cover_url'] ?? null);
        }
    }

    protected function handleUpload(Request $request, string $field, ?string $existing = null): ?string
    {
        if (empty($request->files[$field]['tmp_name'])) {
            return $existing;
        }
        $cfg = $this->settings->all();
        $maxSize = (int)($cfg['upload_max_bytes'] ?? 5 * 1024 * 1024);
        if ($request->files[$field]['size'] > $maxSize) {
            return $existing;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($request->files[$field]['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return $existing;
        }
        $name = uniqid($this->moduleKey() . '_', true) . '.' . $allowed[$mime];
        $relativeFolder = $this->resolveUploadFolder((string)($request->body['upload_folder'] ?? ''));
        $targetDir = $this->uploadPath . ($relativeFolder !== '' ? '/' . $relativeFolder : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }
        $target = $targetDir . '/' . $name;
        if (!move_uploaded_file($request->files[$field]['tmp_name'], $target)) {
            return $existing;
        }
        return '/storage/uploads/' . $this->uploadSubdir() . '/' . ($relativeFolder !== '' ? ($relativeFolder . '/') : '') . $name;
    }

    protected function deleteLocalUpload(string $path): void
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/storage/uploads/' . $this->uploadSubdir() . '/')) {
            return;
        }
        $absolutePath = APP_ROOT . $path;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    protected function deleteReplacedLocalUpload(string $oldPath, string $newPath): void
    {
        $oldPath = trim($oldPath);
        $newPath = trim($newPath);
        if ($oldPath === '' || $oldPath === $newPath) {
            return;
        }
        $this->deleteLocalUpload($oldPath);
    }

    protected function normalizeUploadFolder(string $folder): string
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

    protected function detectUploadFolder(?array $item): string
    {
        $prefix = '/storage/uploads/' . $this->uploadSubdir() . '/';
        foreach (['image_url', 'cover_url'] as $field) {
            $path = trim((string)($item[$field] ?? ''));
            if ($path === '' || !str_starts_with($path, $prefix)) {
                continue;
            }
            $relative = trim(substr($path, strlen($prefix)), '/');
            if ($relative === '' || !str_contains($relative, '/')) {
                return '';
            }
            return trim(dirname($relative), '/.');
        }
        return '';
    }

    protected function defaultUploadFolder(): string
    {
        return $this->normalizeUploadFolder((string)$this->moduleSettings->get($this->moduleKey(), 'default_upload_folder', ''));
    }

    protected function resolveUploadFolder(string $rawFolder): string
    {
        $folder = $this->normalizeUploadFolder($rawFolder);
        return $folder !== '' ? $folder : $this->defaultUploadFolder();
    }

    protected function slugify(string $string): string
    {
        return SlugService::slugify($string, 'item');
    }

    protected function clearCache(string $slug): void
    {
        $cache = $this->container->get('cache');
        $cache->delete($this->moduleKey() . '_' . $slug . '_ru');
        $cache->delete($this->moduleKey() . '_' . $slug . '_en');
        if ($this->moduleKey() === 'news') {
            $cache->delete('news_' . $slug . '_ru');
            $cache->delete('news_' . $slug . '_en');
            $cache->delete('news_public_payload_' . sha1('ru|' . $slug));
            $cache->delete('news_public_payload_' . sha1('en|' . $slug));
        }
    }

    protected function loadCategories(): array
    {
        try {
            return $this->db->fetchAll("SELECT * FROM {$this->categoriesTable()} ORDER BY position ASC, id ASC");
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function loadUsers(): array
    {
        if (!$this->columnExists('author_id')) {
            return [];
        }
        return $this->db->fetchAll("SELECT id, name FROM users ORDER BY name ASC");
    }

    protected function loadCommentGroups(): array
    {
        return $this->commentPolicyService()->groups();
    }

    protected function resolveReturnUrl(Request $request): ?string
    {
        $candidate = (string)($request->query['return'] ?? $request->body['return'] ?? '');
        if ($candidate === '' || strpos($candidate, '://') !== false || ($candidate[0] ?? '') !== '/') {
            return null;
        }
        $path = (string)parse_url($candidate, PHP_URL_PATH);
        return str_starts_with($path, $this->adminBase()) ? $candidate : null;
    }

    protected function columnExists(string $col): bool
    {
        $key = $this->table() . '.' . $col;
        if (!isset($this->columnCache[$key])) {
            $row = $this->db->fetch("SHOW COLUMNS FROM {$this->table()} LIKE ?", [$col]);
            $this->columnCache[$key] = (bool)$row;
        }
        return $this->columnCache[$key];
    }

    protected function tableExists(string $t): bool
    {
        if (!isset($this->tableCache[$t])) {
            $row = $this->db->fetch("SHOW TABLES LIKE ?", [$t]);
            $this->tableCache[$t] = (bool)$row;
        }
        return $this->tableCache[$t];
    }

    protected function commentPolicyService(): EntityCommentPolicyService
    {
        return $this->container->get(EntityCommentPolicyService::class);
    }
}
