<?php
namespace Modules\FAQ\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;

class FaqController
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
        $items = $this->db->fetchAll("SELECT * FROM faq_items WHERE status = 'published' ORDER BY updated_at DESC");
        $html = $this->container->get('renderer')->render(
            '@FAQ/public/index',
            [
                '_layout' => true,
                'title' => 'FAQ',
                'items' => $items,
            ],
            [
                'title' => 'FAQ',
                'canonical' => $this->canonical($request),
                'description' => 'Frequently asked questions.',
            ]
        );
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
        return $base . ($request->path ?? '/');
    }
}
