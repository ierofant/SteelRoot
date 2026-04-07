<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use App\Services\SlugService;
use App\Services\SettingsService;
use App\Services\TagService;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Logger;
use Core\ModuleSettings;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Modules\Gallery\Services\GalleryCategoryService;
use Modules\Gallery\Services\GalleryImageVariantService;
use Modules\Users\Services\Auth;
use Modules\Users\Services\PhotoCopyrightService;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\UserRepository;

class MasterWorksController
{
    private Container $container;
    private Database $db;
    private Auth $auth;
    private UserRepository $users;
    private UserAccessService $access;
    private ModuleSettings $moduleSettings;
    private SettingsService $settings;
    private GalleryCategoryService $categories;
    private GalleryImageVariantService $imageVariants;
    private TagService $tags;
    private PhotoCopyrightService $photoCopyrights;
    private string $galleryPath;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
        $this->access = $container->get(UserAccessService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->settings = $container->get(SettingsService::class);
        $this->categories = new GalleryCategoryService($this->db, $container->get('cache'));
        $galleryVariantSettings = $this->moduleSettings->all('gallery');
        $this->imageVariants = new GalleryImageVariantService([
            'thumb_width' => $galleryVariantSettings['thumb_width'] ?? GalleryImageVariantService::DEFAULT_THUMB_WIDTH,
            'medium_width' => $galleryVariantSettings['medium_width'] ?? GalleryImageVariantService::DEFAULT_MEDIUM_WIDTH,
            'format' => $galleryVariantSettings['variants_format'] ?? GalleryImageVariantService::DEFAULT_FORMAT,
        ]);
        $this->tags = new TagService($this->db);
        $this->photoCopyrights = $container->get(PhotoCopyrightService::class);
        $this->galleryPath = APP_ROOT . '/storage/uploads/gallery';
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $user = $this->users->findFull((int)$user['id']) ?? $user;
        if (!$this->canUpload($user)) {
            return new Response('Forbidden', 403);
        }

        $html = $this->container->get('renderer')->render('users/master_works', [
            '_layout' => true,
            'title' => __('users.master_works.title'),
            'csrf' => Csrf::token('master_gallery_upload'),
            'user' => $user,
            'categories' => $this->categories->all(),
            'suggestedTags' => $this->tags->popular(24),
            'submissions' => $this->users->submissionsForUser((int)$user['id']),
            'usersSettings' => $this->masterSettings(),
            'message' => $this->messageFromQuery((string)($request->query['msg'] ?? '')),
            'error' => $request->query['err'] ?? null,
            'folderName' => $this->masterFolderName($user),
        ], [
            'title' => __('users.master_works.title'),
        ]);
        return new Response($html);
    }

    public function upload(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $user = $this->users->findFull((int)$user['id']) ?? $user;
        if (!$this->canUpload($user)) {
            return new Response('Forbidden', 403);
        }
        if (!Csrf::check('master_gallery_upload', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $ip = $request->server['REMOTE_ADDR'] ?? 'ip';
        $limiter = new RateLimiter('master_gallery_upload_' . $ip . '_' . (int)$user['id'], 10, 3600, true);
        if ($limiter->tooManyAttempts()) {
            return $this->redirectError(__('users.master_works.error.rate_limit'));
        }
        $limiter->hit();

        $file = $request->files['image'] ?? [];
        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $this->redirectError(__('users.master_works.error.file_required'));
        }

        $limits = $this->container->get('config')['limits'] ?? [];
        $maxSize = (int)$this->settings->get('upload_max_bytes', $limits['upload_max_bytes'] ?? (5 * 1024 * 1024));
        $maxW = (int)$this->settings->get('upload_max_width', $limits['upload_max_width'] ?? 8000);
        $maxH = (int)$this->settings->get('upload_max_height', $limits['upload_max_height'] ?? 8000);
        if ((int)($file['size'] ?? 0) > $maxSize) {
            return $this->redirectError(__('users.master_works.error.file_too_large'));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            Logger::log('Master upload rejected for user #' . (int)$user['id'] . ': mime=' . $mime);
            return $this->redirectError(__('users.master_works.error.file_type'));
        }

        [$w, $h] = @getimagesize($tmp) ?: [0, 0];
        if ($w < 1 || $h < 1 || $w > $maxW || $h > $maxH) {
            return $this->redirectError(__('users.master_works.error.dimensions'));
        }

        if ($this->galleryLimitExceeded($user)) {
            return $this->redirectError(__('users.master_works.error.limit'));
        }

        $folder = $this->masterFolderName($user);
        $absoluteDir = $this->galleryPath . '/masters/' . $folder;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            Logger::log('Failed to create master gallery folder for user #' . (int)$user['id'] . ': ' . $absoluteDir);
            return $this->redirectError(__('users.master_works.error.folder'));
        }

        $ext = $allowed[$mime];
        $baseName = 'm_' . (int)$user['id'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $fileName = $baseName . '.' . $ext;
        $target = $absoluteDir . '/' . $fileName;
        if (!move_uploaded_file($tmp, $target)) {
            Logger::log('Failed to move master upload for user #' . (int)$user['id']);
            return $this->redirectError(__('users.master_works.error.save'));
        }

        $status = $this->resolveStatus($user);
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', (array)($request->body['category_ids'] ?? [])))));
        $titleEn = trim((string)($request->body['title_en'] ?? ''));
        $titleRu = trim((string)($request->body['title_ru'] ?? ''));
        $descEn = trim((string)($request->body['description_en'] ?? ''));
        $descRu = trim((string)($request->body['description_ru'] ?? ''));
        $storageFolder = 'masters/' . $folder;
        $originalPublicPath = '/storage/uploads/gallery/' . $storageFolder . '/' . $fileName;
        $variants = $this->imageVariants->regenerateFromPublicPath($originalPublicPath, true);
        if ($variants === null) {
            @unlink($target);
            Logger::log('Failed to generate master gallery variants for user #' . (int)$user['id']);
            return $this->redirectError(__('users.master_works.error.save'));
        }
        $this->applyPhotoCopyright([
            APP_ROOT . $variants['path'],
            APP_ROOT . $variants['path_medium'],
            APP_ROOT . $variants['path_thumb'],
        ], $user);

        $cols = "path, path_medium, path_thumb, title_en, title_ru, description_en, description_ru, created_at, author_id, status, submitted_by_master, storage_folder";
        $vals = ":path, :path_medium, :path_thumb, :title_en, :title_ru, :description_en, :description_ru, NOW(), :author_id, :status, 1, :storage_folder";
        $params = [
            ':path' => $variants['path'],
            ':path_medium' => $variants['path_medium'],
            ':path_thumb' => $variants['path_thumb'],
            ':title_en' => $titleEn,
            ':title_ru' => $titleRu,
            ':description_en' => $descEn,
            ':description_ru' => $descRu,
            ':author_id' => (int)$user['id'],
            ':status' => $status,
            ':storage_folder' => $storageFolder,
        ];

        if ($this->hasGalleryColumn('slug')) {
            $cols = 'slug, ' . $cols;
            $vals = ':slug, ' . $vals;
            $params[':slug'] = $this->makeSlug($titleRu ?: $titleEn ?: ('work-' . time()));
        }
        if ($this->hasGalleryColumn('category_id') && !empty($categoryIds)) {
            $cols .= ', category_id';
            $vals .= ', :category_id';
            $params[':category_id'] = $categoryIds[0];
        }

        $this->db->execute("INSERT INTO gallery_items ({$cols}) VALUES ({$vals})", $params);
        $itemId = (int)$this->db->pdo()->lastInsertId();
        $this->tags->sync('gallery', $itemId, $this->tags->normalizeInput($request->body['tags'] ?? '', 7));
        $this->syncCategories($itemId, $categoryIds);
        if ($status === 'approved' && $this->hasGalleryColumn('approved_at')) {
            $this->db->execute("UPDATE gallery_items SET approved_at = NOW(), reviewed_at = NOW() WHERE id = ?", [$itemId]);
        }

        Logger::log('Master upload created: item #' . $itemId . ' by user #' . (int)$user['id'] . ' status=' . $status);
        return new Response('', 302, ['Location' => '/profile/works?msg=' . urlencode($status === 'approved' ? 'approved' : 'pending')]);
    }

    private function canUpload(array $user): bool
    {
        $settings = $this->masterSettings();
        if (empty($settings['master_uploads_enabled'])) {
            return false;
        }
        if (empty($user['is_master'])) {
            return false;
        }
        if (!empty($settings['verified_masters_only_upload']) && empty($user['is_verified'])) {
            return false;
        }
        return true;
    }

    private function masterFolderName(array $user): string
    {
        $username = strtolower(trim((string)($user['username'] ?? 'user')));
        $username = preg_replace('/[^a-z0-9_-]+/', '-', $username);
        $username = trim((string)$username, '-_');
        if ($username === '') {
            $username = 'user';
        }
        return 'u' . (int)$user['id'] . '-' . $username;
    }

    private function masterSettings(): array
    {
        $settings = $this->moduleSettings->all('users');
        return [
            'master_uploads_enabled' => array_key_exists('master_uploads_enabled', $settings) ? !empty($settings['master_uploads_enabled']) : true,
            'verified_masters_only_upload' => array_key_exists('verified_masters_only_upload', $settings) ? !empty($settings['verified_masters_only_upload']) : true,
            'master_gallery_moderation' => array_key_exists('master_gallery_moderation', $settings) ? !empty($settings['master_gallery_moderation']) : true,
        ];
    }

    private function applyPhotoCopyright(array $paths, array $user): void
    {
        if (empty($user['is_verified'])) {
            return;
        }
        if (empty($user['photo_copyright_enabled'])) {
            return;
        }
        $text = trim((string)($user['photo_copyright_text'] ?? ''));
        if ($text === '') {
            return;
        }

        $this->photoCopyrights->applyToFiles($paths, [
            'text' => $text,
            'font' => (string)($user['photo_copyright_font'] ?? 'oswald'),
            'color' => (string)($user['photo_copyright_color'] ?? PhotoCopyrightService::defaultColor()),
        ]);
    }

    private function resolveStatus(array $user): string
    {
        $settings = $this->masterSettings();
        if (empty($settings['master_gallery_moderation']) || $this->access->can($user, 'gallery.publish')) {
            return 'approved';
        }
        return 'pending';
    }

    private function galleryLimitExceeded(array $user): bool
    {
        $plan = $this->users->currentPlanForUser((int)$user['id']);
        $limit = (int)($plan['gallery_limit'] ?? 0);
        if ($limit < 1) {
            return false;
        }
        $statusSql = $this->hasGalleryColumn('status') ? " AND status IN ('pending','approved')" : '';
        $row = $this->db->fetch("SELECT COUNT(*) AS cnt FROM gallery_items WHERE author_id = ?{$statusSql}", [(int)$user['id']]);
        return (int)($row['cnt'] ?? 0) >= $limit;
    }

    private function syncCategories(int $itemId, array $categoryIds): void
    {
        if ($itemId < 1 || $categoryIds === []) {
            return;
        }
        $hasMap = (bool)$this->db->fetch("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_item_categories'");
        if (!$hasMap) {
            return;
        }
        $this->db->execute("DELETE FROM gallery_item_categories WHERE item_id = ?", [$itemId]);
        foreach ($categoryIds as $categoryId) {
            $this->db->execute("INSERT INTO gallery_item_categories (item_id, category_id) VALUES (?, ?)", [$itemId, $categoryId]);
        }
    }

    private function hasGalleryColumn(string $column): bool
    {
        static $cache = [];
        if (!array_key_exists($column, $cache)) {
            $cache[$column] = (bool)$this->db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$column]);
        }
        return $cache[$column];
    }

    private function makeSlug(string $value): string
    {
        $slug = SlugService::slugify($value, '');
        return $slug !== '' ? $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6) : 'work-' . time();
    }

    private function redirectError(string $message): Response
    {
        return new Response('', 302, ['Location' => '/profile/works?err=' . urlencode($message)]);
    }

    private function messageFromQuery(string $code): ?string
    {
        return match ($code) {
            'approved' => __('users.master_works.success.approved'),
            'pending' => __('users.master_works.success.pending'),
            default => null,
        };
    }
}
