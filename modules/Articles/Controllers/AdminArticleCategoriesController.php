<?php
namespace Modules\Articles\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use Modules\Articles\Services\ArticleCategoryService;
use App\Services\SettingsService;

class AdminArticleCategoriesController
{
    private Container $container;
    private ArticleCategoryService $service;
    private string $uploadPath;
    private string $localeMode;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->service = new ArticleCategoryService($container->get(Database::class));
        $this->uploadPath = APP_ROOT . '/storage/uploads/articles/categories';
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0775, true);
        }
        $settings = new SettingsService($container->get(Database::class));
        $this->localeMode = $settings->get('locale_mode', 'multi');
    }

    public function index(Request $request): Response
    {
        $categories = $this->service->all();
        $html = $this->container->get('renderer')->render('articles/admin/categories', [
            'title'      => 'Article Categories',
            'mode'       => 'list',
            'categories' => $categories,
            'csrf'       => Csrf::token('articles_admin'),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('articles/admin/categories', [
            'title'      => 'New Category',
            'mode'       => 'create',
            'category'   => null,
            'csrf'       => Csrf::token('articles_admin'),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('articles_admin', $request->body['_token'] ?? null)) {
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
            'image_url' => $imageUrl,
            'position'  => (int)($request->body['position'] ?? 0),
            'enabled'   => !empty($request->body['enabled']),
        ]);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles/categories']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $category = $this->service->find($id);
        if (!$category) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('articles/admin/categories', [
            'title'      => 'Edit Category',
            'mode'       => 'edit',
            'category'   => $category,
            'csrf'       => Csrf::token('articles_admin'),
            'localeMode' => $this->localeMode,
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('articles_admin', $request->body['_token'] ?? null)) {
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
            'image_url' => $imageUrl,
            'position'  => (int)($request->body['position'] ?? 0),
            'enabled'   => !empty($request->body['enabled']),
        ]);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles/categories']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('articles_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->service->delete($id);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/articles/categories']);
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
        $ext = $allowed[$mime];
        $name = uniqid('artcat_', true) . '.' . $ext;
        $target = $this->uploadPath . '/' . $name;
        if (!move_uploaded_file($request->files['image']['tmp_name'], $target)) {
            return $existing;
        }
        return '/storage/uploads/articles/categories/' . $name;
    }
}
