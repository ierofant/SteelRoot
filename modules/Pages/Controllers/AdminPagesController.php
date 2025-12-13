<?php
namespace Modules\Pages\Controllers;

use Core\Container;
use Core\Database;
use Core\Csrf;
use Core\Request;
use Core\Response;

class AdminPagesController
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
        $pages = $this->db->fetchAll("SELECT * FROM pages ORDER BY id DESC");
        $html = $this->container->get('renderer')->render('pages/admin/index', [
            'title' => __('pages.admin.title'),
            'pages' => $pages,
            'csrf' => Csrf::token('pages_delete'),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('pages/admin/edit', [
            'title' => __('pages.admin.create'),
            'csrf' => Csrf::token('pages_save'),
            'page' => [
                'slug' => '',
                'title_en' => '',
                'title_ru' => '',
                'content_en' => '',
                'content_ru' => '',
                'meta_title_en' => '',
                'meta_title_ru' => '',
                'meta_description_en' => '',
                'meta_description_ru' => '',
                'visible' => 1,
                'show_in_menu' => 0,
                'menu_order' => 0,
            ],
            'isNew' => true,
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('pages_save', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $data = $this->sanitize($request);
        if ($this->slugExists($data['slug'])) {
            return new Response(__('pages.admin.errors.slug_exists'), 400);
        }
        $this->db->execute("
            INSERT INTO pages (slug, title_en, title_ru, content_en, content_ru, meta_title_en, meta_title_ru, meta_description_en, meta_description_ru, visible, show_in_menu, menu_order, created_at, updated_at)
            VALUES (:slug, :title_en, :title_ru, :content_en, :content_ru, :meta_title_en, :meta_title_ru, :meta_description_en, :meta_description_ru, :visible, :show_in_menu, :menu_order, NOW(), NOW())
        ", $data);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $page = $this->db->fetch("SELECT * FROM pages WHERE id = ?", [$id]);
        if (!$page) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('pages/admin/edit', [
            'title' => __('pages.admin.edit'),
            'csrf' => Csrf::token('pages_save'),
            'page' => $page,
            'isNew' => false,
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('pages_save', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $page = $this->db->fetch("SELECT id, slug FROM pages WHERE id = ?", [$id]);
        if (!$page) {
            return new Response('Not found', 404);
        }
        $data = $this->sanitize($request);
        if ($data['slug'] !== $page['slug'] && $this->slugExists($data['slug'])) {
            return new Response(__('pages.admin.errors.slug_exists'), 400);
        }
        $data['id'] = $id;
        $this->db->execute("
            UPDATE pages SET
                slug = :slug,
                title_en = :title_en,
                title_ru = :title_ru,
                content_en = :content_en,
                content_ru = :content_ru,
                meta_title_en = :meta_title_en,
                meta_title_ru = :meta_title_ru,
                meta_description_en = :meta_description_en,
                meta_description_ru = :meta_description_ru,
                visible = :visible,
                show_in_menu = :show_in_menu,
                menu_order = :menu_order,
                updated_at = NOW()
            WHERE id = :id
        ", $data);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('pages_delete', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->db->execute("DELETE FROM pages WHERE id = ?", [$id]);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages']);
    }

    private function sanitize(Request $request): array
    {
        $slug = strtolower(trim($request->body['slug'] ?? ''));
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $slug);
        $slug = trim($slug, '-');

        return [
            'slug' => $slug,
            'title_en' => trim($request->body['title_en'] ?? ''),
            'title_ru' => trim($request->body['title_ru'] ?? ''),
            'content_en' => trim($request->body['content_en'] ?? ''),
            'content_ru' => trim($request->body['content_ru'] ?? ''),
            'meta_title_en' => trim($request->body['meta_title_en'] ?? ''),
            'meta_title_ru' => trim($request->body['meta_title_ru'] ?? ''),
            'meta_description_en' => trim($request->body['meta_description_en'] ?? ''),
            'meta_description_ru' => trim($request->body['meta_description_ru'] ?? ''),
            'visible' => !empty($request->body['visible']) ? 1 : 0,
            'show_in_menu' => !empty($request->body['show_in_menu']) ? 1 : 0,
            'menu_order' => (int)($request->body['menu_order'] ?? 0),
        ];
    }

    private function slugExists(string $slug): bool
    {
        if ($slug === '') {
            return true;
        }
        $row = $this->db->fetch("SELECT id FROM pages WHERE slug = ?", [$slug]);
        return (bool)$row;
    }
}
