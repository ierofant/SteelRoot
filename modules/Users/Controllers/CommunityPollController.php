<?php

declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\CommunityPollService;

class CommunityPollController
{
    private Container $container;
    private Auth $auth;
    private CommunityPollService $polls;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->polls = $container->get(CommunityPollService::class);
    }

    public function submit(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        if (!Csrf::check('community_poll_submit', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $ip = (string)($request->server['REMOTE_ADDR'] ?? '');
        $limiter = new RateLimiter('community_poll_' . $ip . '_' . (int)$user['id'], 8, 3600, true);
        if ($limiter->tooManyAttempts()) {
            return new Response('', 302, ['Location' => '/profile?tab=overview&err=community-poll-rate-limit']);
        }
        $limiter->hit();

        $result = $this->polls->submitResponse((int)$user['id'], $request->body);
        $queryKey = $result['ok'] ? 'msg' : 'err';
        $queryValue = match ($result['code']) {
            'submitted' => 'community-poll-submitted',
            'exists' => 'community-poll-exists',
            'inactive' => 'community-poll-inactive',
            'not_ready' => 'community-poll-not-ready',
            'other_required' => 'community-poll-other-required',
            default => 'community-poll-invalid',
        };

        return new Response('', 302, ['Location' => '/profile?tab=overview&' . $queryKey . '=' . urlencode($queryValue)]);
    }
}
