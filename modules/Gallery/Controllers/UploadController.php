<?php
namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Database;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use App\Services\TagService;
use App\Services\SettingsService;

class UploadController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private TagService $tags;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
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

    public function form(Request $request): Response
    {
        $items = $this->db->fetchAll("SELECT id, title_en, title_ru, path_thumb, created_at FROM gallery_items ORDER BY created_at DESC LIMIT 30");
        $html = $this->container->get('renderer')->render('gallery/upload', [
            'title' => 'Upload',
            'csrf' => Csrf::token('gallery_upload'),
            'items' => $items,
            'categories' => $this->getCategories(),
        ]);
        return new Response($html);
    }

    public function settings(Request $request): Response
    {
        $settings = $this->moduleSettings->all('gallery');
        $defaults = [
            'show_title' => true,
            'show_description' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'enable_lightbox' => true,
            'lightbox_likes' => true,
        ];
        $merged = array_merge($defaults, $settings);
        $html = $this->container->get('renderer')->render('gallery/settings', [
            'title' => __('gallery.settings.page_title'),
            'csrf' => Csrf::token('gallery_settings'),
            'settings' => $merged,
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('gallery_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $map = [
            'show_title' => 'gallery_show_title',
            'show_description' => 'gallery_show_description',
            'show_likes' => 'gallery_show_likes',
            'show_views' => 'gallery_show_views',
            'show_tags' => 'gallery_show_tags',
            'enable_lightbox' => 'gallery_enable_lightbox',
            'lightbox_likes' => 'gallery_lightbox_likes',
        ];
        foreach ($map as $key => $field) {
            $val = !empty($request->body[$field]);
            $this->moduleSettings->set('gallery', $key, $val);
        }
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/settings?msg=saved']);
    }

    public function upload(Request $request): Response
    {
        if (!Csrf::check('gallery_upload', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        if (empty($_FILES['image']['tmp_name'])) {
            return new Response('No file', 400);
        }
        $cfgLimits = $this->container->get('config')['limits'] ?? [];
        $maxSize = (int)($this->settings->get('upload_max_bytes', $cfgLimits['upload_max_bytes'] ?? (5 * 1024 * 1024)));
        $maxW = (int)($this->settings->get('upload_max_width', $cfgLimits['upload_max_width'] ?? 8000));
        $maxH = (int)($this->settings->get('upload_max_height', $cfgLimits['upload_max_height'] ?? 8000));
        if ($_FILES['image']['size'] > $maxSize) {
            return new Response('File too large (max 5MB)', 400);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return new Response('Unsupported file type', 400);
        }
        $ext = $allowed[$mime];
        $safeName = uniqid('g_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $safeName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            return new Response('Upload failed', 500);
        }
        // Basic dimension check (avoid huge images)
        [$w, $h] = @getimagesize($target) ?: [0, 0];
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return new Response('Image too large in dimensions', 400);
        }
        $mediumName = preg_replace('/\.[^.]+$/', '_m.' . $ext, $safeName);
        $thumbName = preg_replace('/\.[^.]+$/', '_t.' . $ext, $safeName);
        $mediumPath = $this->uploadPath . '/' . $mediumName;
        $thumbPath = $this->uploadPath . '/' . $thumbName;
        $this->resizeCopy($target, $mediumPath, 1200);
        $this->resizeCopy($target, $thumbPath, 360);
        $cols = "path, path_medium, path_thumb, title_en, title_ru, description_en, description_ru, created_at";
        $vals = ":path, :path_medium, :path_thumb, :title_en, :title_ru, :description_en, :description_ru, NOW()";
        $params = [
            ':path' => '/storage/uploads/gallery/' . $safeName,
            ':path_medium' => '/storage/uploads/gallery/' . $mediumName,
            ':path_thumb' => '/storage/uploads/gallery/' . $thumbName,
            ':title_en' => trim($request->body['title_en'] ?? ''),
            ':title_ru' => trim($request->body['title_ru'] ?? ''),
            ':description_en' => trim($request->body['description_en'] ?? ''),
            ':description_ru' => trim($request->body['description_ru'] ?? ''),
        ];
        if ($this->hasColumn('slug')) {
            $cols = "slug, " . $cols;
            $vals = ":slug, " . $vals;
            $params[':slug'] = $this->makeSlug($params[':title_ru'] ?: $params[':title_en']);
        }
        if ($this->hasColumn('category')) {
            $cols .= ", category";
            $vals .= ", :category";
            $params[':category'] = trim($request->body['category'] ?? '');
        }
        if ($this->hasColumn('author_id')) {
            $cols .= ", author_id";
            $vals .= ", :author_id";
            $params[':author_id'] = $this->currentAuthorId();
        }
        $this->db->execute("
            INSERT INTO gallery_items ({$cols})
            VALUES ({$vals})
        ", $params);
        $itemId = (int)$this->db->pdo()->lastInsertId();
        if ($this->hasColumn('slug') && ($params[':slug'] ?? '') === '') {
            $slug = $this->makeSlug('photo-' . $itemId, $itemId);
            $this->db->execute("UPDATE gallery_items SET slug = ? WHERE id = ?", [$slug, $itemId]);
        }
        $this->tags->sync('gallery', $itemId, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->clearGalleryCache();
        header('Location: ' . $this->adminPrefix() . '/gallery/upload');
        exit;
    }

    private function clearGalleryCache(): void
    {
        $cache = $this->container->get('cache');
        $cache->clear();
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('gallery/edit', [
            'title' => 'Edit Image',
            'item' => $item,
            'csrf' => Csrf::token('gallery_edit'),
            'tags' => $this->tags->forEntity('gallery', $id),
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('gallery_edit', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $titleEn = trim($request->body['title_en'] ?? '');
        $titleRu = trim($request->body['title_ru'] ?? '');
        $descEn = trim($request->body['description_en'] ?? '');
        $descRu = trim($request->body['description_ru'] ?? '');
        $category = trim($request->body['category'] ?? '');
        $params = [
            ':ten' => $titleEn,
            ':tru' => $titleRu,
            ':den' => $descEn,
            ':dru' => $descRu,
            ':id' => $id,
        ];
        $slugSql = '';
        if ($this->hasColumn('slug')) {
            $slugSql = ", slug = :slug";
            $params[':slug'] = $this->makeSlug($titleRu ?: $titleEn ?: ('photo-' . $id), $id);
        }
        $catSql = '';
        if ($this->hasColumn('category')) {
            $catSql = ", category = :cat";
            $params[':cat'] = $category;
        }
        $this->db->execute("
            UPDATE gallery_items
            SET title_en = :ten, title_ru = :tru, description_en = :den, description_ru = :dru{$slugSql}{$catSql}
            WHERE id = :id
        ", $params);
        $this->tags->sync('gallery', $id, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->clearGalleryCache();
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/upload']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('gallery_edit', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if ($item) {
            $path = APP_ROOT . $item['path'];
            $m = $item['path_medium'] ?? null;
            $t = $item['path_thumb'] ?? null;
            if (is_file($path)) {
                @unlink($path);
            }
            if ($m && is_file(APP_ROOT . $m)) {
                @unlink(APP_ROOT . $m);
            }
            if ($t && is_file(APP_ROOT . $t)) {
                @unlink(APP_ROOT . $t);
            }
            $this->db->execute("DELETE FROM gallery_items WHERE id = ?", [$id]);
        }
        $this->clearGalleryCache();
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/upload']);
    }

    private function resizeCopy(string $src, string $dest, int $maxWidth): void
    {
        $info = @getimagesize($src);
        if (!$info) {
            @copy($src, $dest);
            return;
        }
        [$w, $h, $type] = $info;
        if ($w <= $maxWidth) {
            @copy($src, $dest);
            return;
        }
        $newW = $maxWidth;
        $newH = (int)round($h * ($newW / $w));
        $dst = imagecreatetruecolor($newW, $newH);
        switch ($type) {
            case IMAGETYPE_JPEG:
                $srcImg = imagecreatefromjpeg($src);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagejpeg($dst, $dest, 85);
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_PNG:
                $srcImg = imagecreatefrompng($src);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagepng($dst, $dest, 6);
                imagedestroy($srcImg);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $srcImg = imagecreatefromwebp($src);
                    imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);
                    imagewebp($dst, $dest, 85);
                    imagedestroy($srcImg);
                } else {
                    @copy($src, $dest);
                }
                break;
            default:
                @copy($src, $dest);
        }
        imagedestroy($dst);
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
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
    }

    private function getCategories(): array
    {
        if (!$this->hasColumn('category')) {
            return [];
        }
        $rows = $this->db->fetchAll("SELECT DISTINCT category FROM gallery_items WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
        return array_values(array_filter(array_map(fn($r) => $r['category'] ?? '', $rows)));
    }

    private function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $normalized = $this->tags->normalizeInput($title);
        if (!empty($normalized[0]['slug'])) {
            $candidate = $normalized[0]['slug'];
        } else {
            $candidate = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($title));
            $candidate = trim($candidate, '-');
        }
        if ($candidate === '') {
            return '';
        }
        $base = $candidate;
        $counter = 1;
        while ($this->slugExists($candidate, $ignoreId)) {
            $suffix = $ignoreId !== null ? (string)$ignoreId : (string)$counter;
            $candidate = $base . '-' . $suffix;
            $counter++;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId !== null) {
            $row = $this->db->fetch("SELECT id FROM gallery_items WHERE slug = ? AND id != ?", [$slug, $ignoreId]);
        } else {
            $row = $this->db->fetch("SELECT id FROM gallery_items WHERE slug = ?", [$slug]);
        }
        return (bool)$row;
    }

    private function adminPrefix(): string
    {
        $cfg = $this->container->get('config');
        return $cfg['admin_prefix'] ?? '/admin';
    }
}
