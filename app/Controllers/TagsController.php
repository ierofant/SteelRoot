<?php
namespace App\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;

class TagsController
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
        $top = ($request->params['slug'] ?? null) === 'top';
        if ($top) {
            $tags = $this->db->fetchAll("
                SELECT t.name, t.slug, COUNT(*) AS uses
                FROM tags t
                JOIN taggables tg ON tg.tag_id = t.id
                GROUP BY t.id, t.name, t.slug
                ORDER BY uses DESC, t.name ASC
                LIMIT 100
            ");
            $title = 'Популярные теги';
        } else {
            $tags = $this->db->fetchAll("SELECT name, slug FROM tags ORDER BY name ASC");
            $title = 'Все теги';
        }
        $html = $this->container->get('renderer')->render(
            'tags/index',
            [
                'title' => $title,
                'tags' => $tags,
                '_layout' => true,
            ],
            [
                'title' => $title,
                'canonical' => $this->canonical($request, $top),
            ]
        );
        return new Response($html);
    }

    private function canonical(Request $request, bool $top = false): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . ($top ? '/tags/top' : '/tags');
    }
}
