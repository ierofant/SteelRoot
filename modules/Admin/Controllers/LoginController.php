<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Csrf;
use Core\Logger;
use App\Services\SecurityLog;
use App\Services\CaptchaService;
use App\Services\SettingsService;

class LoginController
{
    private Container $container;
    private Database $db;
    private int $maxAttempts = 5;
    private int $lockSeconds = 300;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->settings = $container->get(SettingsService::class);
        $this->maxAttempts = (int)$this->settings->get('admin_max_attempts', 5);
        $lockMinutes = (int)$this->settings->get('admin_lock_minutes', 5);
        $this->lockSeconds = max(60, $lockMinutes * 60);
    }

    public function show(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('admin/login', [
            'title' => 'Admin Login',
            'csrf' => Csrf::token('admin_login'),
            'captcha' => $this->container->get(\App\Services\CaptchaService::class)->config(),
        ]);
        return new Response($html);
    }

    public function login(Request $request): Response
    {
        if (!Csrf::check('admin_login', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        /** @var CaptchaService $captcha */
        $captcha = $this->container->get(\App\Services\CaptchaService::class);
        $cfg = $captcha->config();
        if (!empty($cfg['enable_admin_login']) && $cfg['provider'] !== 'none') {
            if (!$captcha->verify($request)) {
                return new Response('Captcha failed', 400);
            }
        }
        $username = trim((string)($request->body['user'] ?? $request->body['username'] ?? ''));
        $plainPassword = (string)($request->body['pass'] ?? $request->body['password'] ?? '');
        $ip = $request->server['REMOTE_ADDR'] ?? '';
        $ua = $request->headers['user-agent'] ?? ($request->server['HTTP_USER_AGENT'] ?? '');
        $record = $this->db->fetch("SELECT * FROM admin_users WHERE username = ?", [$username]);
        $passwordHash = trim($record['password'] ?? '');
        if ($record && $passwordHash !== '' && \password_verify($plainPassword, $passwordHash)) {
            $_SESSION['admin_auth'] = true;
            $_SESSION['admin_user'] = $record['username'];
            $this->clearAdminAttempts($ip, $username);
            $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
            SecurityLog::log('login_success', ['user' => $username]);
            return new Response('', 302, ['Location' => $prefix]);
        }
        $this->logAdminAttempt($ip, $username, $ua);
        if ($this->isRateLimited($ip, $username)) {
            $html = $this->container->get('renderer')->render('admin/login', [
                'title' => 'Admin Login',
                'error' => 'Too many attempts, please wait',
                'csrf' => Csrf::token('admin_login'),
                'captcha' => $captcha->config(),
            ]);
            return new Response($html, 429);
        }
        Logger::log("Admin login failed for user={$username}");
        SecurityLog::log('login_fail', ['user' => $username]);
        $html = $this->container->get('renderer')->render('admin/login', [
            'title' => 'Admin Login',
            'error' => 'Invalid credentials',
            'csrf' => Csrf::token('admin_login'),
            'captcha' => $captcha->config(),
        ]);
        return new Response($html, 401);
    }

    public function logout(Request $request): Response
    {
        if (!Csrf::check('admin_logout', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        unset($_SESSION['admin_auth'], $_SESSION['admin_user']);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/login']);
    }

    private function isRateLimited(string $ip, string $user): bool
    {
        if ($ip === '' || $user === '') {
            return false;
        }
        $since = date('Y-m-d H:i:s', time() - $this->lockSeconds);
        $pattern = $this->adminUaPrefix($user) . '%';
        try {
            $row = $this->db->fetch(
                "SELECT COUNT(*) AS cnt FROM login_logs WHERE user_id IS NULL AND ip = ? AND ua LIKE ? AND created_at >= ?",
                [$ip, $pattern, $since]
            );
            return (int)($row['cnt'] ?? 0) >= $this->maxAttempts;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function logAdminAttempt(string $ip, string $user, string $ua): void
    {
        if ($ip === '' || $user === '') {
            return;
        }
        $uaValue = $this->buildAdminUa($user, $ua);
        try {
            $this->db->execute(
                "INSERT INTO login_logs (user_id, ip, ua, created_at) VALUES (?, ?, ?, NOW())",
                [null, $ip, $uaValue]
            );
        } catch (\Throwable $e) {
            return;
        }
    }

    private function clearAdminAttempts(string $ip, string $user): void
    {
        if ($ip === '' || $user === '') {
            return;
        }
        $pattern = $this->adminUaPrefix($user) . '%';
        try {
            $this->db->execute(
                "DELETE FROM login_logs WHERE user_id IS NULL AND ip = ? AND ua LIKE ?",
                [$ip, $pattern]
            );
        } catch (\Throwable $e) {
            return;
        }
    }

    private function adminUaPrefix(string $user): string
    {
        $norm = strtolower(trim($user));
        if ($norm === '') {
            $norm = 'unknown';
        }
        return 'admin:' . $norm . '|';
    }

    private function buildAdminUa(string $user, string $ua): string
    {
        $prefix = $this->adminUaPrefix($user);
        $tail = trim((string)$ua);
        if ($tail === '') {
            return $prefix;
        }
        $value = $prefix . $tail;
        if (strlen($value) > 255) {
            return substr($value, 0, 255);
        }
        return $value;
    }
}
