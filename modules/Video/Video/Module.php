<?php
namespace Modules\Video;

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
        $router->get('/videos',                              [Controllers\VideoController::class, 'index']);
        $router->get('/videos/page/{page}',                  [Controllers\VideoController::class, 'index']);
        $router->get('/videos/category/{category}',          [Controllers\VideoController::class, 'index']);
        $router->get('/videos/category/{category}/page/{page}', [Controllers\VideoController::class, 'index']);
        $router->get('/videos/{category}/{slug}',            [Controllers\VideoController::class, 'viewByCategory']);

        $config      = $container->get('config');
        $adminPrefix = $config['admin_prefix'] ?? '/admin';

        $auth = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/videos', [$auth], function (Router $r) {
            $r->get('/',              [Controllers\AdminVideoController::class, 'index']);
            $r->get('/create',        [Controllers\AdminVideoController::class, 'create']);
            $r->post('/create',       [Controllers\AdminVideoController::class, 'store']);
            $r->get('/edit/{id}',     [Controllers\AdminVideoController::class, 'edit']);
            $r->post('/edit/{id}',    [Controllers\AdminVideoController::class, 'update']);
            $r->post('/delete/{id}',  [Controllers\AdminVideoController::class, 'delete']);
            $r->get('/categories',             [Controllers\AdminVideoController::class, 'categories']);
            $r->get('/categories/create',      [Controllers\AdminVideoController::class, 'categoriesCreate']);
            $r->post('/categories/create',     [Controllers\AdminVideoController::class, 'categoriesStore']);
            $r->get('/categories/edit/{id}',   [Controllers\AdminVideoController::class, 'categoriesEdit']);
            $r->post('/categories/edit/{id}',  [Controllers\AdminVideoController::class, 'categoriesUpdate']);
            $r->post('/categories/delete/{id}', [Controllers\AdminVideoController::class, 'categoriesDelete']);
        });
    }
}
