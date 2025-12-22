<?php
namespace Modules\Templates;

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

        $router->group($adminPrefix . '/templates', [$authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\TemplatesController::class, 'index']);
            $r->post('/select', [Controllers\TemplatesController::class, 'select']);
            $r->post('/upload', [Controllers\TemplatesController::class, 'upload']);
            $r->post('/delete', [Controllers\TemplatesController::class, 'delete']);
        });
    }
}
