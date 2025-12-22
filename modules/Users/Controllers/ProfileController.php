<?php
namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\ModuleSettings;
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
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
        $this->avatars = $container->get(AvatarService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $html = $this->container->get('renderer')->render('users/profile', [
            '_layout' => true,
            'title' => 'Profile',
            'user' => $user,
            'csrf' => Csrf::token('profile_update'),
            'avatarToken' => Csrf::token('profile_avatar'),
            'message' => $request->query['msg'] ?? null,
            'error' => $request->query['err'] ?? null,
            'visibilityOptions' => ['public', 'private'],
            'logoutToken' => Csrf::token('logout'),
        ], [
            'title' => 'Profile',
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
        $usernameInput = (string)($request->body['username'] ?? '');
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? 'public'));
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        if ($name === '' || $email === '') {
            return $this->redirectWithError('Name and email required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithError('Invalid email');
        }
        if ($this->users->emailExists($email, (int)$user['id'])) {
            return $this->redirectWithError('Email already used');
        }
        $username = $this->normalizeUsername($usernameInput !== '' ? $usernameInput : ($user['username'] ?? $name));
        if ($username === '' || strlen($username) < $this->usernameMin()) {
            return $this->redirectWithError('Username is too short or invalid');
        }
        if ($this->users->usernameExists($username, (int)$user['id'])) {
            return $this->redirectWithError('Username already used');
        }
        $data = [
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'profile_visibility' => $visibility,
            'signature' => $signature,
        ];
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
            '_layout' => true,
            'title' => 'Edit avatar',
            'csrf' => Csrf::token('profile_avatar_crop'),
            'user' => $user,
        ], [
            'title' => 'Edit avatar',
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
        $identifier = trim((string)($request->params['id'] ?? ''));
        $user = $this->findByIdentifier($identifier);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return new Response('Not found', 404);
        }
        $viewer = $this->auth->user();
        $isOwner = $viewer && (int)$viewer['id'] === (int)$user['id'];
        $isAdmin = $this->auth->checkRole('admin');
        $isPrivate = ($user['profile_visibility'] ?? 'public') === 'private';
        $restricted = $isPrivate && !$isOwner && !$isAdmin;
        $meta = [
            'title' => $restricted ? 'Profile is private' : ($user['name'] ?? 'User'),
        ];
        if ($restricted) {
            $meta['robots'] = 'noindex,nofollow';
        }
        $html = $this->container->get('renderer')->render(
            'users/public_profile',
            [
                '_layout' => true,
                'title' => $meta['title'],
                'user' => $user,
                'restricted' => $restricted,
                'username' => $user['username'] ?? '',
                'canViewDetails' => !$restricted,
            ],
            $meta
        );
        return new Response($html);
    }

    private function redirectWithError(string $msg): Response
    {
        return new Response('', 302, ['Location' => '/profile?err=' . urlencode($msg)]);
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9_.\\-]+/', '', $value);
        $value = trim($value, '-_.');
        $max = $this->usernameMax();
        if ($max > 0 && strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }
        if (ctype_digit($value)) {
            $value = 'u' . $value;
        }
        return $value;
    }

    private function normalizeVisibility(string $raw): string
    {
        return $raw === 'private' ? 'private' : 'public';
    }

    private function sanitizeSignature($value): ?string
    {
        $plain = trim((string)$value);
        if ($plain === '') {
            return null;
        }
        $plain = strip_tags($plain);
        $plain = preg_replace('/\\s+/', ' ', $plain);
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        if (mb_strlen($plain) > 300) {
            $plain = mb_substr($plain, 0, 300);
        }
        return $plain;
    }

    private function usernameMin(): int
    {
        $min = (int)($this->moduleSettings->all('users')['username_min_length'] ?? 3);
        return $min > 0 ? $min : 3;
    }

    private function usernameMax(): int
    {
        $settings = $this->moduleSettings->all('users');
        $min = $this->usernameMin();
        $max = (int)($settings['username_max_length'] ?? 32);
        if ($max < $min) {
            $max = $min;
        }
        return $max;
    }

    private function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        if (ctype_digit($identifier)) {
            $byId = $this->users->find((int)$identifier);
            if ($byId) {
                return $byId;
            }
        }
        return $this->users->findByUsername($identifier);
    }
}
