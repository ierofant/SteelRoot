<?php
namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Modules\Users\Services\UserRepository;

class AdminUsersController
{
    private Container $container;
    private UserRepository $users;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'email' => trim($request->query['email'] ?? ''),
            'role' => trim($request->query['role'] ?? ''),
            'status' => trim($request->query['status'] ?? ''),
        ];
        $list = $this->users->list($filters);
        $html = $this->container->get('renderer')->render('users/admin/users_list', [
            'title' => 'Users',
            'users' => $list,
            'filters' => $filters,
            'csrf' => Csrf::token('admin_users'),
            'blockToken' => Csrf::token('admin_users_block'),
            'resetToken' => Csrf::token('admin_users_reset'),
            'message' => $request->query['msg'] ?? null,
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_create', [
            'title' => 'Create User',
            'csrf' => Csrf::token('admin_users'),
            'error' => null,
            'visibilityOptions' => ['public', 'private'],
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('admin_users', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $role = $this->normalizeRole($request->body['role'] ?? 'user');
        $status = $this->normalizeStatus($request->body['status'] ?? 'active');
        $pass = (string)($request->body['password'] ?? '');
        $usernameInput = (string)($request->body['username'] ?? $name);
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? 'public'));
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        if ($name === '' || $email === '' || $pass === '') {
            return $this->renderCreateError('All fields are required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderCreateError('Invalid email');
        }
        if (strlen($pass) < 8) {
            return $this->renderCreateError('Password must be at least 8 characters');
        }
        if ($this->users->emailExists($email)) {
            return $this->renderCreateError('Email already exists');
        }
        $username = $this->normalizeUsername($usernameInput ?: $name);
        if ($username === '' || strlen($username) < $this->usernameMin()) {
            return $this->renderCreateError('Username is too short or invalid');
        }
        if ($this->users->usernameExists($username)) {
            return $this->renderCreateError('Username already exists');
        }
        $this->users->create($name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $status, null, $username, $visibility, $signature);
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=created']);
    }

    public function settings(Request $request): Response
    {
        $settings = $this->moduleSettings->all('users');
        $defaults = $this->defaults();
        $data = array_merge($defaults, $settings);
        $html = $this->container->get('renderer')->render('users/admin/settings', [
            'title' => __('users.settings.title'),
            'csrf' => Csrf::token('users_settings'),
            'settings' => $data,
            'saved' => !empty($request->query['saved']),
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('users_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $map = [
            'registration_enabled' => 'users_registration_enabled',
            'email_verification_required' => 'users_email_verification_required',
            'auto_login_after_register' => 'users_auto_login_after_register',
            'default_role' => 'users_default_role',
            'email_domain_blacklist' => 'users_email_domain_blacklist',
            'email_domain_whitelist' => 'users_email_domain_whitelist',
            'username_min_length' => 'users_username_min_length',
            'username_max_length' => 'users_username_max_length',
            'password_min_length' => 'users_password_min_length',
            'password_require_numbers' => 'users_password_require_numbers',
            'password_require_special' => 'users_password_require_special',
            'registration_rate_limit' => 'users_registration_rate_limit',
            'blocked_ips' => 'users_blocked_ips',
        ];
        foreach ($map as $key => $field) {
            $val = $request->body[$field] ?? null;
            if (in_array($key, ['registration_enabled','email_verification_required','auto_login_after_register','password_require_numbers','password_require_special'], true)) {
                $val = !empty($val) ? 1 : 0;
            } elseif (in_array($key, ['username_min_length','username_max_length','password_min_length','registration_rate_limit'], true)) {
                $val = (int)$val;
            } else {
                $val = (string)$val;
            }
            $this->moduleSettings->set('users', $key, $val);
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/users/settings?saved=1']);
    }

    private function defaults(): array
    {
        return [
            'registration_enabled' => 1,
            'email_verification_required' => 0,
            'auto_login_after_register' => 0,
            'default_role' => 'user',
            'email_domain_blacklist' => '',
            'email_domain_whitelist' => '',
            'username_min_length' => 3,
            'username_max_length' => 32,
            'password_min_length' => 8,
            'password_require_numbers' => 0,
            'password_require_special' => 0,
            'registration_rate_limit' => 5,
            'blocked_ips' => '',
        ];
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $user = $this->users->find($id);
        if (!$user) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('users/admin/user_edit', [
            'title' => 'Edit User',
            'user' => $user,
            'csrf' => Csrf::token('admin_users'),
            'error' => null,
            'message' => $request->query['msg'] ?? null,
            'visibilityOptions' => ['public', 'private'],
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('admin_users', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $user = $this->users->find($id);
        if (!$user) {
            return new Response('Not found', 404);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $role = $this->normalizeRole($request->body['role'] ?? 'user');
        $status = $this->normalizeStatus($request->body['status'] ?? 'active');
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        $usernameInput = (string)($request->body['username'] ?? ($user['username'] ?? $user['name'] ?? ''));
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? ($user['profile_visibility'] ?? 'public')));
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        if ($name === '' || $email === '') {
            return $this->renderEditError($user, 'Name and email required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderEditError($user, 'Invalid email');
        }
        if ($this->users->emailExists($email, $id)) {
            return $this->renderEditError($user, 'Email already exists');
        }
        $username = $this->normalizeUsername($usernameInput);
        if ($username === '' || strlen($username) < $this->usernameMin()) {
            return $this->renderEditError($user, 'Username is too short or invalid');
        }
        if ($this->users->usernameExists($username, $id)) {
            return $this->renderEditError($user, 'Username already exists');
        }
        $data = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'username' => $username,
            'profile_visibility' => $visibility,
            'signature' => $signature,
        ];
        $this->users->update($id, $data);
        if ($pass !== '') {
            if ($pass !== $pass2) {
                return $this->renderEditError($this->users->find($id) ?? $user, 'Passwords do not match');
            }
            if (strlen($pass) < 8) {
                return $this->renderEditError($this->users->find($id) ?? $user, 'Password must be at least 8 characters');
            }
            $this->users->setPassword($id, password_hash($pass, PASSWORD_DEFAULT));
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/users/edit/' . $id . '?msg=updated']);
    }

    public function block(Request $request): Response
    {
        if (!Csrf::check('admin_users_block', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->users->update($id, ['status' => 'blocked']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=blocked']);
    }

    public function unblock(Request $request): Response
    {
        if (!Csrf::check('admin_users_block', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->users->update($id, ['status' => 'active']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=unblocked']);
    }

    public function resetPassword(Request $request): Response
    {
        if (!Csrf::check('admin_users_reset', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $newPass = bin2hex(random_bytes(6));
        $this->users->setPassword($id, password_hash($newPass, PASSWORD_DEFAULT));
        return new Response('', 302, ['Location' => $this->prefix() . '/users/edit/' . $id . '?msg=Password:' . urlencode($newPass)]);
    }

    private function renderCreateError(string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_create', [
            'title' => 'Create User',
            'csrf' => Csrf::token('admin_users'),
            'error' => $msg,
            'visibilityOptions' => ['public', 'private'],
        ]);
        return new Response($html, 400);
    }

    private function renderEditError(array $user, string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_edit', [
            'title' => 'Edit User',
            'user' => $user,
            'csrf' => Csrf::token('admin_users'),
            'error' => $msg,
            'message' => null,
            'visibilityOptions' => ['public', 'private'],
        ]);
        return new Response($html, 400);
    }

    private function normalizeRole(string $role): string
    {
        $allowed = ['user', 'editor', 'admin'];
        $role = strtolower(trim($role));
        return in_array($role, $allowed, true) ? $role : 'user';
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['active', 'blocked', 'pending'];
        $status = strtolower(trim($status));
        return in_array($status, $allowed, true) ? $status : 'active';
    }

    private function normalizeVisibility(string $raw): string
    {
        return $raw === 'private' ? 'private' : 'public';
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
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

    private function usernameMin(): int
    {
        $settings = $this->moduleSettings->all('users');
        $min = (int)($settings['username_min_length'] ?? 3);
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
}
