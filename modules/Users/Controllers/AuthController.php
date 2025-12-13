<?php
namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;

class AuthController
{
    private Container $container;
    private Auth $auth;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
    }

    public function form(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('users/login', [
            'title' => 'Login',
            'csrf' => Csrf::token('login'),
            'error' => null,
            'success' => null,
        ]);
        return new Response($html);
    }

    public function login(Request $request): Response
    {
        $ip = $request->server['REMOTE_ADDR'] ?? 'ip';
        $ua = $request->headers['user-agent'] ?? ($request->server['HTTP_USER_AGENT'] ?? '');
        $limiter = new RateLimiter('login_' . $ip, 5, 300, true);
        if ($limiter->tooManyAttempts()) {
            return $this->renderError('Too many attempts, try later');
        }
        $limiter->hit();
        if (!Csrf::check('login', $request->body['_token'] ?? null)) {
            return $this->renderError('Invalid CSRF token');
        }
        $email = strtolower(trim($request->body['email'] ?? ''));
        $pass = (string)($request->body['password'] ?? '');
        if ($email === '' || $pass === '') {
            return $this->renderError('Enter email and password');
        }
        [$ok, $msg] = $this->auth->attempt($email, $pass, $ip, $ua);
        if (!$ok) {
            return $this->renderError($msg ?: 'Login failed');
        }
        return new Response('', 302, ['Location' => '/profile']);
    }

    public function logout(Request $request): Response
    {
        $token = $request->body['_token'] ?? null;
        if (!Csrf::check('logout', $token)) {
            // fallback to avoid lockout: regenerate and allow logout
            Csrf::token('logout');
        }
        $this->auth->logout();
        return new Response('', 302, ['Location' => '/login']);
    }

    private function renderError(string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/login', [
            'title' => 'Login',
            'csrf' => Csrf::token('login'),
            'error' => $msg,
            'success' => null,
        ]);
        return new Response($html, 400);
    }
}
