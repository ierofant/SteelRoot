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
        $tags = $this->db->fetchAll("SELECT name, slug FROM tags ORDER BY name ASC");
        $html = $this->container->get('renderer')->render('tags/index', [
            'title' => 'Все теги',
            'tags' => $tags,
            'meta' => [
                'title' => 'Теги',
                'canonical' => $this->canonical($request),
            ],
        ]);
        return new Response($html);
    }

    private function canonical(Request $request): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base . '/tags';
    }
}
