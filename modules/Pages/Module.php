<?php
namespace Modules\Pages;

use Core\Container;
use Core\Router;
use Core\Database;

class Module
{
    private string $path;
    private static array $menuItems = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function register(Container $container, Router $router): void
    {
        $db = $container->get(Database::class);
        $config = $container->get('config');
        $adminPrefix = $config['admin_prefix'] ?? '/admin';

        // Frontend routes: register only visible pages with defined slugs
        try {
            $pages = $db->fetchAll("SELECT slug FROM pages WHERE visible = 1");
            foreach ($pages as $row) {
                $slug = trim($row['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $router->get('/' . $slug, function ($req) use ($container, $slug) {
                    $req->params['slug'] = $slug;
                    return (new Controllers\PagesController($container))->show($req);
                });
            }
            $menuRows = $db->fetchAll("
                SELECT slug, title_en, title_ru
                FROM pages
                WHERE visible = 1 AND show_in_menu = 1
                ORDER BY menu_order ASC, id ASC
            ");
            self::$menuItems = array_map(function ($page) {
                $url = '/' . ltrim($page['slug'] ?? '', '/');
                return [
                    'label_en' => $page['title_en'] ?? '',
                    'label_ru' => $page['title_ru'] ?? '',
                    'url' => $url,
                    'enabled' => true,
                    'requires_admin' => false,
                ];
            }, array_filter($menuRows, fn($p) => !empty($p['slug'])));
        } catch (\Throwable $e) {
            // ignore if table not ready
        }

        // Admin routes
        $authMiddleware = function ($req, $next) {
            if (empty($_SESSION['admin_auth'])) {
                header('Location: /admin/login');
                exit;
            }
            return $next($req);
        };

        $router->group($adminPrefix . '/pages', [$authMiddleware], function (Router $r) {
            $r->get('/', [Controllers\AdminPagesController::class, 'index']);
            $r->get('/create', [Controllers\AdminPagesController::class, 'create']);
            $r->post('/create', [Controllers\AdminPagesController::class, 'store']);
            $r->get('/edit/{id}', [Controllers\AdminPagesController::class, 'edit']);
            $r->post('/edit/{id}', [Controllers\AdminPagesController::class, 'update']);
            $r->post('/delete/{id}', [Controllers\AdminPagesController::class, 'delete']);
        });
    }

    public static function appendMenuItems(array &$settings): void
    {
        if (empty(self::$menuItems)) {
            return;
        }
        $menuRaw = $settings['menu_schema'] ?? '';
        $menuDecoded = $menuRaw ? json_decode($menuRaw, true) : [];
        if (!is_array($menuDecoded)) {
            $menuDecoded = [];
        }
        $existingUrls = array_map(fn($item) => $item['url'] ?? '', $menuDecoded);
        foreach (self::$menuItems as $item) {
            if (!$item['url'] || in_array($item['url'], $existingUrls, true)) {
                continue;
            }
            $menuDecoded[] = $item;
            $existingUrls[] = $item['url'];
        }
        if ($menuDecoded) {
            $settings['menu_schema'] = json_encode($menuDecoded, JSON_UNESCAPED_UNICODE);
        }
    }
}
