<?php
use Core\Router;
use Modules\Users\Services\Auth;
use Core\Slot;
use Modules\Users\Services\UserRepository;
use Modules\Users\Services\AvatarService;
use Modules\Users\Services\ProfileCoverService;
use Modules\Users\Services\PhotoCopyrightService;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\CollectionService;
use Modules\Users\Services\CommunityPollService;
use Modules\Users\Services\ProfilePanelService;
use Modules\Users\Services\MasterContactService;
use App\Services\SettingsService;

return function (Router $router, ?\Core\Container $container = null) {
    if ($container) {
        $container->singleton(UserRepository::class, function ($c) {
            return new UserRepository($c->get(\Core\Database::class));
        });
        $container->singleton(Auth::class, function ($c) {
            return new Auth($c->get(UserRepository::class), $c->get('config'));
        });
        $container->singleton(UserAccessService::class, function ($c) {
            return new UserAccessService($c->get(UserRepository::class));
        });
        $container->singleton(CollectionService::class, function ($c) {
            return new CollectionService($c->get(\Core\Database::class));
        });
        $container->singleton(ProfilePanelService::class, function ($c) {
            return new ProfilePanelService($c->get(\Core\Database::class));
        });
        $container->singleton(CommunityPollService::class, function ($c) {
            return new CommunityPollService($c->get(\Core\Database::class), $c->get(\Core\ModuleSettings::class));
        });
        $container->singleton(MasterContactService::class, function ($c) {
            return new MasterContactService(
                $c->get(\Core\Database::class),
                $c->get(\Core\ModuleSettings::class),
                $c->get(SettingsService::class),
                $c->get(UserRepository::class)
            );
        });
        $container->singleton(AvatarService::class, function ($c) {
            return new AvatarService(APP_ROOT . '/storage/uploads/users');
        });
        $container->singleton(ProfileCoverService::class, function ($c) {
            return new ProfileCoverService();
        });
        $container->singleton(PhotoCopyrightService::class, function ($c) {
            return new PhotoCopyrightService();
        });
        Slot::register('user-nav', function () use ($container) {
            try {
                /** @var Auth $auth */
                $auth = $container->get(Auth::class);
                $user = $auth->user();
                $renderer = $container->get('renderer');
                if ($user) {
                    $fullUser = $container->get(UserRepository::class)->findFull((int)($user['id'] ?? 0)) ?? $user;
                    $panelFeedHtml = '';
                    if (!array_key_exists('show_personal_feed', $fullUser) || !empty($fullUser['show_personal_feed'])) {
                        $panelSections = $container->get(ProfilePanelService::class)->sectionsForUser($fullUser);
                        $panelFeedHtml = $renderer->render('users/profile_panel_feed', [
                            'panelSections' => $panelSections,
                        ]);
                    }
                    return $renderer->render('users/nav_logged', [
                        'user' => $fullUser,
                        'panelFeedHtml' => $panelFeedHtml,
                    ]);
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

    $router->get('/forgot-password',  [Modules\Users\Controllers\AuthController::class, 'forgotForm'],   [$security]);
    $router->post('/forgot-password', [Modules\Users\Controllers\AuthController::class, 'forgotSubmit'], [$security]);
    $router->get('/reset-password',   [Modules\Users\Controllers\AuthController::class, 'resetForm'],    [$security]);
    $router->post('/reset-password',  [Modules\Users\Controllers\AuthController::class, 'resetSubmit'],  [$security]);

    $router->get('/users', [Modules\Users\Controllers\ProfileController::class, 'publicIndex'], [$security]);
    $router->get('/users/out/{id}/{platform}', [Modules\Users\Controllers\ExternalLinkController::class, 'redirect'], [$security]);
    $router->get('/users/{id}/works', [Modules\Users\Controllers\ProfileController::class, 'publicWorks'], [$security]);
    $router->get('/users/{id}/contact', [Modules\Users\Controllers\MasterContactController::class, 'form'], [$security]);
    $router->post('/users/{id}/contact', [Modules\Users\Controllers\MasterContactController::class, 'submit'], [$security]);
    $router->get('/users/{id}', [Modules\Users\Controllers\ProfileController::class, 'publicProfile'], [$security]);
    $router->get('/profile', [Modules\Users\Controllers\ProfileController::class, 'show'], [$security, $authRequired]);
    $router->post('/profile/update', [Modules\Users\Controllers\ProfileController::class, 'update'], [$security, $authRequired]);
    $router->post('/profile/collections/create', [Modules\Users\Controllers\CollectionsController::class, 'create'], [$security, $authRequired]);
    $router->post('/profile/collections/{id}/delete', [Modules\Users\Controllers\CollectionsController::class, 'delete'], [$security, $authRequired]);
    $router->post('/profile/collections/{id}/items/remove/{itemId}', [Modules\Users\Controllers\CollectionsController::class, 'removeItem'], [$security, $authRequired]);
    $router->post('/profile/collections/quick-save', [Modules\Users\Controllers\CollectionsController::class, 'quickSave'], [$security, $authRequired]);
    $router->get('/profile/works', [Modules\Users\Controllers\MasterWorksController::class, 'show'], [$security, $authRequired]);
    $router->post('/profile/works', [Modules\Users\Controllers\MasterWorksController::class, 'upload'], [$security, $authRequired]);
    $router->post('/favorites/toggle', [Modules\Users\Controllers\FavoritesController::class, 'toggle'], [$security, $authRequired]);
    $router->post('/profile/avatar', [Modules\Users\Controllers\ProfileController::class, 'avatar'], [$security, $authRequired]);
    $router->get('/profile/avatar/editor', [Modules\Users\Controllers\ProfileController::class, 'avatarEditor'], [$security, $authRequired]);
    $router->post('/profile/avatar/crop', [Modules\Users\Controllers\ProfileController::class, 'avatarCrop'], [$security, $authRequired]);
    $router->post('/profile/cover', [Modules\Users\Controllers\ProfileController::class, 'cover'], [$security, $authRequired]);
    $router->post('/profile/community-poll', [Modules\Users\Controllers\CommunityPollController::class, 'submit'], [$security, $authRequired]);
    $router->get('/profile/master-requests', [Modules\Users\Controllers\MasterContactController::class, 'inbox'], [$security, $authRequired]);
    $router->get('/profile/master-requests/{id}', [Modules\Users\Controllers\MasterContactController::class, 'inbox'], [$security, $authRequired]);
    $router->get('/profile/my-requests', [Modules\Users\Controllers\MasterContactController::class, 'clientInbox'], [$security, $authRequired]);
    $router->get('/profile/my-requests/{id}', [Modules\Users\Controllers\MasterContactController::class, 'clientInbox'], [$security, $authRequired]);
    $router->post('/profile/master-requests/{id}/status', [Modules\Users\Controllers\MasterContactController::class, 'updateStatus'], [$security, $authRequired]);
    $router->post('/profile/master-contact-settings', [Modules\Users\Controllers\MasterContactController::class, 'updateSettings'], [$security, $authRequired]);
    $router->post('/profile/master-contact-settings/telegram-bind', [Modules\Users\Controllers\MasterContactController::class, 'telegramBind'], [$security, $authRequired]);
    $router->post('/profile/master-contact-settings/telegram-unbind', [Modules\Users\Controllers\MasterContactController::class, 'telegramUnbind'], [$security, $authRequired]);
    $router->get('/profile/master-requests/files/{id}', [Modules\Users\Controllers\MasterContactController::class, 'file'], [$security, $authRequired]);
    $router->get('/profile/my-requests/files/{id}', [Modules\Users\Controllers\MasterContactController::class, 'clientFile'], [$security, $authRequired]);

    $prefix = '/admin';
    if ($container) {
        $cfg = $container->get('config');
        $prefix = $cfg['admin_prefix'] ?? '/admin';
    }

    $router->group($prefix . '/users', [$security, $adminGate], function (Router $r) {
        $r->get('/', [Modules\Users\Controllers\AdminUsersController::class, 'index']);
        $r->get('/export.csv', [Modules\Users\Controllers\AdminUsersController::class, 'export']);
        $r->get('/create', [Modules\Users\Controllers\AdminUsersController::class, 'create']);
        $r->post('/create', [Modules\Users\Controllers\AdminUsersController::class, 'store']);
        $r->get('/edit/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'edit']);
        $r->post('/edit/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'update']);
        $r->post('/delete/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'delete']);
        $r->post('/bulk-delete', [Modules\Users\Controllers\AdminUsersController::class, 'bulkDelete']);
        $r->post('/block/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'block']);
        $r->post('/unblock/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'unblock']);
        $r->post('/reset-password/{id}', [Modules\Users\Controllers\AdminUsersController::class, 'resetPassword']);
        $r->get('/settings', [Modules\Users\Controllers\AdminUsersController::class, 'settings']);
        $r->post('/settings', [Modules\Users\Controllers\AdminUsersController::class, 'saveSettings']);
        $r->get('/community-poll', [Modules\Users\Controllers\AdminUsersController::class, 'communityPoll']);
        $r->post('/community-poll', [Modules\Users\Controllers\AdminUsersController::class, 'saveCommunityPoll']);
        $r->get('/groups', [Modules\Users\Controllers\AdminUserGroupsController::class, 'index']);
        $r->get('/groups/create', [Modules\Users\Controllers\AdminUserGroupsController::class, 'create']);
        $r->post('/groups/create', [Modules\Users\Controllers\AdminUserGroupsController::class, 'store']);
        $r->get('/groups/edit/{id}', [Modules\Users\Controllers\AdminUserGroupsController::class, 'edit']);
        $r->post('/groups/edit/{id}', [Modules\Users\Controllers\AdminUserGroupsController::class, 'update']);
        $r->get('/plans', [Modules\Users\Controllers\AdminMasterPlansController::class, 'index']);
        $r->get('/plans/create', [Modules\Users\Controllers\AdminMasterPlansController::class, 'create']);
        $r->post('/plans/create', [Modules\Users\Controllers\AdminMasterPlansController::class, 'store']);
        $r->get('/plans/edit/{id}', [Modules\Users\Controllers\AdminMasterPlansController::class, 'edit']);
        $r->post('/plans/edit/{id}', [Modules\Users\Controllers\AdminMasterPlansController::class, 'update']);
    });
};
