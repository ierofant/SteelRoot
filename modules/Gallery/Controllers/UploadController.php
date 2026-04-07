<?php
namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Database;
use Core\Csrf;
use Core\Logger;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use App\Services\SlugService;
use App\Services\SearchIndexService;
use App\Services\TagService;
use App\Services\SettingsService;
use Modules\Gallery\Services\GalleryCategoryService;
use Modules\Gallery\Services\GalleryImageVariantService;

class UploadController
{
    private Container $container;
    private Database $db;
    private string $uploadPath;
    private TagService $tags;
    private SettingsService $settings;
    private ModuleSettings $moduleSettings;
    private GalleryCategoryService $galleryCategories;
    private GalleryImageVariantService $imageVariants;
    private SearchIndexService $searchIndex;
    private string $localeMode;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->tags = new TagService($this->db);
        $this->settings = $container->get(SettingsService::class);
        $this->searchIndex = $container->get(SearchIndexService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->localeMode = $this->settings->get('locale_mode', 'multi');
        $this->moduleSettings->loadDefaults('gallery', [
            'show_title' => true,
            'show_description' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'enable_lightbox' => true,
            'lightbox_likes' => true,
            'thumb_width' => GalleryImageVariantService::DEFAULT_THUMB_WIDTH,
            'medium_width' => GalleryImageVariantService::DEFAULT_MEDIUM_WIDTH,
            'variants_format' => GalleryImageVariantService::DEFAULT_FORMAT,
        ]);
        $this->galleryCategories = new GalleryCategoryService($this->db, $container->get('cache'));
        $this->imageVariants = new GalleryImageVariantService($this->variantSettings());
        $this->uploadPath = APP_ROOT . '/storage/uploads/gallery';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
    }

    public function form(Request $request): Response
    {
        $page    = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $sort    = trim((string)($request->query['sort'] ?? 'new'));
        $status  = trim((string)($request->query['status'] ?? ''));
        $tag     = trim((string)($request->query['tag'] ?? ''));
        $offset  = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($this->hasColumn('status') && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where[] = 'gi.status = :status';
            $params[':status'] = $status;
        } else {
            $status = '';
        }

        if ($tag !== '') {
            $normalizedTags = $this->tags->normalizeInput($tag);
            if ($normalizedTags === []) {
                $where[] = '1 = 0';
            } else {
                $where[] = "EXISTS (
                    SELECT 1
                    FROM taggables tg
                    JOIN tags t ON t.id = tg.tag_id
                    WHERE tg.entity_type = 'gallery'
                      AND tg.entity_id = gi.id
                      AND t.slug = :tag_slug
                )";
                $params[':tag_slug'] = (string)$normalizedTags[0]['slug'];
            }
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';
        $orderSql = match ($sort) {
            'old' => 'gi.created_at ASC',
            'likes' => $this->hasColumn('likes_count') ? 'gi.likes_count DESC, gi.created_at DESC' : 'gi.created_at DESC',
            'views' => $this->hasColumn('views_count') ? 'gi.views_count DESC, gi.created_at DESC' : 'gi.created_at DESC',
            'category' => $this->hasColumn('category_id') ? 'gi.category_id ASC, gi.created_at DESC' : 'gi.created_at DESC',
            'tags' => 'tags_sort DESC, gi.created_at DESC',
            'title' => 'COALESCE(NULLIF(gi.title_ru, \'\'), gi.title_en) ASC, gi.created_at DESC',
            default => 'gi.created_at DESC',
        };
        $sort = in_array($sort, ['new', 'old', 'likes', 'views', 'category', 'tags', 'title'], true) ? $sort : 'new';

        $statusSelect = $this->hasColumn('status') ? ', status, status_note, submitted_by_master' : '';
        $totalRow = $this->db->fetch("SELECT COUNT(*) as cnt FROM gallery_items gi {$whereSql}", $params);
        $total    = (int)($totalRow['cnt'] ?? 0);
        $items    = $this->db->fetchAll(
            "SELECT
                gi.id,
                gi.title_en,
                gi.title_ru,
                gi.path,
                gi.path_medium,
                gi.path_thumb,
                gi.created_at{$statusSelect},
                (
                    SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ' ')
                    FROM taggables tg
                    JOIN tags t ON t.id = tg.tag_id
                    WHERE tg.entity_type = 'gallery' AND tg.entity_id = gi.id
                ) AS tags_sort
             FROM gallery_items gi
             {$whereSql}
             ORDER BY {$orderSql}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($items as &$item) {
            $item['tags_display'] = $this->tags->formatInput($this->tags->forEntity('gallery', (int)($item['id'] ?? 0)));
        }
        unset($item);
        $summary = $this->gallerySummary();
        $html = $this->container->get('renderer')->render('gallery/upload', [
            'title'      => 'Upload',
            'csrf'       => Csrf::token('gallery_upload'),
            'moderateToken' => Csrf::token('gallery_moderate'),
            'items'      => $items,
            'suggestedTags' => $this->tags->popular(40),
            'categories' => $this->galleryCategories->all(),
            'localeMode' => $this->localeMode,
            'folders'    => $this->scanFolders(),
            'page'       => $page,
            'total'      => $total,
            'perPage'    => $perPage,
            'sort'       => $sort,
            'status'     => $status,
            'tagFilter'  => $tag,
            'summary'    => $summary,
        ]);
        return new Response($html);
    }

    public function tags(Request $request): Response
    {
        $q = trim((string)($request->query['q'] ?? ''));
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $sort = trim((string)($request->query['sort'] ?? 'usage'));
        $dir = strtolower(trim((string)($request->query['dir'] ?? 'desc')));
        $sort = in_array($sort, ['usage', 'name', 'slug', 'id'], true) ? $sort : 'usage';
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';

        $params = [];
        $whereSql = '';
        if ($q !== '') {
            $whereSql = 'WHERE t.name LIKE :q OR t.slug LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }

        $totalRow = $this->db->fetch(
            "SELECT COUNT(*) AS cnt
             FROM (
                SELECT t.id
                FROM tags t
                LEFT JOIN taggables tg ON tg.tag_id = t.id
                {$whereSql}
                GROUP BY t.id
             ) tag_rows",
            $params
        );
        $total = (int)($totalRow['cnt'] ?? 0);

        $orderSql = match ($sort) {
            'name' => 't.name ' . strtoupper($dir) . ', usage_count DESC',
            'slug' => 't.slug ' . strtoupper($dir) . ', usage_count DESC',
            'id' => 't.id ' . strtoupper($dir),
            default => 'usage_count ' . strtoupper($dir) . ', t.name ASC',
        };

        $tags = $this->db->fetchAll(
            "SELECT
                t.id,
                t.name,
                t.slug,
                COUNT(tg.tag_id) AS usage_count
             FROM tags t
             LEFT JOIN taggables tg ON tg.tag_id = t.id
             {$whereSql}
             GROUP BY t.id, t.name, t.slug
             ORDER BY {$orderSql}
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $html = $this->container->get('renderer')->render('gallery/tags', [
            'title' => 'Gallery Tags',
            'csrf' => Csrf::token('gallery_tag_admin'),
            'tags' => $tags,
            'query' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
        ]);

        return new Response($html);
    }

    public function saveTag(Request $request): Response
    {
        if (!Csrf::check('gallery_tag_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $id = (int)($request->params['id'] ?? 0);
        $tag = $this->db->fetch("SELECT id, name, slug FROM tags WHERE id = ?", [$id]);
        if (!$tag) {
            return new Response('Not found', 404);
        }

        if (($request->body['delete'] ?? '') === '1') {
            $this->db->execute("DELETE FROM taggables WHERE tag_id = ?", [$id]);
            $this->db->execute("DELETE FROM tags WHERE id = ?", [$id]);
            $this->clearGalleryCache();

            $return = trim((string)($request->body['return'] ?? ''));
            $location = ($return !== '' && str_starts_with($return, $this->adminPrefix() . '/gallery/tags'))
                ? $return
                : ($this->adminPrefix() . '/gallery/tags');

            return new Response('', 302, ['Location' => $location]);
        }

        $name = trim((string)($request->body['name'] ?? ''));
        if ($name === '') {
            $name = (string)$tag['name'];
        }

        $slugInput = trim((string)($request->body['slug'] ?? ''));
        $slug = SlugService::slugify($slugInput !== '' ? $slugInput : $name, '');
        if ($slug === '') {
            return new Response('Invalid slug', 422);
        }

        $existing = $this->db->fetch("SELECT id FROM tags WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($existing) {
            $targetId = (int)$existing['id'];
            $this->db->execute("UPDATE tags SET name = ?, slug = ? WHERE id = ?", [$name, $slug, $targetId]);
            $this->db->execute(
                "INSERT INTO taggables (tag_id, entity_type, entity_id)
                 SELECT ?, source.entity_type, source.entity_id
                 FROM taggables source
                 WHERE source.tag_id = ?
                   AND NOT EXISTS (
                       SELECT 1
                       FROM taggables existing
                       WHERE existing.tag_id = ?
                         AND existing.entity_type = source.entity_type
                         AND existing.entity_id = source.entity_id
                   )",
                [$targetId, $id, $targetId]
            );
            $this->db->execute("DELETE FROM taggables WHERE tag_id = ?", [$id]);
            $this->db->execute("DELETE FROM tags WHERE id = ?", [$id]);
        } else {
            $this->db->execute("UPDATE tags SET name = ?, slug = ? WHERE id = ?", [$name, $slug, $id]);
        }

        $this->clearGalleryCache();

        $return = trim((string)($request->body['return'] ?? ''));
        $location = ($return !== '' && str_starts_with($return, $this->adminPrefix() . '/gallery/tags'))
            ? $return
            : ($this->adminPrefix() . '/gallery/tags');

        return new Response('', 302, ['Location' => $location]);
    }

    private function scanFolders(): array
    {
        $folders = [];
        if (!is_dir($this->uploadPath)) {
            return $folders;
        }
        foreach (scandir($this->uploadPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($this->uploadPath . '/' . $entry) && preg_match('/^[a-z0-9_\-]+$/i', $entry)) {
                $folders[] = $entry;
            }
        }
        sort($folders);
        return $folders;
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
            'thumb_width' => GalleryImageVariantService::DEFAULT_THUMB_WIDTH,
            'medium_width' => GalleryImageVariantService::DEFAULT_MEDIUM_WIDTH,
            'variants_format' => GalleryImageVariantService::DEFAULT_FORMAT,
            'seo_title_en' => '',
            'seo_title_ru' => '',
            'seo_desc_en' => '',
            'seo_desc_ru' => '',
        ];
        $merged = array_merge($defaults, $settings);
        $html = $this->container->get('renderer')->render('gallery/settings', [
            'title' => __('gallery.settings.page_title'),
            'csrf' => Csrf::token('gallery_settings'),
            'settings' => $merged,
            'flash' => (($request->query['msg'] ?? '') === 'saved') ? 'Saved' : null,
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
        $perPage = max(1, (int)($request->body['gallery_per_page'] ?? 9));
        $this->moduleSettings->set('gallery', 'per_page', $perPage);
        $thumbWidth = max(160, (int)($request->body['gallery_thumb_width'] ?? GalleryImageVariantService::DEFAULT_THUMB_WIDTH));
        $mediumWidth = max($thumbWidth, (int)($request->body['gallery_medium_width'] ?? GalleryImageVariantService::DEFAULT_MEDIUM_WIDTH));
        $variantsFormat = strtolower(trim((string)($request->body['gallery_variants_format'] ?? GalleryImageVariantService::DEFAULT_FORMAT)));
        if (!in_array($variantsFormat, ['source', 'webp'], true)) {
            $variantsFormat = GalleryImageVariantService::DEFAULT_FORMAT;
        }
        $this->moduleSettings->set('gallery', 'thumb_width', $thumbWidth);
        $this->moduleSettings->set('gallery', 'medium_width', $mediumWidth);
        $this->moduleSettings->set('gallery', 'variants_format', $variantsFormat);
        $this->moduleSettings->set('gallery', 'seo_title_en', trim((string)($request->body['gallery_seo_title_en'] ?? '')));
        $this->moduleSettings->set('gallery', 'seo_title_ru', trim((string)($request->body['gallery_seo_title_ru'] ?? '')));
        $this->moduleSettings->set('gallery', 'seo_desc_en', trim((string)($request->body['gallery_seo_desc_en'] ?? '')));
        $this->moduleSettings->set('gallery', 'seo_desc_ru', trim((string)($request->body['gallery_seo_desc_ru'] ?? '')));
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/settings?msg=saved']);
    }

    private function variantSettings(): array
    {
        $settings = $this->moduleSettings->all('gallery');

        return [
            'thumb_width' => $settings['thumb_width'] ?? GalleryImageVariantService::DEFAULT_THUMB_WIDTH,
            'medium_width' => $settings['medium_width'] ?? GalleryImageVariantService::DEFAULT_MEDIUM_WIDTH,
            'format' => $settings['variants_format'] ?? GalleryImageVariantService::DEFAULT_FORMAT,
        ];
    }

    private function gallerySummary(): array
    {
        $summary = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        $row = $this->db->fetch("SELECT COUNT(*) AS cnt FROM gallery_items");
        $summary['total'] = (int)($row['cnt'] ?? 0);

        if (!$this->hasColumn('status')) {
            $summary['approved'] = $summary['total'];
            return $summary;
        }

        $rows = $this->db->fetchAll("
            SELECT status, COUNT(*) AS cnt
            FROM gallery_items
            GROUP BY status
        ");
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int)($row['cnt'] ?? 0);
            }
        }

        return $summary;
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
        // Determine upload subfolder from explicit folder or primary category.
        $categoryIds = $this->normalizeCategoryIds($request->body['category_ids'] ?? []);
        $categoryId = $categoryIds[0] ?? null;
        $catSlug = '';
        if ($categoryId) {
            $catRow = $this->galleryCategories->find($categoryId);
            $catSlug = $catRow ? $catRow['slug'] : '';
        }
        $targetFolder = trim($request->body['target_folder'] ?? '');
        if ($targetFolder === '__new__' || $targetFolder === '') {
            $targetFolder = trim($request->body['target_folder_new'] ?? '');
        }
        if ($targetFolder !== '' && preg_match('/^[a-z0-9_\-]+$/i', $targetFolder)) {
            $subDir = '/' . $targetFolder;
        } else {
            $subDir = $catSlug ? '/' . $catSlug : '';
        }
        $uploadDir = $this->uploadPath . $subDir;
        if ($subDir && !is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $pathBase = '/storage/uploads/gallery' . $subDir;

        $ext = $allowed[$mime];
        $safeName = uniqid('g_', true) . '.' . $ext;
        $target = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            return new Response('Upload failed', 500);
        }
        // Basic dimension check (avoid huge images)
        [$w, $h] = @getimagesize($target) ?: [0, 0];
        if ($w > $maxW || $h > $maxH) {
            @unlink($target);
            return new Response('Image too large in dimensions', 400);
        }
        $originalPublicPath = $pathBase . '/' . $safeName;
        $variants = $this->imageVariants->regenerateFromPublicPath($originalPublicPath, true);
        if ($variants === null) {
            @unlink($target);
            return new Response('Failed to generate image variants', 500);
        }
        $cols = "path, path_medium, path_thumb, title_en, title_ru, description_en, description_ru, created_at";
        $vals = ":path, :path_medium, :path_thumb, :title_en, :title_ru, :description_en, :description_ru, NOW()";
        $params = [
            ':path' => $variants['path'],
            ':path_medium' => $variants['path_medium'],
            ':path_thumb' => $variants['path_thumb'],
            ':title_en' => trim($request->body['title_en'] ?? ''),
            ':title_ru' => trim($request->body['title_ru'] ?? ''),
            ':description_en' => trim($request->body['description_en'] ?? ''),
            ':description_ru' => trim($request->body['description_ru'] ?? ''),
        ];
        if ($this->hasColumn('slug')) {
            $cols = "slug, " . $cols;
            $vals = ":slug, " . $vals;
            $params[':slug'] = $this->resolveRequestedSlug(
                (string)($request->body['slug'] ?? ''),
                $params[':title_ru'] ?: $params[':title_en']
            );
        }
        if ($this->hasColumn('category_id')) {
            $cols .= ", category_id";
            $vals .= ", :category_id";
            $params[':category_id'] = $categoryId;
        } elseif ($this->hasColumn('category')) {
            $cols .= ", category";
            $vals .= ", :category";
            $params[':category'] = $catSlug ?: trim($request->body['category'] ?? '');
        }
        if ($this->hasColumn('author_id')) {
            $cols .= ", author_id";
            $vals .= ", :author_id";
            $params[':author_id'] = $this->currentAuthorId();
        }
        if ($this->hasColumn('status')) {
            $cols .= ", status";
            $vals .= ", :status";
            $params[':status'] = 'approved';
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
        $this->syncItemCategories($itemId, $categoryIds, $categoryId);
        $this->tags->sync('gallery', $itemId, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->searchIndex->upsertGalleryItem($itemId);
        $this->clearGalleryCache();
        header('Location: ' . $this->adminPrefix() . '/gallery/upload');
        exit;
    }

    private function clearGalleryCache(): void
    {
        $cache = $this->container->get('cache');
        $cache->clear();
    }

    public function updateTags(Request $request): Response
    {
        if (!Csrf::check('gallery_upload', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT id FROM gallery_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }

        $this->tags->sync('gallery', $id, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->clearGalleryCache();

        return new Response('', 302, ['Location' => $this->resolveUploadReturnUrl($request)]);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->db->fetch("SELECT * FROM gallery_items WHERE id = ?", [$id]);
        if (!$item) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('gallery/edit', [
            'title'      => 'Edit Image',
            'item'       => $item,
            'csrf'       => Csrf::token('gallery_edit'),
            'tags'       => $this->tags->forEntity('gallery', $id),
            'tagsInput'  => $this->tags->formatInput($this->tags->forEntity('gallery', $id)),
            'suggestedTags' => $this->tags->popular(40),
            'categories' => $this->galleryCategories->all(),
            'selectedCategoryIds' => $this->selectedCategoryIds($id, $item),
            'returnUrl' => $this->resolveUploadReturnUrlFromQuery($request),
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
        $categoryIds = $this->normalizeCategoryIds($request->body['category_ids'] ?? []);
        $categoryId = $categoryIds[0] ?? null;
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
            $currentSlug = trim((string)($item['slug'] ?? ''));
            $requestedSlug = trim((string)($request->body['slug'] ?? ''));
            $params[':slug'] = $requestedSlug !== ''
                ? $this->resolveRequestedSlug($requestedSlug, 'photo-' . $id, $id)
                : ($currentSlug !== '' ? $currentSlug : $this->resolveRequestedSlug('', $titleRu ?: $titleEn ?: ('photo-' . $id), $id));
        }
        $catSql = '';
        if ($this->hasColumn('category_id')) {
            $catSql = ", category_id = :cat_id";
            $params[':cat_id'] = $categoryId;
        } elseif ($this->hasColumn('category')) {
            $catSql = ", category = :cat";
            $params[':cat'] = trim($request->body['category'] ?? '');
        }
        $this->db->execute("
            UPDATE gallery_items
            SET title_en = :ten, title_ru = :tru, description_en = :den, description_ru = :dru{$slugSql}{$catSql}
            WHERE id = :id
        ", $params);
        $this->syncItemCategories($id, $categoryIds, $categoryId);
        $this->tags->sync('gallery', $id, $this->tags->normalizeInput($request->body['tags'] ?? ''));
        $this->searchIndex->upsertGalleryItem($id);
        $this->clearGalleryCache();
        return new Response('', 302, ['Location' => $this->resolveUploadReturnUrl($request)]);
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
            if (is_file($path)) {
                @unlink($path);
            }
            $this->imageVariants->deleteDerivedFiles(
                (string)($item['path_medium'] ?? ''),
                (string)($item['path_thumb'] ?? ''),
                (string)($item['path'] ?? '')
            );
            if ($this->tableExists('taggables')) {
                $this->db->execute("DELETE FROM taggables WHERE entity_type IN ('gallery','gallery_item','image') AND entity_id = ?", [$id]);
            }
            if ($this->tableExists('gallery_item_categories')) {
                $this->db->execute("DELETE FROM gallery_item_categories WHERE item_id = ?", [$id]);
            }
            if ($this->tableExists('master_gallery_likes')) {
                $this->db->execute("DELETE FROM master_gallery_likes WHERE gallery_item_id = ?", [$id]);
            }
            if ($this->tableExists('likes')) {
                $this->db->execute("DELETE FROM likes WHERE entity_type = 'gallery' AND entity_id = ?", [$id]);
            }
            if ($this->tableExists('comments')) {
                $this->db->execute("DELETE FROM comments WHERE entity_type = 'gallery' AND entity_id = ?", [$id]);
            }
            $this->searchIndex->deleteEntity('gallery', $id);
            $this->db->execute("DELETE FROM gallery_items WHERE id = ?", [$id]);
        }
        $this->clearGalleryCache();
        return new Response('', 302, ['Location' => $this->resolveUploadReturnUrl($request)]);
    }

    public function approve(Request $request): Response
    {
        if (!Csrf::check('gallery_moderate', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        if ($id > 0 && $this->hasColumn('status')) {
            $this->db->execute("
                UPDATE gallery_items
                SET status = 'approved',
                    status_note = NULL,
                    reviewed_at = NOW(),
                    approved_at = NOW()
                WHERE id = ?
            ", [$id]);
            Logger::log('Gallery item approved #' . $id);
            $this->clearGalleryCache();
        }
        return new Response('', 302, ['Location' => $this->resolveUploadReturnUrl($request, 'approved')]);
    }

    public function reject(Request $request): Response
    {
        if (!Csrf::check('gallery_moderate', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        if ($id > 0 && $this->hasColumn('status')) {
            $note = trim((string)($request->body['status_note'] ?? ''));
            $this->db->execute("
                UPDATE gallery_items
                SET status = 'rejected',
                    status_note = ?,
                    reviewed_at = NOW()
                WHERE id = ?
            ", [$note !== '' ? $note : null, $id]);
            Logger::log('Gallery item rejected #' . $id);
            $this->clearGalleryCache();
        }
        return new Response('', 302, ['Location' => $this->resolveUploadReturnUrl($request, 'rejected')]);
    }

    private function resolveUploadReturnUrl(Request $request, ?string $message = null): string
    {
        $return = trim((string)($request->body['return'] ?? ''));
        if ($return !== '' && str_starts_with($return, $this->adminPrefix() . '/gallery/upload')) {
            if ($message === null || $message === '') {
                return $return;
            }

            $parts = parse_url($return);
            $query = [];
            if (!empty($parts['query'])) {
                parse_str((string)$parts['query'], $query);
            }
            $query['msg'] = $message;

            return ($parts['path'] ?? ($this->adminPrefix() . '/gallery/upload')) . '?' . http_build_query($query);
        }

        return $this->adminPrefix() . '/gallery/upload' . ($message ? ('?msg=' . urlencode($message)) : '');
    }

    private function resolveUploadReturnUrlFromQuery(Request $request): string
    {
        $return = trim((string)($request->query['return'] ?? ''));
        if ($return !== '' && str_starts_with($return, $this->adminPrefix() . '/gallery/upload')) {
            return $return;
        }

        return $this->adminPrefix() . '/gallery/upload';
    }

    private function normalizeCategoryIds($raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function selectedCategoryIds(int $itemId, array $item): array
    {
        if ($itemId < 1) {
            return [];
        }
        if ($this->tableExists('gallery_item_categories')) {
            $rows = $this->db->fetchAll("SELECT category_id FROM gallery_item_categories WHERE item_id = ? ORDER BY category_id ASC", [$itemId]);
            $ids = array_values(array_filter(array_map(static fn (array $row): int => (int)($row['category_id'] ?? 0), $rows)));
            if ($ids !== []) {
                return $ids;
            }
        }

        $fallback = (int)($item['category_id'] ?? 0);
        return $fallback > 0 ? [$fallback] : [];
    }

    private function syncItemCategories(int $itemId, array $categoryIds, ?int $fallbackCategoryId = null): void
    {
        if ($itemId < 1 || !$this->tableExists('gallery_item_categories')) {
            return;
        }

        $this->db->execute("DELETE FROM gallery_item_categories WHERE item_id = ?", [$itemId]);
        $sourceIds = $categoryIds !== [] ? $categoryIds : ($fallbackCategoryId ? [$fallbackCategoryId] : []);
        foreach ($sourceIds as $categoryId) {
            $this->db->execute("INSERT INTO gallery_item_categories (item_id, category_id) VALUES (?, ?)", [$itemId, $categoryId]);
        }
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

    private function tableExists(string $name): bool
    {
        static $cache = [];
        if (!array_key_exists($name, $cache)) {
            $row = $this->db->fetch("SHOW TABLES LIKE ?", [$name]);
            $cache[$name] = $row ? true : false;
        }
        return $cache[$name];
    }

    private function makeSlug(string $title, ?int $ignoreId = null): string
    {
        $candidate = SlugService::slugify($title, '');
        if ($candidate === '') {
            return '';
        }
        $base = $candidate;
        $counter = 0;
        while ($this->slugExists($candidate, $ignoreId)) {
            $counter++;
            if ($ignoreId !== null) {
                $candidate = $counter === 1
                    ? $base . '-' . $ignoreId
                    : $base . '-' . $ignoreId . '-' . $counter;
                continue;
            }
            $candidate = $base . '-' . $counter;
        }
        return $candidate;
    }

    private function resolveRequestedSlug(string $requestedSlug, string $fallbackTitle, ?int $ignoreId = null): string
    {
        $base = trim($requestedSlug) !== '' ? trim($requestedSlug) : $fallbackTitle;
        return $this->makeSlug($base, $ignoreId);
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
