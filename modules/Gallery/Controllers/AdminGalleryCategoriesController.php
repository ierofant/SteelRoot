<?php
namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;
use Modules\Gallery\Services\GalleryCategoryService;

class AdminGalleryCategoriesController
{
    private Container $container;
    private GalleryCategoryService $service;
    private string $uploadPath;
    private string $localeMode;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->service = new GalleryCategoryService($container->get(Database::class), $container->get('cache'));
        $this->localeMode = $container->get(SettingsService::class)->get('locale_mode', 'multi');
        $this->uploadPath = APP_ROOT . '/storage/uploads/gallery/categories';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
    }

    public function index(Request $request): Response
    {
        $categories = $this->service->all();
        $html = $this->container->get('renderer')->render('gallery/admin/categories', [
            'title'      => 'Gallery Categories',
            'mode'       => 'list',
            'categories' => $categories,
            'csrf'       => Csrf::token('gallery_admin'),
            'adminPrefix' => $this->adminPrefix(),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('gallery/admin/categories', [
            'title'    => 'New Category',
            'mode'     => 'create',
            'category' => null,
            'csrf'     => Csrf::token('gallery_admin'),
            'adminPrefix' => $this->adminPrefix(),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('gallery_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $nameEn = trim($request->body['name_en'] ?? '');
        $nameRu = trim($request->body['name_ru'] ?? '');
        if ($nameEn === '' && $nameRu === '') {
            return new Response('Name is required', 422);
        }
        $imageUrl = $this->handleUpload($request, trim($request->body['image_url'] ?? '') ?: '');
        $this->service->create([
            'slug'      => trim($request->body['slug'] ?? '') ?: ($nameEn ?: $nameRu),
            'name_en'   => $nameEn,
            'name_ru'   => $nameRu,
            'meta_title_en' => trim((string)($request->body['meta_title_en'] ?? '')),
            'meta_title_ru' => trim((string)($request->body['meta_title_ru'] ?? '')),
            'image_url' => $imageUrl,
            'meta_description_en' => trim((string)($request->body['meta_description_en'] ?? '')),
            'meta_description_ru' => trim((string)($request->body['meta_description_ru'] ?? '')),
            'position'  => (int)($request->body['position'] ?? 0),
            'enabled'   => !empty($request->body['enabled']),
        ]);
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/categories']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $category = $this->service->find($id);
        if (!$category) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('gallery/admin/categories', [
            'title'    => 'Edit Category',
            'mode'     => 'edit',
            'category' => $category,
            'csrf'     => Csrf::token('gallery_admin'),
            'adminPrefix' => $this->adminPrefix(),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('gallery_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $existing = $this->service->find($id);
        $nameEn = trim($request->body['name_en'] ?? '');
        $nameRu = trim($request->body['name_ru'] ?? '');
        $imageUrl = $this->handleUpload($request, trim($request->body['image_url'] ?? '') ?: ($existing['image_url'] ?? ''));
        $this->service->update($id, [
            'slug'      => trim($request->body['slug'] ?? '') ?: ($nameEn ?: $nameRu),
            'name_en'   => $nameEn,
            'name_ru'   => $nameRu,
            'meta_title_en' => trim((string)($request->body['meta_title_en'] ?? '')),
            'meta_title_ru' => trim((string)($request->body['meta_title_ru'] ?? '')),
            'image_url' => $imageUrl,
            'meta_description_en' => trim((string)($request->body['meta_description_en'] ?? '')),
            'meta_description_ru' => trim((string)($request->body['meta_description_ru'] ?? '')),
            'position'  => (int)($request->body['position'] ?? 0),
            'enabled'   => !empty($request->body['enabled']),
        ]);
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/categories']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('gallery_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->service->delete($id);
        return new Response('', 302, ['Location' => $this->adminPrefix() . '/gallery/categories']);
    }

    private function handleUpload(Request $request, string $existing = ''): string
    {
        if (empty($request->files['image']['tmp_name'])) {
            return $existing;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($request->files['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            return $existing;
        }
        $name = uniqid('gcat_', true) . '.' . $allowed[$mime];
        $target = $this->uploadPath . '/' . $name;
        if (!move_uploaded_file($request->files['image']['tmp_name'], $target)) {
            return $existing;
        }
        return '/storage/uploads/gallery/categories/' . $name;
    }

    private function adminPrefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }
}
