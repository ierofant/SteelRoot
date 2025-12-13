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
        if ($this->isLocked()) {
            return new Response('Too many attempts, please wait', 429);
        }
        /** @var CaptchaService $captcha */
        $captcha = $this->container->get(\App\Services\CaptchaService::class);
        $cfg = $captcha->config();
        if (!empty($cfg['enable_admin_login']) && $cfg['provider'] !== 'none') {
            if (!$captcha->verify($request)) {
                return new Response('Captcha failed', 400);
            }
        }
        $user = $request->body['user'] ?? '';
        $pass = $request->body['pass'] ?? '';
        $record = $this->db->fetch("SELECT * FROM admin_users WHERE username = ?", [$user]);
        if ($record && password_verify($pass, $record['password'])) {
            $_SESSION['admin_auth'] = true;
            $_SESSION['admin_user'] = $record['username'];
            unset($_SESSION['admin_lock']);
            $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
            SecurityLog::log('login_success', ['user' => $user]);
            return new Response('', 302, ['Location' => $prefix]);
        }
        $this->failAttempt();
        Logger::log("Admin login failed for user={$user}");
        SecurityLog::log('login_fail', ['user' => $user]);
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

    private function failAttempt(): void
    {
        $lock = $_SESSION['admin_lock'] ?? ['count' => 0, 'time' => time()];
        if (time() - $lock['time'] > $this->lockSeconds) {
            $lock = ['count' => 0, 'time' => time()];
        }
        $lock['count']++;
        $lock['time'] = time();
        $_SESSION['admin_lock'] = $lock;
    }

    private function isLocked(): bool
    {
        $lock = $_SESSION['admin_lock'] ?? null;
        if (!$lock) {
            return false;
        }
        if (time() - $lock['time'] > $this->lockSeconds) {
            unset($_SESSION['admin_lock']);
            return false;
        }
        return $lock['count'] >= $this->maxAttempts;
    }
}
