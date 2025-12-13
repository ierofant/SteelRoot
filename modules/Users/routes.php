<?php
use Core\Router;
use Modules\Users\Services\Auth;
use Core\Slot;
use Modules\Users\Services\UserRepository;
use Modules\Users\Services\AvatarService;

return function (Router $router, ?\Core\Container $container = null) {
    if ($container) {
        $container->singleton(UserRepository::class, function ($c) {
            return new UserRepository($c->get(\Core\Database::class));
        });
        $container->singleton(Auth::class, function ($c) {
            return new Auth($c->get(UserRepository::class), $c->get('config'));
        });
        $container->singleton(AvatarService::class, function ($c) {
            return new AvatarService(APP_ROOT . '/storage/uploads/users');
        });
        Slot::register('user-nav', function () use ($container) {
            try {
                /** @var Auth $auth */
                $auth = $container->get(Auth::class);
                $user = $auth->user();
                $renderer = $container->get('renderer');
                if ($user) {
                    return $renderer->render('users/nav_logged', ['user' => $user]);
                }
                return $renderer->render('users/nav_guest');
            } catch (\Throwable $e) {
                return '';
            }
        });
    }
    $security = function ($req, $next, $container = null) {
        $config = $container?->get('config')['modules']['users'] ?? [];
        $ip = $req->server['REMOTE_ADDR'] ?? '';
        $ua = $req->headers['user-agent'] ?? ($req->server['HTTP_USER_AGENT'] ?? '');
        $blockedIps = array_filter(array_map('trim', $config['block_ips'] ?? []));
        $blockedUa = array_filter(array_map('trim', $config['block_user_agents'] ?? []));
        if ($ip !== '' && in_array($ip, $blockedIps, true)) {
            return new \Core\Response('Forbidden', 403);
        }
        if ($ua !== '' && $blockedUa) {
            foreach ($blockedUa as $pattern) {
                if ($pattern !== '' && stripos($ua, $pattern) !== false) {
                    return new \Core\Response('Forbidden', 403);
                }
            }
        }
        return $next($req);
    };

    $authRequired = function ($req, $next, $container = null) {
        /** @var Auth $auth */
        $auth = $container->get(Auth::class);
        if (!$auth->user()) {
            return new \Core\Response('', 302, ['Location' => '/login']);
        }
        return $next($req);
    };

    $adminGate = function ($req, $next, $container = null) {
        $hasAdminSession = !empty($_SESSION['admin_auth']);
        /** @var Auth $auth */
        $auth = $container->get(Auth::class);
        if ($hasAdminSession || $auth->checkRole('admin')) {
            return $next($req);
        }
        return new \Core\Response('', 302, ['Location' => '/login']);
    };

    $router->get('/register', [Modules\Users\Controllers\RegistrationController::class, 'form'], [$security]);
    $router->post('/register', [Modules\Users\Controllers\RegistrationController::class, 'register'], [$security]);
    $router->get('/confirm', [Modules\Users\Controllers\RegistrationController::class, 'confirm'], [$security]);

    $router->get('/login', [Modules\Users\Controllers\AuthController::class, 'form'], [$security]);
    $router->post('/login', [Modules\Users\Controllers\AuthController::class, 'login'], [$security]);
    $router->post('/logout', [Modules\Users\Controllers\AuthController::class, 'logout'], [$security]);

    $router->get('/users/{id}', [Modules\Users\Controllers\ProfileController::class, 'publicProfile'], [$security]);
    $router->get('/profile', [Modules\Users\Controllers\ProfileController::class, 'show'], [$security, $authRequired]);
    $router->post('/profile/update', [Modules\Users\Controllers\ProfileController::class, 'update'], [$security, $authRequired]);
    $router->post('/profile/avatar', [Modules\Users\Controllers\ProfileController::class, 'avatar'], [$security, $authRequired]);
    $router->get('/profile/avatar/editor', [Modules\Users\Controllers\ProfileController::class, 'avatarEditor'], [$security, $authRequired]);
    $router->post('/profile/avatar/crop', [Modules\Users\Controllers\ProfileController::class, 'avatarCrop'], [$security, $authRequired]);

    $prefix = '/admin';
    if ($container) {
        $cfg = $container->get('config');
        $prefix = $cfg['admin_prefix'] ?? '/admin';
    }

    $router->group($prefix . '/users', [$security, $adminGate], function (Router $r) {
        $r->get('/', [Modules\Users\Controllers\AdminUsersController::class, 'index']);
        $r->get('/create', [Modules\Users\Controllers\AdminUsersController::class, 'create']);
        $r->post('/create', [Modules\Users\Controllers\AdminUsersController::class, 'store']);
        $r->get('/edit/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'edit']);
        $r->post('/edit/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'update']);
        $r->post('/block/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'block']);
        $r->post('/unblock/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'unblock']);
        $r->post('/reset-password/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'resetPassword']);
        $r->get('/settings', [Modules\Users\Controllers\AdminUsersController::class, 'settings']);
        $r->post('/settings', [Modules\Users\Controllers\AdminUsersController::class, 'saveSettings']);
    });
};
