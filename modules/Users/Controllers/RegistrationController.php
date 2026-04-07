<?php
namespace Modules\Users\Controllers;

use App\Services\MailTemplateService;
use App\Services\CaptchaService;
use App\Services\ProjectMailer;
use App\Services\SettingsService;
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
    private const CONFIRM_TOKEN_TTL = 86400;

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
            'captcha' => $this->container->get(CaptchaService::class)->config(),
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
        if (($request->body['website'] ?? '') !== '') {
            return new Response('', 302, ['Location' => '/']);
        }
        if (!Csrf::check('register', $request->body['_token'] ?? null)) {
            return $this->renderError('Invalid CSRF token');
        }
        /** @var CaptchaService $captcha */
        $captcha = $this->container->get(CaptchaService::class);
        if (!$captcha->verify($request, 'register')) {
            return $this->renderError('Captcha failed');
        }
        $name = trim($request->body['name'] ?? '');
        $displayName = trim($request->body['display_name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        if ($name === '' || $email === '' || $pass === '') {
            return $this->renderError('All fields are required', $name, $displayName, $email);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderError('Invalid email', $name, $displayName, $email);
        }
        if (empty($request->body['policy_ack'])) {
            return $this->renderError('Необходимо принять политику конфиденциальности', $name, $displayName, $email);
        }
        if (!$this->emailAllowed($email)) {
            return $this->renderError('Email domain is not allowed', $name, $displayName, $email);
        }
        $usernameSeed = $displayName !== '' ? $displayName : $name;
        if (!$this->usernameValid($usernameSeed)) {
            return $this->renderError('Display name does not meet length rules', $name, $displayName, $email);
        }
        if ($pass !== $pass2) {
            return $this->renderError('Passwords do not match', $name, $displayName, $email);
        }
        $passMin = (int)$this->getSetting('password_min_length', 8);
        if ($passMin < 1) {
            $passMin = 8;
        }
        if (strlen($pass) < $passMin) {
            return $this->renderError('Password must be at least ' . $passMin . ' characters', $name, $displayName, $email);
        }
        if (!empty($this->getSetting('password_require_numbers', 0)) && !preg_match('/\\d/', $pass)) {
            return $this->renderError('Password must contain a number', $name, $displayName, $email);
        }
        if (!empty($this->getSetting('password_require_special', 0)) && !preg_match('/[^a-zA-Z0-9]/', $pass)) {
            return $this->renderError('Password must contain a special character', $name, $displayName, $email);
        }
        if ($this->users->emailExists($email)) {
            return $this->renderError('Email already exists', $name, $displayName, $email);
        }
        $username = $this->normalizeUsername($usernameSeed);
        if ($username === '' || !$this->usernameValid($username) || ctype_digit($username)) {
            return $this->renderError('Username is invalid', $name, $displayName, $email);
        }
        if ($this->users->usernameExists($username)) {
            return $this->renderError('Username already exists', $name, $displayName, $email);
        }

        $status = 'active';
        $requireConfirm = (bool)$this->getSetting('email_verification_required', ($this->config['require_email_confirmation'] ?? false));
        $settings = $this->container->get(SettingsService::class);
        if ($requireConfirm && !$this->mailDeliveryConfigured($settings)) {
            return $this->renderError('Email verification is enabled, but mail delivery is not configured', $name, $displayName, $email);
        }
        if ($requireConfirm) {
            $status = 'pending';
        } elseif (!($this->config['auto_activate'] ?? true)) {
            $status = 'pending';
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $role = $this->safeRole($this->getSetting('default_role', 'user'));
        $userId = $this->users->create($name, $email, $hash, $role, $status, null, $username, 'public', null, $ip);
        $this->users->upsertProfile($userId, [
            'display_name' => $displayName !== '' ? $displayName : $name,
            'visibility_mode' => 'public',
        ]);

        if ($requireConfirm) {
            $token = $this->storeConfirmToken($userId, $email);
            $link = $this->baseUrl($request) . '/confirm?token=' . urlencode($token);
            $this->sendRegistrationMail($email, $displayName !== '' ? $displayName : $name, $link);
            $this->notifyAdmin($username, $email, $displayName !== '' ? $displayName : $name, 'pending_confirm');
            return $this->renderSuccess('Check your email to confirm registration.');
        }

        if ($status === 'pending') {
            $this->notifyAdmin($username, $email, $displayName !== '' ? $displayName : $name, 'pending');
            return $this->renderSuccess('Account created and pending activation. Admin will enable it soon.');
        }

        $this->notifyAdmin($username, $email, $displayName !== '' ? $displayName : $name, 'active');

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
        $file = $this->confirmTokenPath($token);
        if (!is_file($file)) {
            return new Response('Token not found or expired', 404);
        }
        $payload = json_decode((string)file_get_contents($file), true);
        if (!is_array($payload) || (int)($payload['expires_at'] ?? 0) < time()) {
            @unlink($file);
            return new Response('Token not found or expired', 404);
        }
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
            'captcha' => $this->container->get(CaptchaService::class)->config(),
        ]);
        return new Response($html);
    }

    private function renderError(string $msg, string $name = '', string $displayName = '', string $email = ''): Response
    {
        $html = $this->container->get('renderer')->render('users/register', [
            'title' => 'Register',
            'csrf' => Csrf::token('register'),
            'error' => $msg,
            'success' => null,
            'name' => $name,
            'display_name' => $displayName,
            'email' => $email,
            'captcha' => $this->container->get(CaptchaService::class)->config(),
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
            'display_name' => '',
            'email' => '',
            'captcha' => $this->container->get(CaptchaService::class)->config(),
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

    private function sendRegistrationMail(string $to, string $name, string $link): void
    {
        $settings = $this->container->get(SettingsService::class);
        $siteName = trim((string)$settings->get('site_name', 'TattooRoot'));
        $templates = new MailTemplateService($settings);
        $message = $templates->render(
            'mail_registration_subject',
            'mail_registration_body',
            ['site_name' => $siteName, 'name' => $name, 'email' => $to, 'link' => $link],
            'Confirm your registration on {site_name}',
            ''
        );
        $html = $this->renderEmailTemplate('registration_confirm', [
            'site_name' => $siteName,
            'name'      => $name,
            'link'      => $link,
            'subject'   => $message['subject'],
        ]);
        (new ProjectMailer($settings))->sendHtml($to, $message['subject'], $html);
    }

    private function notifyAdmin(string $username, string $email, string $name, string $status): void
    {
        $settings = $this->container->get(SettingsService::class);
        $adminEmail = trim((string)$settings->get('contact_email', ''));
        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $siteName = trim((string)$settings->get('site_name', 'TattooRoot'));
        $subject  = '[' . $siteName . '] Новый пользователь: ' . $username;
        $statusLabel = match ($status) {
            'pending_confirm' => 'Ожидает подтверждения email',
            'pending'         => 'Ожидает активации администратором',
            default           => 'Активен',
        };
        $date = date('d.m.Y H:i');
        $html = '<p>На сайте <strong>' . htmlspecialchars($siteName) . '</strong> зарегистрировался новый пользователь.</p>'
            . '<table cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
            . '<tr><td style="color:#888">Имя:</td><td><strong>' . htmlspecialchars($name) . '</strong></td></tr>'
            . '<tr><td style="color:#888">Логин:</td><td>' . htmlspecialchars($username) . '</td></tr>'
            . '<tr><td style="color:#888">Email:</td><td>' . htmlspecialchars($email) . '</td></tr>'
            . '<tr><td style="color:#888">Статус:</td><td>' . htmlspecialchars($statusLabel) . '</td></tr>'
            . '<tr><td style="color:#888">Дата:</td><td>' . $date . '</td></tr>'
            . '</table>';
        try {
            (new ProjectMailer($settings))->sendHtml($adminEmail, $subject, $html);
        } catch (\Throwable) {
            // не ломаем регистрацию из-за сбоя уведомления
        }
    }

    private function renderEmailTemplate(string $tpl, array $vars): string
    {
        extract($vars);
        ob_start();
        include __DIR__ . '/../views/emails/' . $tpl . '.php';
        return (string)ob_get_clean();
    }

    private function storeConfirmToken(int $userId, string $email): string
    {
        $token = bin2hex(random_bytes(16));
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'created_at' => time(),
            'expires_at' => time() + self::CONFIRM_TOKEN_TTL,
        ];
        file_put_contents($this->confirmTokenPath($token), json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $token;
    }

    private function confirmTokenPath(string $token): string
    {
        return $this->tokenDir . '/' . basename($token) . '.json';
    }

    private function mailDeliveryConfigured(SettingsService $settings): bool
    {
        $driver = trim((string)$settings->get('mail_driver', 'php'));
        $from = trim((string)$settings->get('mail_from', ''));

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($driver === 'smtp') {
            return trim((string)$settings->get('mail_host', '')) !== '';
        }

        return in_array($driver, ['php', 'sendmail'], true);
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
        $emailLower = strtolower($email);
        $emailBlacklistRaw = (string)$this->getSetting('email_blacklist', '');
        $emailBlacklist = array_filter(array_map('strtolower', array_map('trim', preg_split('/\\R/', $emailBlacklistRaw))));
        if ($emailBlacklist && in_array($emailLower, $emailBlacklist, true)) {
            return false;
        }
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
