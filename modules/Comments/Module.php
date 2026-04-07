<?php
declare(strict_types=1);

namespace Modules\Comments;

use Core\Container;
use Core\ModuleSettings;
use Core\Router;
use Modules\Comments\Controllers\AdminCommentsController;
use Modules\Comments\Controllers\CommentsController;
use Modules\Comments\Services\CommentService;
use Modules\Comments\Services\EntityCommentPolicyService;

class Module
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register(Container $container, Router $router): void
    {
        $container->singleton(CommentService::class, function (Container $c) {
            return new CommentService($c, APP_ROOT . '/modules/Comments/config.php');
        });
        $container->singleton(EntityCommentPolicyService::class, function (Container $c) {
            return new EntityCommentPolicyService($c->get(\Core\Database::class));
        });

        $container->get(ModuleSettings::class)->loadDefaults('comments', require $this->path . '/config.php');

        $router->post('/comments/store', [CommentsController::class, 'store']);
        $router->post('/comments/reply', [CommentsController::class, 'store']);
        $router->get('/comments/fragment', [CommentsController::class, 'fragment']);

        $config = $container->get('config');
        $adminPrefix = $config['admin_prefix'] ?? '/admin';
        $authMiddleware = function ($req, $next) use ($adminPrefix) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: ' . $adminPrefix . '/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/comments', [$authMiddleware], function (Router $r) {
            $r->get('/', [AdminCommentsController::class, 'index']);
            $r->get('/settings', [AdminCommentsController::class, 'settings']);
            $r->post('/settings', [AdminCommentsController::class, 'saveSettings']);
            $r->post('/approve/{id}', [AdminCommentsController::class, 'approve']);
            $r->post('/reject/{id}', [AdminCommentsController::class, 'reject']);
            $r->post('/spam/{id}', [AdminCommentsController::class, 'spam']);
            $r->post('/delete/{id}', [AdminCommentsController::class, 'delete']);
            $r->post('/purge/{id}', [AdminCommentsController::class, 'purge']);
            $r->post('/bulk', [AdminCommentsController::class, 'bulk']);
        });
    }
}
