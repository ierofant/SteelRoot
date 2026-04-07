<?php
declare(strict_types=1);

namespace Modules\Gallery\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Modules\Gallery\Services\MasterLikeService;

class MasterLikeController
{
    private MasterLikeService $service;

    public function __construct(Container $container)
    {
        $this->service = new MasterLikeService($container);
    }

    public function store(Request $request): Response
    {
        $itemId = (int)($request->body['gallery_item_id'] ?? $request->body['id'] ?? 0);
        $result = $this->service->create($itemId, (string)($request->body['_token'] ?? ''), $request);

        $status = (int)($result['status'] ?? 200);
        unset($result['status']);

        return Response::json($result, $status);
    }
}
