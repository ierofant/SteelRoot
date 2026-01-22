<?php
declare(strict_types=1);

namespace Modules\Api\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Modules\Api\OpenApi\Attributes\ApiResponse;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiTag;
use Modules\Api\OpenApi\OpenApiGenerator;
use Modules\Api\Support\JsonNegotiationTrait;

#[ApiTag('OpenAPI')]
class OpenApiController
{
    use JsonNegotiationTrait;

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    #[ApiRoute('GET', '/api/v1/openapi.json', auth: false)]
    #[ApiResponse(200, 'OpenAPI schema')]
    public function index(Request $request): Response
    {
        $generator = new OpenApiGenerator($this->container->get('config')['modules']['api'] ?? []);
        return $this->respond($request, $generator->generate());
    }
}
