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
        $html = $this->container->get('renderer')->render('@FAQ/public/index', [
            'title' => 'FAQ',
            'items' => $items,
        ]);
        return new Response($html);
    }
}
