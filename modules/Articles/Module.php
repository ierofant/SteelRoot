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
        $adminPrefix = $container->get('config')['admin_prefix'] ?? '/admin';
        $router->get('/articles', [Controllers\ArticlesController::class, 'index']);
        $router->get('/articles/page/{page}', [Controllers\ArticlesController::class, 'index']);
        $router->get('/articles/category/{slug}/page/{page}', [Controllers\ArticlesController::class, 'byCategory']);
        $router->get('/articles/category/{slug}', [Controllers\ArticlesController::class, 'byCategory']);
        $router->get('/articles/{slug}', [Controllers\ArticlesController::class, 'view']);
        $router->get('/tags/{slug}', [Controllers\ArticlesController::class, 'byTag']);

        $authMiddleware = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: ' . (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/articles', [$authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\AdminArticlesController::class, 'index']);
            $r->get('/create', [Controllers\AdminArticlesController::class, 'create']);
            $r->post('/create', [Controllers\AdminArticlesController::class, 'store']);
            $r->get('/edit/{slug}', [Controllers\AdminArticlesController::class, 'edit']);
            $r->post('/edit/{slug}', [Controllers\AdminArticlesController::class, 'update']);
            $r->post('/delete/{slug}', [Controllers\AdminArticlesController::class, 'delete']);
            $r->get('/settings', [Controllers\AdminArticlesController::class, 'settings']);
            $r->post('/settings', [Controllers\AdminArticlesController::class, 'saveSettings']);
            $r->get('/categories', [Controllers\AdminArticleCategoriesController::class, 'index']);
            $r->get('/categories/create', [Controllers\AdminArticleCategoriesController::class, 'create']);
            $r->post('/categories/create', [Controllers\AdminArticleCategoriesController::class, 'store']);
            $r->get('/categories/edit/{id}', [Controllers\AdminArticleCategoriesController::class, 'edit']);
            $r->post('/categories/edit/{id}', [Controllers\AdminArticleCategoriesController::class, 'update']);
            $r->post('/categories/delete/{id}', [Controllers\AdminArticleCategoriesController::class, 'delete']);
        });
    }
}
