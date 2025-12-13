<?php
namespace Modules\Articles;

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
        $router->get('/articles', [Controllers\ArticlesController::class, 'index']);
        $router->get('/articles/{slug}', [Controllers\ArticlesController::class, 'view']);
        $router->get('/tags/{slug}', [Controllers\ArticlesController::class, 'byTag']);

        $authMiddleware = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->group('/admin/articles', [$authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\AdminArticlesController::class, 'index']);
            $r->get('/create', [Controllers\AdminArticlesController::class, 'create']);
            $r->post('/create', [Controllers\AdminArticlesController::class, 'store']);
            $r->get('/edit/{slug}', [Controllers\AdminArticlesController::class, 'edit']);
            $r->post('/edit/{slug}', [Controllers\AdminArticlesController::class, 'update']);
            $r->post('/delete/{slug}', [Controllers\AdminArticlesController::class, 'delete']);
            $r->get('/settings', [Controllers\AdminArticlesController::class, 'settings']);
            $r->post('/settings', [Controllers\AdminArticlesController::class, 'saveSettings']);
        });
    }
}
