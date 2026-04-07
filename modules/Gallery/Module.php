<?php
namespace Modules\Gallery;

use Core\Container;
use Core\Router;

class Module
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register(Container $container, Router $router): void
    {
        $router->get('/gallery', [Controllers\GalleryController::class, 'index']);
        $router->get('/gallery/page/{page}', [Controllers\GalleryController::class, 'index']);
        $router->get('/gallery/category/{slug}/page/{page}', [Controllers\GalleryController::class, 'byCategory']);
        $router->get('/gallery/category/{slug}', [Controllers\GalleryController::class, 'byCategory']);
        $router->get('/gallery/share/{id}/{platform}', [Controllers\GalleryController::class, 'share']);
        $router->get('/gallery/photo/{slug}', [Controllers\GalleryController::class, 'view']);
        // ЧПУ отключено по требованию: оставляем query /view?id=
        $router->get('/gallery/view', [Controllers\GalleryController::class, 'view']);
        $router->get('/api/v1/tags/{slug}/gallery', [Controllers\GalleryController::class, 'tagApi']);
        $router->get('/tags/{slug}/gallery', [Controllers\GalleryController::class, 'byTag']);
        $router->post('/api/v1/gallery/master-like', [Controllers\MasterLikeController::class, 'store']);

        $config = $container->get('config');
        $adminPrefix = $config['admin_prefix'] ?? '/admin';
        $authMiddleware = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/gallery', [$authMiddleware], function (Router $r) {
            $r->get('/upload', [Controllers\UploadController::class, 'form']);
            $r->post('/upload', [Controllers\UploadController::class, 'upload']);
            $r->get('/tags', [Controllers\UploadController::class, 'tags']);
            $r->post('/tags/{id}/save', [Controllers\UploadController::class, 'saveTag']);
            $r->get('/edit/{id}', [Controllers\UploadController::class, 'edit']);
            $r->post('/edit/{id}', [Controllers\UploadController::class, 'update']);
            $r->post('/tags/{id}', [Controllers\UploadController::class, 'updateTags']);
            $r->post('/delete/{id}', [Controllers\UploadController::class, 'delete']);
            $r->post('/approve/{id}', [Controllers\UploadController::class, 'approve']);
            $r->post('/reject/{id}', [Controllers\UploadController::class, 'reject']);
            $r->get('/settings', [Controllers\UploadController::class, 'settings']);
            $r->post('/settings', [Controllers\UploadController::class, 'saveSettings']);
            $r->get('/categories', [Controllers\AdminGalleryCategoriesController::class, 'index']);
            $r->get('/categories/create', [Controllers\AdminGalleryCategoriesController::class, 'create']);
            $r->post('/categories/create', [Controllers\AdminGalleryCategoriesController::class, 'store']);
            $r->get('/categories/edit/{id}', [Controllers\AdminGalleryCategoriesController::class, 'edit']);
            $r->post('/categories/edit/{id}', [Controllers\AdminGalleryCategoriesController::class, 'update']);
            $r->post('/categories/delete/{id}', [Controllers\AdminGalleryCategoriesController::class, 'delete']);
        });
    }
}
