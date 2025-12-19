<?php
namespace Modules\Menu;

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
        $config = $container->get('config');
        $adminPrefix = $config['admin_prefix'] ?? '/admin';

        $authMiddleware = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/menu', [$authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\MenuController::class, 'index']);
            $r->get('/create', [Controllers\MenuController::class, 'create']);
            $r->post('/create', [Controllers\MenuController::class, 'store']);
            $r->get('/edit/{id}', [Controllers\MenuController::class, 'edit']);
            $r->post('/edit/{id}', [Controllers\MenuController::class, 'update']);
            $r->post('/delete/{id}', [Controllers\MenuController::class, 'delete']);
            $r->post('/toggle/{id}', [Controllers\MenuController::class, 'toggle']);
            $r->post('/reorder', [Controllers\MenuController::class, 'reorder']);
        });
    }
}
