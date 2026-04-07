<?php
declare(strict_types=1);

namespace Modules\Comments\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Modules\Comments\Services\CommentService;

class CommentsController
{
    private CommentService $comments;

    public function __construct(Container $container)
    {
        $this->comments = $container->get(CommentService::class);
    }

    public function store(Request $request): Response
    {
        return $this->comments->store($request);
    }

    public function fragment(Request $request): Response
    {
        if (($request->query['_xhr'] ?? '') !== '1') {
            return new Response('', 302, ['Location' => '/gallery']);
        }
        $entityType = preg_replace('/[^a-z_]/', '', strtolower((string)($request->query['entity_type'] ?? '')));
        $entityId   = max(0, (int)($request->query['entity_id'] ?? 0));
        if ($entityType === '' || $entityId < 1) {
            return new Response('', 400);
        }
        $returnUrl = $request->query['return_url'] ?? null;
        $options   = $returnUrl ? ['currentUrl' => filter_var($returnUrl, FILTER_SANITIZE_URL)] : [];
        $html = $this->comments->renderForEntity($entityType, $entityId, $options);
        return new Response($html ?: '<p class="comments-empty">Комментариев пока нет.</p>', 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
