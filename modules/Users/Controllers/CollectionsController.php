<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\CollectionService;

class CollectionsController
{
    private Auth $auth;
    private CollectionService $collections;

    public function __construct(Container $container)
    {
        $this->auth = $container->get(Auth::class);
        $this->collections = $container->get(CollectionService::class);
    }

    public function create(Request $request): Response
    {
        if (!Csrf::check('users_collections', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $title = trim((string)($request->body['title'] ?? ''));
        $description = trim((string)($request->body['description'] ?? ''));
        $id = $this->collections->create((int)$user['id'], $title, $description);
        $returnTo = $id > 0 ? '/profile?tab=collections&collection=' . $id . '&msg=collection-created' : '/profile?tab=collections&err=collection-create-failed';

        return new Response('', 302, ['Location' => $returnTo]);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('users_collections', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $collectionId = (int)($request->params['id'] ?? 0);
        $this->collections->delete((int)$user['id'], $collectionId);

        return new Response('', 302, ['Location' => '/profile?tab=collections&msg=collection-deleted']);
    }

    public function removeItem(Request $request): Response
    {
        if (!Csrf::check('users_collections', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $collectionId = (int)($request->params['id'] ?? 0);
        $itemId = (int)($request->params['itemId'] ?? 0);
        $this->collections->removeItem((int)$user['id'], $collectionId, $itemId);

        return new Response('', 302, ['Location' => '/profile?tab=collections&collection=' . $collectionId . '&msg=collection-item-removed']);
    }

    public function quickSave(Request $request): Response
    {
        if (!Csrf::check('users_collections', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $entityType = trim((string)($request->body['entity_type'] ?? ''));
        $entityId = (int)($request->body['entity_id'] ?? 0);
        $returnTo = trim((string)($request->body['return_to'] ?? '/profile?tab=collections'));
        $returnTo = str_starts_with($returnTo, '/') ? $returnTo : '/profile?tab=collections';

        $collectionId = $this->collections->quickSave((int)$user['id'], $entityType, $entityId);
        if ($collectionId === null) {
            return new Response('', 302, ['Location' => $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'err=collection-save-failed']);
        }

        return new Response('', 302, ['Location' => $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'msg=collection-saved']);
    }
}
