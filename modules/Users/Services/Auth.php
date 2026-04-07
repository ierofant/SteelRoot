<?php
namespace Modules\Users\Services;

class Auth
{
    private const REMEMBER_COOKIE = 'tt_remember';
    private const REMEMBER_TTL = 2592000;

    private UserRepository $users;
    private array $config;
    private ?array $cachedUser = null;

    public function __construct(UserRepository $users, $config)
    {
        $this->users = $users;
        if ($config instanceof \Core\Config) {
            $config = $config->all();
        }
        $this->config = is_array($config) ? ($config['modules']['users'] ?? []) : [];
    }

    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $this->ensureSessionForRememberCookie();

        $id = $_SESSION['user_id'] ?? null;
        if ($id) {
            $user = $this->users->findFull((int)$id);
            if ($user && ($user['status'] ?? '') === 'active') {
                $this->cachedUser = $user;
                $this->users->touchLastSeen((int)$id);
                return $user;
            }

            $this->clearUserSession();
        }

        return $this->restoreRememberedUser();
    }

    public function attempt(string $email, string $password, string $ip = '', string $ua = '', bool $remember = false): array
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->users->logLogin(null, $ip, $ua);
            return [false, 'Invalid credentials'];
        }
        if (($user['status'] ?? '') === 'blocked') {
            $this->users->logLogin((int)$user['id'], $ip, $ua);
            return [false, 'Account is blocked'];
        }
        if (($user['status'] ?? '') === 'pending') {
            $this->users->logLogin((int)$user['id'], $ip, $ua);
            return [false, 'Please confirm your email or wait for activation'];
        }
        if (!password_verify($password, $user['password'] ?? '')) {
            $this->users->logLogin((int)$user['id'], $ip, $ua);
            return [false, 'Invalid credentials'];
        }
        $this->setSession($user);
        if ($remember) {
            $this->issueRememberCookie((int)$user['id'], $ip, $ua);
        } else {
            $this->clearRememberCookie();
        }
        $this->users->logLogin((int)$user['id'], $ip, $ua);
        return [true, ''];
    }

    public function logout(): void
    {
        $selector = $this->rememberSelectorFromCookie();
        if ($selector !== null) {
            $this->users->deleteRememberToken($selector);
        }

        $this->clearUserSession();
        $this->clearRememberCookie();
        $this->cachedUser = null;
    }

    public function checkRole(string $role): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        $rank = ['user' => 1, 'editor' => 2, 'admin' => 3];
        $current = $rank[$user['role'] ?? 'user'] ?? 0;
        $need = $rank[$role] ?? 0;
        return $current >= $need && ($user['status'] ?? '') === 'active';
    }

    public function requireRole(string $role): callable
    {
        return function ($req, $next, $container = null) use ($role) {
            /** @var self $auth */
            $auth = $container->get(self::class);
            if (!$auth->checkRole($role)) {
                return new \Core\Response('', 302, ['Location' => '/login']);
            }
            return $next($req);
        };
    }

    public function activationRequired(): bool
    {
        return (bool)($this->config['require_email_confirmation'] ?? false);
    }

    public function loginDirect(array $user, bool $remember = false): void
    {
        if (($user['status'] ?? '') !== 'active') {
            return;
        }
        $this->setSession($user);
        if ($remember) {
            $this->issueRememberCookie((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
        }
        $this->users->logLogin((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    private function setSession(array $user): void
    {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['user_status'] = $user['status'] ?? 'active';
        $this->cachedUser = $user;
    }

    private function restoreRememberedUser(): ?array
    {
        [$selector, $token] = $this->rememberPartsFromCookie();
        if ($selector === null || $token === null) {
            return null;
        }

        $row = $this->users->findRememberToken($selector);
        if (!$row) {
            $this->clearRememberCookie();
            return null;
        }

        if (!password_verify($token, (string)($row['token_hash'] ?? ''))) {
            $this->users->deleteRememberToken($selector);
            $this->clearRememberCookie();
            return null;
        }

        $userId = (int)($row['user_id'] ?? 0);
        $user = $userId > 0 ? $this->users->findFull($userId) : null;
        if (!$user || ($user['status'] ?? '') !== 'active') {
            $this->users->deleteRememberToken($selector);
            $this->clearRememberCookie();
            return null;
        }

        $this->setSession($user);
        $this->users->touchLastSeen($userId);
        $this->rotateRememberCookie($selector, $userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

        return $user;
    }

    private function issueRememberCookie(int $userId, string $ip, string $ua): void
    {
        $selector = bin2hex(random_bytes(9));
        $token = bin2hex(random_bytes(32));
        $this->users->storeRememberToken($userId, $selector, password_hash($token, PASSWORD_DEFAULT), self::REMEMBER_TTL, $ip, $ua);
        $this->setRememberCookieValue($selector . ':' . $token, time() + self::REMEMBER_TTL);
    }

    private function rotateRememberCookie(string $selector, int $userId, string $ip, string $ua): void
    {
        $token = bin2hex(random_bytes(32));
        $this->users->rotateRememberToken($selector, password_hash($token, PASSWORD_DEFAULT), self::REMEMBER_TTL, $ip, $ua);
        $this->setRememberCookieValue($selector . ':' . $token, time() + self::REMEMBER_TTL);
    }

    private function clearRememberCookie(): void
    {
        unset($_COOKIE[self::REMEMBER_COOKIE]);
        $this->setRememberCookieValue('', time() - 3600);
    }

    private function rememberPartsFromCookie(): array
    {
        $raw = (string)($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($raw === '' || strpos($raw, ':') === false) {
            return [null, null];
        }

        [$selector, $token] = explode(':', $raw, 2);
        $selector = preg_match('/^[a-f0-9]{18}$/', $selector) ? $selector : null;
        $token = preg_match('/^[a-f0-9]{64}$/', $token) ? $token : null;

        return [$selector, $token];
    }

    private function rememberSelectorFromCookie(): ?string
    {
        [$selector] = $this->rememberPartsFromCookie();
        return $selector;
    }

    private function clearUserSession(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_status']);
    }

    private function ensureSessionForRememberCookie(): void
    {
        if (!empty($_SESSION) || empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    private function setRememberCookieValue(string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
