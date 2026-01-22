<?php
declare(strict_types=1);

use Core\Router;
use Core\Container;
use Modules\Api\Controllers\OpenApiController;
use Modules\Api\Middleware\ApiAuthMiddleware;
use Modules\Api\Controllers\PingController;
use Modules\Api\Controllers\MeController;
use Modules\Api\Controllers\ApiKeysController;
use Modules\Api\Support\ApiScopeRegistry;

return function (Router $router, ?Container $container = null): void {
    $router->group('/api/v1', [], function (Router $r): void {
        $r->get('/openapi.json', [OpenApiController::class, 'index']);
        $r->get('/ping', [PingController::class, 'show']);
    });

    $scopeMap = ApiScopeRegistry::build();
    $router->group('/api/v1', [new ApiAuthMiddleware($scopeMap)], function (Router $r): void {
        $r->get('/me', [MeController::class, 'show']);
        $r->get('/api-keys', [ApiKeysController::class, 'index']);
        $r->post('/api-keys', [ApiKeysController::class, 'create']);
        $r->post('/api-keys/{id}/disable', [ApiKeysController::class, 'disable']);
        $r->post('/api-keys/{id}/rotate', [ApiKeysController::class, 'rotate']);
    });
};
