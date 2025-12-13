<?php
use Modules\Popups\Controllers\AdminPopupsController;

return function (\Core\Router $router, ?\Core\Container $container = null) {
    $prefix = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
    $router->get($prefix . '/popups', [AdminPopupsController::class, 'index']);
    $router->post($prefix . '/popups', [AdminPopupsController::class, 'save']);
};
