<?php
declare(strict_types=1);

namespace Modules\Video\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Request;
use Core\Response;
use App\Services\SettingsService;
use RuntimeException;

class AdminVideoController
{
    private Container       $container;
    private Database        $db;
    private SettingsService $settings;
    private string          $localeMode;
    private string          $uploadPath;
    private string          $categoryUploadPath;

    public function __construct(Container $container)
    {
        $this->container  = $container;
        $this->db         = $container->get(Database::class);
        $this->settings   = $container->get(SettingsService::class);
        $this->localeMode = $this->settings->get('locale_mode', 'multi');
        $this->uploadPath = APP_ROOT . '/storage/uploads/videos';
        if (!is_dir($this->uploadPath)) {
            @mkdir($this->uploadPath, 0775, true);
        }
        $this->categoryUploadPath = APP_ROOT . '/storage/uploads/videos/categories';
        if (!is_dir($this->categoryUploadPath)) {
            @mkdir($this->categoryUploadPath, 0775, true);
        }
    }

    public function index(Request $request): Response
    {
        $page     = max(1, (int)($request->query['page'] ?? 1));
        $perPage  = 20;
        $categoryFilter = max(0, (int)($request->query['category_id'] ?? 0));

        $allowedSort = ['created_at', 'title_en', 'title_ru', 'views', 'likes', 'category'];
        $sort = in_array($request->query['sort'] ?? '', $allowedSort, true)
            ? $request->query['sort'] : 'created_at';
        $dir  = ($request->query['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $sortSql = match ($sort) {
            'title_en' => 'v.title_en',
            'title_ru' => 'v.title_ru',
            'views' => 'v.views',
            'likes' => 'v.likes',
            'category' => 'category_name',
            default => 'v.created_at',
        };
        $where = '';
        $params = [];
        if ($categoryFilter > 0) {
            $where = 'WHERE v.category_id = ?';
            $params[] = $categoryFilter;
        }

        $total   = (int)($this->db->fetch("SELECT COUNT(*) AS cnt FROM video_items v {$where}", $params)['cnt'] ?? 0);
        $offset  = ($page - 1) * $perPage;
        $items   = $this->db->fetchAll(
            "SELECT v.id, v.slug, v.title_en, v.title_ru, v.video_type, v.video_id, v.thumbnail_url, v.views, v.likes, v.enabled, v.created_at,
                    v.category_id, COALESCE(c.name_en, c.name_ru, '') AS category_name_en, COALESCE(c.name_ru, c.name_en, '') AS category_name_ru,
                    COALESCE(c.name_en, c.name_ru, '') AS category_name
             FROM video_items v
             LEFT JOIN video_categories c ON c.id = v.category_id
             {$where}
             ORDER BY {$sortSql} {$dir}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $categories = $this->categoriesAll();

        ob_start();
        include __DIR__ . '/../views/admin/list.php';
        $content = ob_get_clean();
        return new Response($this->layout(
            $this->byLocale('Видео / Videos', 'Videos', 'Видео'),
            $content
        ));
    }

    public function create(Request $request): Response
    {
        $item = null;
        $mode = 'create';
        $categories = $this->categoriesEnabled();
        ob_start();
        include __DIR__ . '/../views/admin/form.php';
        $content = ob_get_clean();
        return new Response($this->layout(
            $this->byLocale('Добавить видео / Add Video', 'Add Video', 'Добавить видео'),
            $content
        ));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        [$url, $uploadError] = $this->resolveVideoUrl($request);
        if ($uploadError !== null) {
            return new Response($uploadError, 422);
        }

        $parsed   = VideoController::parseVideoUrl($url);
        $slug     = $this->makeSlug($request->body['slug'] ?? '', $request->body['title_en'] ?? '', $request->body['title_ru'] ?? '');
        $thumbUrl = trim($request->body['thumbnail_url'] ?? '') ?: null;
        $categoryId = $this->normalizeCategoryId($request->body['category_id'] ?? null);

        $this->db->execute(
            "INSERT INTO video_items
                (slug, title_en, title_ru, description_en, description_ru,
                 video_url, video_type, video_id, thumbnail_url, duration, category_id, enabled, created_at, updated_at)
             VALUES (:slug,:ten,:tru,:den,:dru,:url,:vtype,:vid,:thumb,:dur,:cat,:en,NOW(),NOW())",
            [
                ':slug'  => $slug,
                ':ten'   => trim($request->body['title_en'] ?? ''),
                ':tru'   => trim($request->body['title_ru'] ?? ''),
                ':den'   => trim($request->body['description_en'] ?? ''),
                ':dru'   => trim($request->body['description_ru'] ?? ''),
                ':url'   => $url,
                ':vtype' => $parsed['type'],
                ':vid'   => $parsed['video_id'],
                ':thumb' => $thumbUrl,
                ':dur'   => trim($request->body['duration'] ?? '') ?: null,
                ':cat'   => $categoryId,
                ':en'    => !empty($request->body['enabled']) ? 1 : 0,
            ]
        );
        return new Response('', 302, ['Location' => $this->prefix() . '/videos']);
    }

    public function edit(Request $request): Response
    {
        $id   = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM video_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $mode = 'edit';
        $categories = $this->categoriesEnabled();
        ob_start();
        include __DIR__ . '/../views/admin/form.php';
        $content = ob_get_clean();
        return new Response($this->layout(
            $this->byLocale('Редактировать видео / Edit Video', 'Edit Video', 'Редактировать видео'),
            $content
        ));
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id   = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT id FROM video_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        [$url, $uploadError] = $this->resolveVideoUrl($request);
        if ($uploadError !== null) {
            return new Response($uploadError, 422);
        }
        $parsed = VideoController::parseVideoUrl($url);
        $slug   = $this->makeSlug(
            $request->body['slug'] ?? '',
            $request->body['title_en'] ?? '',
            $request->body['title_ru'] ?? '',
            $id
        );
        $thumbUrl = trim($request->body['thumbnail_url'] ?? '') ?: null;
        $categoryId = $this->normalizeCategoryId($request->body['category_id'] ?? null);

        $this->db->execute(
            "UPDATE video_items SET
                slug = :slug, title_en = :ten, title_ru = :tru,
                description_en = :den, description_ru = :dru,
                video_url = :url, video_type = :vtype, video_id = :vid,
                thumbnail_url = :thumb, duration = :dur, category_id = :cat,
                enabled = :en, updated_at = NOW()
             WHERE id = :id",
            [
                ':slug'  => $slug,
                ':ten'   => trim($request->body['title_en'] ?? ''),
                ':tru'   => trim($request->body['title_ru'] ?? ''),
                ':den'   => trim($request->body['description_en'] ?? ''),
                ':dru'   => trim($request->body['description_ru'] ?? ''),
                ':url'   => $url,
                ':vtype' => $parsed['type'],
                ':vid'   => $parsed['video_id'],
                ':thumb' => $thumbUrl,
                ':dur'   => trim($request->body['duration'] ?? '') ?: null,
                ':cat'   => $categoryId,
                ':en'    => !empty($request->body['enabled']) ? 1 : 0,
                ':id'    => $id,
            ]
        );
        return new Response('', 302, ['Location' => $this->prefix() . '/videos']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->db->execute("DELETE FROM video_items WHERE id = ?", [$id]);
        return new Response('', 302, ['Location' => $this->prefix() . '/videos']);
    }

    public function categories(Request $request): Response
    {
        $categories = $this->categoriesAll();
        $cat = null;
        $mode = 'create';
        ob_start();
        include __DIR__ . '/../views/admin/categories.php';
        $content = ob_get_clean();
        return new Response($this->layout(
            $this->byLocale('Категории видео / Video Categories', 'Video Categories', 'Категории видео'),
            $content
        ));
    }

    public function categoriesCreate(Request $request): Response
    {
        return $this->categories($request);
    }

    public function categoriesStore(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $nameEn = trim((string)($request->body['name_en'] ?? ''));
        $nameRu = trim((string)($request->body['name_ru'] ?? ''));
        if ($nameEn === '' && $nameRu === '') {
            return new Response('Category name is required', 422);
        }
        $slug = $this->makeCategorySlug((string)($request->body['slug'] ?? ''), $nameEn, $nameRu);
        $this->db->execute(
            "INSERT INTO video_categories (slug, name_en, name_ru, image_url, enabled, sort_order, created_at, updated_at)
             VALUES (:slug, :en, :ru, :image, :enabled, :sort, NOW(), NOW())",
            [
                ':slug' => $slug,
                ':en' => $nameEn,
                ':ru' => $nameRu,
                ':image' => $this->resolveCategoryImage($request, null),
                ':enabled' => !empty($request->body['enabled']) ? 1 : 0,
                ':sort' => (int)($request->body['sort_order'] ?? 0),
            ]
        );
        return new Response('', 302, ['Location' => $this->prefix() . '/videos/categories']);
    }

    public function categoriesEdit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $cat = $this->db->fetch("SELECT * FROM video_categories WHERE id = ?", [$id]);
        if (!$cat) {
            return new Response('Not found', 404);
        }
        $categories = $this->categoriesAll();
        $mode = 'edit';
        ob_start();
        include __DIR__ . '/../views/admin/categories.php';
        $content = ob_get_clean();
        return new Response($this->layout(
            $this->byLocale('Категории видео / Video Categories', 'Video Categories', 'Категории видео'),
            $content
        ));
    }

    public function categoriesUpdate(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $exists = $this->db->fetch("SELECT id FROM video_categories WHERE id = ?", [$id]);
        if (!$exists) {
            return new Response('Not found', 404);
        }
        $nameEn = trim((string)($request->body['name_en'] ?? ''));
        $nameRu = trim((string)($request->body['name_ru'] ?? ''));
        if ($nameEn === '' && $nameRu === '') {
            return new Response('Category name is required', 422);
        }
        $slug = $this->makeCategorySlug((string)($request->body['slug'] ?? ''), $nameEn, $nameRu, $id);
        $this->db->execute(
            "UPDATE video_categories
             SET slug = :slug, name_en = :en, name_ru = :ru, image_url = :image, enabled = :enabled, sort_order = :sort, updated_at = NOW()
             WHERE id = :id",
            [
                ':slug' => $slug,
                ':en' => $nameEn,
                ':ru' => $nameRu,
                ':image' => $this->resolveCategoryImage($request, $id),
                ':enabled' => !empty($request->body['enabled']) ? 1 : 0,
                ':sort' => (int)($request->body['sort_order'] ?? 0),
                ':id' => $id,
            ]
        );
        return new Response('', 302, ['Location' => $this->prefix() . '/videos/categories']);
    }

    public function categoriesDelete(Request $request): Response
    {
        if (!Csrf::check('video_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->db->execute("UPDATE video_items SET category_id = NULL WHERE category_id = ?", [$id]);
        $this->db->execute("DELETE FROM video_categories WHERE id = ?", [$id]);
        return new Response('', 302, ['Location' => $this->prefix() . '/videos/categories']);
    }

    private function makeSlug(string $raw, string $titleEn, string $titleRu, ?int $exceptId = null): string
    {
        $source = $raw !== '' ? $raw : ($titleEn !== '' ? $titleEn : $titleRu);
        $source = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source) ?? $source;
        $slug   = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $source), '-'));
        $slug   = $slug ?: 'video';

        $base = $slug;
        $n    = 1;
        while (true) {
            $cond   = $exceptId ? "slug = ? AND id != ?" : "slug = ?";
            $params = $exceptId ? [$slug, $exceptId] : [$slug];
            if (!$this->db->fetch("SELECT id FROM video_items WHERE {$cond}", $params)) {
                break;
            }
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }

    private function layout(string $title, string $content): string
    {
        $ap          = $this->prefix();
        $localeMode  = $this->localeMode;
        $pageTitle   = $title;
        ob_start();
        include APP_ROOT . '/modules/Admin/views/layout.php';
        return ob_get_clean();
    }

    private function byLocale(string $multi, string $en, string $ru): string
    {
        return match ($this->localeMode) {
            'ru' => $ru,
            'en' => $en,
            default => $multi,
        };
    }

    private function categoriesAll(): array
    {
        return $this->db->fetchAll("SELECT * FROM video_categories ORDER BY sort_order ASC, id ASC");
    }

    private function categoriesEnabled(): array
    {
        return $this->db->fetchAll("SELECT * FROM video_categories WHERE enabled = 1 ORDER BY sort_order ASC, id ASC");
    }

    private function normalizeCategoryId(mixed $raw): ?int
    {
        $id = (int)$raw;
        if ($id <= 0) {
            return null;
        }
        $exists = $this->db->fetch("SELECT id FROM video_categories WHERE id = ?", [$id]);
        return $exists ? $id : null;
    }

    private function makeCategorySlug(string $raw, string $nameEn, string $nameRu, ?int $exceptId = null): string
    {
        $source = $raw !== '' ? $raw : ($nameEn !== '' ? $nameEn : $nameRu);
        $source = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source) ?? $source;
        $slug   = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $source), '-'));
        $slug   = $slug ?: 'video-category';

        $base = $slug;
        $n = 1;
        while (true) {
            $cond = $exceptId ? "slug = ? AND id != ?" : "slug = ?";
            $params = $exceptId ? [$slug, $exceptId] : [$slug];
            if (!$this->db->fetch("SELECT id FROM video_categories WHERE {$cond}", $params)) {
                break;
            }
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    private function resolveCategoryImage(Request $request, ?int $categoryId): ?string
    {
        $manual = trim((string)($request->body['image_url'] ?? ''));
        $file = $request->files['image_file'] ?? null;
        if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return $this->uploadCategoryImage($file);
        }
        if ($manual !== '') {
            return $manual;
        }
        if ($categoryId) {
            $row = $this->db->fetch("SELECT image_url FROM video_categories WHERE id = ?", [$categoryId]);
            return $row['image_url'] ?? null;
        }
        return null;
    }

    private function uploadCategoryImage(array $file): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Category image upload failed');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Uploaded image is invalid');
        }

        $maxSize = (int)$this->settings->get('upload_max_bytes', 5 * 1024 * 1024);
        if ((int)($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('Category image is too large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $allowed[$mime] ?? '';
        if ($ext === '') {
            throw new RuntimeException('Unsupported category image format');
        }

        $name = uniqid('video_cat_', true) . '.' . $ext;
        $target = $this->categoryUploadPath . '/' . $name;
        if (!@move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Could not move category image');
        }
        return '/storage/uploads/videos/categories/' . $name;
    }

    private function resolveVideoUrl(Request $request): array
    {
        $url = trim((string)($request->body['video_url'] ?? ''));
        $file = $request->files['video_file'] ?? null;

        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [$url, $url === '' ? 'Video URL or uploaded file is required' : null];
        }

        try {
            return [$this->uploadVideoFile($file), null];
        } catch (RuntimeException $e) {
            return ['', $e->getMessage()];
        }
    }

    private function uploadVideoFile(array $file): string
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Video upload failed');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Uploaded file is invalid');
        }

        $maxSize = max(
            100 * 1024 * 1024,
            (int)$this->settings->get('video_upload_max_bytes', 0),
            (int)$this->settings->get('upload_max_bytes', 0)
        );
        if ((int)($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('Video file is too large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($tmp);
        $allowed = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
            'video/quicktime' => 'mov',
        ];

        $ext = $allowed[$mime] ?? '';
        if ($ext === '') {
            $originalExt = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (in_array($originalExt, ['mp4', 'webm', 'ogg', 'mov', 'm4v'], true)) {
                $ext = $originalExt;
            }
        }
        if ($ext === '') {
            throw new RuntimeException('Unsupported video format');
        }

        $name   = uniqid('video_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $name;
        if (!@move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Could not move uploaded video');
        }

        return '/storage/uploads/videos/' . $name;
    }
}
