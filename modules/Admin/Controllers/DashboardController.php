<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleManager;

class DashboardController
{
    private Container $container;
    private Database $db;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
    }

    public function index(Request $request): Response
    {
        $stats = $this->collectStats();
        $html = $this->container->get('renderer')->render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'stats' => $stats,
            'modules' => $this->collectModules(),
        ]);
        return new Response($html);
    }

    private function collectStats(): array
    {
        $counts = ['articles' => 0, 'gallery' => 0, 'users' => 0, 'views_articles' => 0, 'likes_articles' => 0, 'views_gallery' => 0, 'likes_gallery' => 0, 'modules' => 0];
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS c, SUM(views) AS v, SUM(likes) AS l FROM articles");
            $counts['articles'] = (int)($row['c'] ?? 0);
            $counts['views_articles'] = (int)($row['v'] ?? 0);
            $counts['likes_articles'] = (int)($row['l'] ?? 0);
        } catch (\Throwable $e) {
            // ignore if table not ready
        }
        try {
            $manager = $this->container->get(ModuleManager::class);
            $modules = $manager->list();
            $counts['modules'] = count(array_filter($modules, static fn($m) => !empty($m['enabled'])));
        } catch (\Throwable $e) {
            // module manager may be unavailable early in install
        }
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS c, SUM(views) AS v, SUM(likes) AS l FROM gallery_items");
            $counts['gallery'] = (int)($row['c'] ?? 0);
            $counts['views_gallery'] = (int)($row['v'] ?? 0);
            $counts['likes_gallery'] = (int)($row['l'] ?? 0);
        } catch (\Throwable $e) {
        }
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS c FROM admin_users");
            $counts['users'] = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
        }
        return $counts;
    }

    private function collectModules(): array
    {
        try {
            /** @var ModuleManager $manager */
            $manager = $this->container->get(ModuleManager::class);
            $modules = $manager->list();
            $normalized = [];
            foreach ($modules as $slug => $module) {
                $normalized[] = [
                    'slug' => $slug,
                    'name' => $module['name'] ?? ucfirst($slug),
                    'enabled' => !empty($module['enabled']),
                ];
            }
            return $normalized;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
