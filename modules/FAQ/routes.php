<?php
use Core\Router;

return function (Router $router, ?\Core\Container $container = null) {
    $guard = function ($req, $next) {
        if (empty($_SESSION['admin_auth'])) {
            header('Location: /admin/login');
            exit;
        }
        return $next($req);
    };
    $prefix = '/admin';
    if ($container) {
        $cfg = $container->get('config');
        $prefix = $cfg['admin_prefix'] ?? '/admin';
    }
    $router->get('/faq', [Modules\FAQ\Controllers\FaqController::class, 'index']);
    $router->group($prefix . '/faq', [$guard], function (Router $r) {
        $r->get('/', [Modules\FAQ\Controllers\FaqAdminController::class, 'index']);
        $r->get('/create', [Modules\FAQ\Controllers\FaqAdminController::class, 'create']);
        $r->post('/create', [Modules\FAQ\Controllers\FaqAdminController::class, 'store']);
        $r->get('/edit/{id}', [Modules\FAQ\Controllers\FaqAdminController::class, 'edit']);
        $r->post('/edit/{id}', [Modules\FAQ\Controllers\FaqAdminController::class, 'update']);
        $r->post('/delete/{id}', [Modules\FAQ\Controllers\FaqAdminController::class, 'delete']);
    });
};
