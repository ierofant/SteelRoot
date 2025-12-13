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
        $router->get('/gallery/photo/{slug}', [Controllers\GalleryController::class, 'view']);
        // ЧПУ отключено по требованию: оставляем query /view?id=
        $router->get('/gallery/view', [Controllers\GalleryController::class, 'view']);
        $router->get('/tags/{slug}/gallery', [Controllers\GalleryController::class, 'byTag']);

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
            $r->get('/edit/{id}', [Controllers\UploadController::class, 'edit']);
            $r->post('/edit/{id}', [Controllers\UploadController::class, 'update']);
            $r->post('/delete/{id}', [Controllers\UploadController::class, 'delete']);
            $r->get('/settings', [Controllers\UploadController::class, 'settings']);
            $r->post('/settings', [Controllers\UploadController::class, 'saveSettings']);
        });
    }
}
