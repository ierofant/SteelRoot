<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\UserRepository;

class FavoritesController
{
    private Auth $auth;
    private UserRepository $users;

    public function __construct(Container $container)
    {
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
    }

    public function toggle(Request $request): Response
    {
        if (!Csrf::check('users_favorites', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $entityType = trim((string)($request->body['entity_type'] ?? ''));
        $entityId = (int)($request->body['entity_id'] ?? 0);
        $returnTo = trim((string)($request->body['return_to'] ?? '/profile'));
        $returnTo = str_starts_with($returnTo, '/') ? $returnTo : '/profile';

        if ($entityId > 0) {
            $this->users->toggleFavorite((int)$user['id'], $entityType, $entityId);
        }

        return new Response('', 302, ['Location' => $returnTo]);
    }
}
