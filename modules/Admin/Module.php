<?php
namespace Modules\Admin;

use Core\Container;
use Core\Router;

class Module
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register(Container $container, Router $router): void
    {
        $config = $container->get('config');
        $prefix = $config['admin_prefix'] ?? '/admin';
        $guard = function ($req) use ($container) {
            $settings = $container->get(\App\Services\SettingsService::class);
            $ipRegex = trim($settings->get('admin_ip_regex', ''));
            $blocked = array_filter(array_map('trim', explode(',', (string)$settings->get('admin_blocked_ips', ''))));
            $ip = $req->server['REMOTE_ADDR'] ?? '';
            if ($ip && in_array($ip, $blocked, true)) {
                return new \Core\Response('Forbidden', 403);
            }
            if ($ipRegex !== '') {
                if (@preg_match('/' . $ipRegex . '/', $ip) !== 1) {
                    return new \Core\Response('Forbidden', 403);
                }
            }
            $key = trim($settings->get('admin_guard_key', ''));
            if ($key !== '') {
                $provided = $req->query['ak'] ?? $req->body['ak'] ?? ($_COOKIE['admin_guard'] ?? '');
                if ($provided !== $key) {
                    return new \Core\Response('Admin key required', 403);
                }
                if (empty($_COOKIE['admin_guard']) || $_COOKIE['admin_guard'] !== $key) {
                    @setcookie('admin_guard', $key, time() + 3600 * 24 * 30, '/');
                }
            }
            return null;
        };
        $guardMiddleware = function ($req, $next, $container = null) use ($guard) {
            $resp = $guard($req);
            if ($resp instanceof \Core\Response) {
                return $resp;
            }
            return $next($req);
        };
        $authMiddleware = function ($req, $next, $container = null) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->get($prefix . '/login', [Controllers\LoginController::class, 'show'], [$guardMiddleware]);
        $router->post($prefix . '/login', [Controllers\LoginController::class, 'login'], [$guardMiddleware]);
        $router->post($prefix . '/logout', [Controllers\LoginController::class, 'logout'], [$guardMiddleware]);

        $router->group($prefix, [$guardMiddleware, $authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\DashboardController::class, 'index']);
            $r->get('/settings', [Controllers\SettingsController::class, 'index']);
            $r->post('/settings', [Controllers\SettingsController::class, 'save']);
            $r->get('/search', [Controllers\SearchSettingsController::class, 'index']);
            $r->post('/search', [Controllers\SearchSettingsController::class, 'save']);
            $r->post('/search/rebuild', [Controllers\SearchSettingsController::class, 'rebuild']);
            $r->get('/sitemap', [Controllers\SitemapController::class, 'index']);
            $r->post('/sitemap', [Controllers\SitemapController::class, 'save']);
            $r->post('/sitemap/clear-cache', [Controllers\SitemapController::class, 'clearCache']);
            $r->get('/theme', [Controllers\ThemeController::class, 'index']);
            $r->post('/theme', [Controllers\ThemeController::class, 'save']);
            $r->get('/template/errors', [Controllers\TemplateErrorsController::class, 'index']);
            $r->post('/template/errors', [Controllers\TemplateErrorsController::class, 'save']);
            $r->get('/files', [Controllers\FileManagerController::class, 'index']);
            $r->post('/files/regenerate/{id}', [Controllers\FileManagerController::class, 'regenerate']);
            $r->post('/files/delete/{id}', [Controllers\FileManagerController::class, 'delete']);
            $r->get('/pwa', [Controllers\PwaAdminController::class, 'index']);
            $r->post('/pwa', [Controllers\PwaAdminController::class, 'save']);
            $r->get('/attachments', [Controllers\AttachmentAdminController::class, 'index']);
            $r->post('/attachments/upload', [Controllers\AttachmentAdminController::class, 'upload']);
            $r->post('/attachments/delete/{name}', [Controllers\AttachmentAdminController::class, 'delete']);
            // fallback body-based delete in case path params break (dots in filenames, webserver rewrites)
            $r->post('/attachments/delete', [Controllers\AttachmentAdminController::class, 'delete']);
            $r->get('/forms', [Controllers\FormBuilderController::class, 'index']);
            $r->post('/forms', [Controllers\FormBuilderController::class, 'save']);
            $r->get('/forms/embeds', [Controllers\EmbeddableFormsController::class, 'index']);
            $r->get('/forms/embeds/create', [Controllers\EmbeddableFormsController::class, 'create']);
            $r->post('/forms/embeds/create', [Controllers\EmbeddableFormsController::class, 'store']);
            $r->get('/forms/embeds/edit/{id}', [Controllers\EmbeddableFormsController::class, 'edit']);
            $r->post('/forms/embeds/edit/{id}', [Controllers\EmbeddableFormsController::class, 'update']);
            $r->post('/forms/embeds/delete/{id}', [Controllers\EmbeddableFormsController::class, 'delete']);
            $r->get('/homepage', [Controllers\HomepageController::class, 'index']);
            $r->post('/homepage', [Controllers\HomepageController::class, 'save']);
            $r->get('/cache', [Controllers\CacheController::class, 'index']);
            $r->post('/cache/clear', [Controllers\CacheController::class, 'clear']);
            $r->post('/cache/delete', [Controllers\CacheController::class, 'delete']);
            $r->get('/docs', [Controllers\DocsController::class, 'index']);
            $r->get('/docs/support', [Controllers\DocsController::class, 'support']);
            $r->get('/profile', [Controllers\ProfileController::class, 'show']);
            $r->post('/profile', [Controllers\ProfileController::class, 'update']);
            $r->get('/modules', [Controllers\ModulesController::class, 'index']);
            $r->post('/modules/enable/{slug}', [Controllers\ModulesController::class, 'enable']);
            $r->post('/modules/disable/{slug}', [Controllers\ModulesController::class, 'disable']);
            $r->post('/modules/migrate/{slug}', [Controllers\ModulesController::class, 'migrate']);
            $r->post('/modules/rollback/{slug}', [Controllers\ModulesController::class, 'rollback']);
            $r->get('/modules/upload', [Controllers\ModulesController::class, 'upload']);
            $r->post('/modules/upload', [Controllers\ModulesController::class, 'upload']);
            $r->get('/modules/delete/{slug}', [Controllers\ModulesController::class, 'deleteConfirm']);
            $r->post('/modules/delete/{slug}', [Controllers\ModulesController::class, 'delete']);
            $r->get('/security', [Controllers\SecurityController::class, 'index']);
            $r->post('/security/block', [Controllers\SecurityController::class, 'block']);
            $r->post('/security/unblock', [Controllers\SecurityController::class, 'unblock']);
            $r->post('/security/clear', [Controllers\SecurityController::class, 'clearLogs']);
            $r->get('/redirects', [Controllers\RedirectsController::class, 'index']);
            $r->post('/redirects', [Controllers\RedirectsController::class, 'store']);
            $r->post('/redirects/clear-cache', [Controllers\RedirectsController::class, 'clearCache']);
        });
    }
}
