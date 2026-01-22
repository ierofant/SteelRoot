<?php
declare(strict_types=1);

namespace Modules\Api\Controllers;

use Core\Request;
use Core\Response;
use Modules\Api\OpenApi\Attributes\ApiResponse;
use Modules\Api\OpenApi\Attributes\ApiRoute;
use Modules\Api\OpenApi\Attributes\ApiTag;
use Modules\Api\Support\JsonNegotiationTrait;

#[ApiTag('Auth')]
class MeController
{
    use JsonNegotiationTrait;

    #[ApiRoute('GET', '/api/v1/me', auth: true)]
    #[ApiResponse(200, 'Current API key')]
    #[ApiResponse(401, 'Unauthorized')]
    public function show(Request $request): Response
    {
        $apiKey = $request->attrs['api_key'] ?? null;
        if (!is_array($apiKey)) {
            return $this->respond($request, ['error' => 'unauthorized'], 401);
        }

        $payload = [
            'id' => (int)($apiKey['id'] ?? 0),
            'name' => (string)($apiKey['name'] ?? ''),
            'scopes' => is_array($apiKey['scopes'] ?? null) ? $apiKey['scopes'] : [],
            'enabled' => (bool)($apiKey['enabled'] ?? false),
            'last_used_at' => $apiKey['last_used_at'] ?? null,
        ];

        return $this->respond($request, $payload);
    }
}
