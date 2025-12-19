<?php
namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Modules\Users\Services\Auth;
use Modules\Users\Services\UserRepository;

class RegistrationController
{
    private Container $container;
    private UserRepository $users;
    private Auth $auth;
    private array $config;
    private string $tokenDir;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
        $this->auth = $container->get(Auth::class);
        $this->config = $container->get('config')['modules']['users'] ?? [];
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->tokenDir = APP_ROOT . '/storage/tmp/user_tokens';
        if (!is_dir($this->tokenDir)) {
            @mkdir($this->tokenDir, 0775, true);
        }
    }

    public function form(Request $request): Response
    {
        if (!$this->registrationEnabled()) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('users/register', [
            'title' => 'Register',
            'csrf' => Csrf::token('register'),
            'error' => null,
            'success' => null,
        ]);
        return new Response($html);
    }

    public function register(Request $request): Response
    {
        if (!$this->registrationEnabled()) {
            return new Response('Not found', 404);
        }
        $ip = $request->server['REMOTE_ADDR'] ?? 'ip';
        $ua = $request->headers['user-agent'] ?? ($request->server['HTTP_USER_AGENT'] ?? '');
        if ($this->isIpBlocked($ip)) {
            return $this->renderError('Registration is not allowed from your IP');
        }
        $limit = (int)$this->getSetting('registration_rate_limit', 5);
        $limit = $limit > 0 ? $limit : 5;
        $limiter = new RateLimiter('register_' . $ip, $limit, 3600, true);
        if ($limiter->tooManyAttempts()) {
            return $this->renderError('Too many attempts, try later');
        }
        $limiter->hit();
        if (!Csrf::check('register', $request->body['_token'] ?? null)) {
            return $this->renderError('Invalid CSRF token');
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $usernameInput = (string)($request->body['username'] ?? $name);
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        if ($name === '' || $email === '' || $pass === '') {
            return $this->renderError('All fields are required', $name, $email, $usernameInput);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderError('Invalid email', $name, $email, $usernameInput);
        }
        if (!$this->emailAllowed($email)) {
            return $this->renderError('Email domain is not allowed', $name, $email, $usernameInput);
        }
        if (!$this->usernameValid($name)) {
            return $this->renderError('Username does not meet length rules', $name, $email, $usernameInput);
        }
        if ($pass !== $pass2) {
            return $this->renderError('Passwords do not match', $name, $email, $usernameInput);
        }
        $passMin = (int)$this->getSetting('password_min_length', 8);
        if ($passMin < 1) {
            $passMin = 8;
        }
        if (strlen($pass) < $passMin) {
            return $this->renderError('Password must be at least ' . $passMin . ' characters', $name, $email, $usernameInput);
        }
        if (!empty($this->getSetting('password_require_numbers', 0)) && !preg_match('/\\d/', $pass)) {
            return $this->renderError('Password must contain a number', $name, $email, $usernameInput);
        }
        if (!empty($this->getSetting('password_require_special', 0)) && !preg_match('/[^a-zA-Z0-9]/', $pass)) {
            return $this->renderError('Password must contain a special character', $name, $email, $usernameInput);
        }
        if ($this->users->emailExists($email)) {
            return $this->renderError('Email already exists', $name, $email, $usernameInput);
        }
        $username = $this->normalizeUsername($usernameInput);
        if ($username === '' || !$this->usernameValid($username) || ctype_digit($username)) {
            return $this->renderError('Username is invalid', $name, $email, $usernameInput);
        }
        if ($this->users->usernameExists($username)) {
            return $this->renderError('Username already exists', $name, $email, $usernameInput);
        }

        $status = 'active';
        $requireConfirm = (bool)$this->getSetting('email_verification_required', ($this->config['require_email_confirmation'] ?? false));
        if ($requireConfirm) {
            $status = 'pending';
        } elseif (!($this->config['auto_activate'] ?? true)) {
            $status = 'pending';
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $role = $this->safeRole($this->getSetting('default_role', 'user'));
        $userId = $this->users->create($name, $email, $hash, $role, $status, null, $username, 'public', null);

        if ($status === 'pending') {
            $smtp = $this->config['smtp'] ?? [];
            $smtpEnabled = !empty($smtp['enabled']) && !empty($smtp['host']);
            if ($smtpEnabled) {
                $token = bin2hex(random_bytes(16));
                file_put_contents($this->tokenDir . '/' . $token . '.json', json_encode([
                    'user_id' => $userId,
                    'email' => $email,
                    'created_at' => time(),
                ]));
                $link = $this->baseUrl($request) . '/confirm?token=' . urlencode($token);
                $this->sendMail(
                    $email,
                    'Confirm your account',
                    $this->container->get('renderer')->render('users/emails/confirmation_en', [
                        'name' => $name,
                        'link' => $link,
                    ]),
                    $smtp
                );
                return $this->renderSuccess('Check your email to confirm registration.');
            }
            return $this->renderSuccess('Account created and pending activation. Admin will enable it soon.');
        }

        if (!empty($this->getSetting('auto_login_after_register', 0))) {
            $user = $this->users->find($userId);
            if ($user) {
                $this->auth->loginDirect($user);
            }
        }

        return $this->renderSuccess('Account created, you can log in now.');
    }

    public function confirm(Request $request): Response
    {
        $token = trim($request->query['token'] ?? '');
        if ($token === '') {
            return new Response('Invalid token', 400);
        }
        $file = $this->tokenDir . '/' . basename($token) . '.json';
        if (!file_exists($file)) {
            return new Response('Token not found or expired', 404);
        }
        $payload = json_decode((string)file_get_contents($file), true);
        $userId = (int)($payload['user_id'] ?? 0);
        $user = $userId ? $this->users->find($userId) : null;
        if ($user && ($user['status'] ?? '') !== 'active') {
            $this->users->update($userId, ['status' => 'active']);
        }
        @unlink($file);
        $html = $this->container->get('renderer')->render('users/login', [
            'title' => 'Login',
            'csrf' => Csrf::token('login'),
            'success' => 'Email confirmed, you can sign in.',
            'error' => null,
        ]);
        return new Response($html);
    }

    private function renderError(string $msg, string $name = '', string $email = '', string $username = ''): Response
    {
        $html = $this->container->get('renderer')->render('users/register', [
            'title' => 'Register',
            'csrf' => Csrf::token('register'),
            'error' => $msg,
            'success' => null,
            'name' => $name,
            'email' => $email,
            'username' => $username,
        ]);
        return new Response($html, 400);
    }

    private function renderSuccess(string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/register', [
            'title' => 'Register',
            'csrf' => Csrf::token('register'),
            'error' => null,
            'success' => $msg,
            'name' => '',
            'email' => '',
            'username' => '',
        ]);
        return new Response($html);
    }

    private function baseUrl(Request $request): string
    {
        $cfg = $this->container->get('config');
        $base = rtrim($cfg['url'] ?? '', '/');
        if ($base === '') {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base;
    }

    private function sendMail(string $to, string $subject, string $body, array $smtp): void
    {
        $headers = 'From: ' . ($smtp['from'] ?? 'noreply@example.com');
        @mail($to, $subject, $body, $headers);
    }

    private function registrationEnabled(): bool
    {
        return !empty($this->getSetting('registration_enabled', 1));
    }

    private function getSetting(string $key, $default = null)
    {
        $all = $this->moduleSettings->all('users');
        return $all[$key] ?? $default;
    }

    private function emailAllowed(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $domain = strtolower($domain ?? '');
        $whitelistRaw = (string)$this->getSetting('email_domain_whitelist', '');
        $blacklistRaw = (string)$this->getSetting('email_domain_blacklist', '');
        $whitelist = array_filter(array_map('trim', preg_split('/\\R/', $whitelistRaw)));
        $blacklist = array_filter(array_map('trim', preg_split('/\\R/', $blacklistRaw)));
        if ($whitelist) {
            return in_array($domain, array_map('strtolower', $whitelist), true);
        }
        if ($blacklist && in_array($domain, array_map('strtolower', $blacklist), true)) {
            return false;
        }
        return true;
    }

    private function usernameValid(string $name): bool
    {
        $min = (int)$this->getSetting('username_min_length', 3);
        $max = (int)$this->getSetting('username_max_length', 32);
        if ($min < 1) {
            $min = 1;
        }
        if ($max < $min) {
            $max = $min;
        }
        $len = mb_strlen($name);
        return $len >= $min && $len <= $max;
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9_.\\-]+/', '', $value);
        $value = trim($value, '-_.');
        $max = $this->getSetting('username_max_length', 32);
        if ((int)$max > 0 && strlen($value) > (int)$max) {
            $value = substr($value, 0, (int)$max);
        }
        if (ctype_digit($value)) {
            $value = 'u' . $value;
        }
        return $value;
    }

    private function isIpBlocked(string $ip): bool
    {
        $raw = (string)$this->getSetting('blocked_ips', '');
        $list = array_filter(array_map('trim', preg_split('/\\R/', $raw)));
        if (!$list || $ip === '') {
            return false;
        }
        foreach ($list as $entry) {
            if ($entry === $ip) {
                return true;
            }
            if (strpos($entry, '/') !== false && $this->cidrMatch($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    private function cidrMatch(string $ip, string $cidr): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        [$subnet, $mask] = explode('/', $cidr) + [null, null];
        if (!filter_var($subnet, FILTER_VALIDATE_IP) || $mask === null) {
            return false;
        }
        $mask = (int)$mask;
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskBin = -1 << (32 - $mask);
        return ($ipLong & $maskBin) === ($subnetLong & $maskBin);
    }

    private function safeRole(string $role): string
    {
        $role = strtolower(trim($role));
        $allowed = ['user', 'member', 'editor'];
        return in_array($role, $allowed, true) ? $role : 'user';
    }
}
