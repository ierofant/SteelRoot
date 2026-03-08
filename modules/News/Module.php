<?php
namespace Modules\News;
use Core\Container;
use Core\Router;

class Module {
    public function __construct(private string $path) {}

    public function register(Container $container, Router $router): void
    {
        $router->get('/news',                              [Controllers\NewsController::class, 'index']);
        $router->get('/news/page/{page}',                  [Controllers\NewsController::class, 'index']);
        $router->get('/news/category/{slug}',              [Controllers\NewsController::class, 'byCategory']);
        $router->get('/news/category/{slug}/page/{page}',  [Controllers\NewsController::class, 'byCategory']);
        $router->get('/news/{slug}',                       [Controllers\NewsController::class, 'view']);

        $auth = fn($req, $next) => empty($_SESSION['admin_auth']) ? (header('Location: /admin/login') ?: exit()) : $next($req);
        $router->group('/admin/news', [$auth], function (Router $r) {
            $r->get('/',              [Controllers\AdminNewsController::class, 'index']);
            $r->get('/create',        [Controllers\AdminNewsController::class, 'create']);
            $r->post('/create',       [Controllers\AdminNewsController::class, 'store']);
            $r->get('/edit/{slug}',   [Controllers\AdminNewsController::class, 'edit']);
            $r->post('/edit/{slug}',  [Controllers\AdminNewsController::class, 'update']);
            $r->post('/delete/{slug}',[Controllers\AdminNewsController::class, 'delete']);
            $r->get('/settings', [Controllers\AdminNewsController::class, 'settings']);
            $r->post('/settings', [Controllers\AdminNewsController::class, 'saveSettings']);
            $r->get('/categories', [Controllers\AdminNewsCategoriesController::class, 'index']);
            $r->get('/categories/create', [Controllers\AdminNewsCategoriesController::class, 'create']);
            $r->post('/categories/create', [Controllers\AdminNewsCategoriesController::class, 'store']);
            $r->get('/categories/edit/{id}', [Controllers\AdminNewsCategoriesController::class, 'edit']);
            $r->post('/categories/edit/{id}', [Controllers\AdminNewsCategoriesController::class, 'update']);
            $r->post('/categories/delete/{id}', [Controllers\AdminNewsCategoriesController::class, 'delete']);
        });
    }
}
