<?php
declare(strict_types=1);

namespace Modules\Api\Controllers;

use Core\Request;
use Core\Response;
use Modules\Api\OpenApi\Attributes\ApiResponse;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiTag;
use Modules\Api\Support\JsonNegotiationTrait;

#[ApiTag('System')]
class PingController
{
    use JsonNegotiationTrait;

    #[ApiRoute('GET', '/api/v1/ping', auth: false)]
    #[ApiResponse(200, 'OK')]
    public function show(Request $request): Response
    {
        return $this->respond($request, ['ok' => true, 'ts' => time()]);
    }
}
