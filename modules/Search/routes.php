<?php
use Core\Router;

return function (Router $router, ?\Core\Container $container = null) {
    $router->get('/search/advanced', [Modules\Search\Controllers\SearchAdvancedController::class, 'index']);
    $router->post('/search/advanced/apply', [Modules\Search\Controllers\SearchAdvancedController::class, 'apply']);
};
