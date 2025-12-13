<?php
namespace Modules\Users\Services;

class Auth
{
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
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        $user = $this->users->find((int)$id);
        if (!$user) {
            return null;
        }
        if (($user['status'] ?? '') !== 'active') {
            return null;
        }
        $this->cachedUser = $user;
        return $user;
    }

    public function attempt(string $email, string $password, string $ip = '', string $ua = ''): array
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
        $this->users->logLogin((int)$user['id'], $ip, $ua);
        return [true, ''];
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user_status']);
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

    public function loginDirect(array $user): void
    {
        if (($user['status'] ?? '') !== 'active') {
            return;
        }
        $this->setSession($user);
        $this->users->logLogin((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    private function setSession(array $user): void
    {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['user_status'] = $user['status'] ?? 'active';
        $this->cachedUser = $user;
    }
}
