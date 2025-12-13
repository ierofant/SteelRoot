<?php
namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\AvatarService;
use Modules\Users\Services\UserRepository;

class ProfileController
{
    private Container $container;
    private Auth $auth;
    private UserRepository $users;
    private AvatarService $avatars;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
        $this->avatars = $container->get(AvatarService::class);
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $html = $this->container->get('renderer')->render('users/profile', [
            'title' => 'Profile',
            'user' => $user,
            'csrf' => Csrf::token('profile_update'),
            'avatarToken' => Csrf::token('profile_avatar'),
            'message' => $request->query['msg'] ?? null,
            'error' => $request->query['err'] ?? null,
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_update', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        if ($name === '' || $email === '') {
            return $this->redirectWithError('Name and email required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithError('Invalid email');
        }
        if ($this->users->emailExists($email, (int)$user['id'])) {
            return $this->redirectWithError('Email already used');
        }
        $data = ['name' => $name, 'email' => $email];
        if ($pass !== '') {
            if ($pass !== $pass2) {
                return $this->redirectWithError('Passwords do not match');
            }
            if (strlen($pass) < 8) {
                return $this->redirectWithError('Password must be at least 8 characters');
            }
            $this->users->setPassword((int)$user['id'], password_hash($pass, PASSWORD_DEFAULT));
        }
        $this->users->update((int)$user['id'], $data);
        return new Response('', 302, ['Location' => '/profile?msg=updated']);
    }

    public function avatar(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_avatar', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $saved = (new \Modules\Users\Services\AvatarProcessor())->processUploadedImage($request->files['avatar']['tmp_name'] ?? '', (int)$user['id']);
        if (!$saved) {
            return $this->redirectWithError('Avatar upload failed');
        }
        $this->users->update((int)$user['id'], ['avatar' => $saved]);
        return new Response('', 302, ['Location' => '/profile?msg=avatar']);
    }

    public function avatarEditor(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $html = $this->container->get('renderer')->render('users/avatar_editor', [
            'title' => 'Edit avatar',
            'csrf' => Csrf::token('profile_avatar_crop'),
            'user' => $user,
        ]);
        return new Response($html);
    }

    public function avatarCrop(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_avatar_crop', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $tmp = $request->files['avatar']['tmp_name'] ?? '';
        $cropX = (int)($request->body['crop_x'] ?? 0);
        $cropY = (int)($request->body['crop_y'] ?? 0);
        $cropW = (int)($request->body['crop_w'] ?? 0);
        $cropH = (int)($request->body['crop_h'] ?? 0);
        $scale = (float)($request->body['crop_scale'] ?? 1.0);
        $processor = new \Modules\Users\Services\AvatarProcessor();
        $saved = $processor->processWithCrop($tmp, (int)$user['id'], $cropX, $cropY, $cropW, $cropH, $scale);
        if (!$saved) {
            return $this->redirectWithError('Avatar crop failed');
        }
        $this->users->update((int)$user['id'], ['avatar' => $saved]);
        return new Response('', 302, ['Location' => '/profile?msg=avatar']);
    }

    public function publicProfile(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $user = $id ? $this->users->find($id) : null;
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('users/public_profile', [
            'title' => $user['name'] ?? 'User',
            'user' => $user,
        ]);
        return new Response($html);
    }

    private function redirectWithError(string $msg): Response
    {
        return new Response('', 302, ['Location' => '/profile?err=' . urlencode($msg)]);
    }
}
