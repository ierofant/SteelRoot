<?php
namespace Modules\News\Controllers;

use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\ContentBase\Controllers\BaseAdminController;

class AdminNewsController extends BaseAdminController
{
    protected function table(): string          { return 'news'; }
    protected function categoriesTable(): string { return 'news_categories'; }
    protected function moduleKey(): string       { return 'news'; }
    protected function adminBase(): string       { return ($this->container->get('config')['admin_prefix'] ?? '/admin') . '/news'; }
    protected function entityType(): string      { return 'news'; }
    protected function uploadSubdir(): string    { return 'news'; }

    public function index(Request $request): Response
    {
        $page    = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $q       = trim((string)($request->query['q'] ?? ''));

        $catSelect  = $this->columnExists('category_id') && $this->tableExists($this->categoriesTable())
            ? ", ac.name_en AS category_name_en, ac.name_ru AS category_name_ru" : '';
        $catJoin    = $catSelect !== '' ? " LEFT JOIN {$this->categoriesTable()} ac ON ac.id = a.category_id" : '';
        $authorSelect = $this->columnExists('author_id') && $this->tableExists('users') ? ", u.name AS author_name" : '';
        $authorJoin   = $authorSelect !== '' ? " LEFT JOIN users u ON u.id = a.author_id" : '';

        $whereParts = [];
        $params     = [];
        if ($q !== '') {
            $whereParts[] = '(a.title_en LIKE :q OR a.title_ru LIKE :q OR a.slug LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $total  = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()} a {$whereSql}", $params)['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT a.*{$catSelect}{$authorSelect}
             FROM {$this->table()} a {$catJoin} {$authorJoin}
             {$whereSql}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $html = $this->container->get('renderer')->render('news/admin/form', [
            'title'      => 'Manage News',
            'articles'   => $items,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'mode'       => 'list',
            'categories' => $this->loadCategories(),
            'users'      => $this->loadUsers(),
            'localeMode' => $this->localeMode,
            'page'       => $page,
            'total'      => $total,
            'perPage'    => $perPage,
            'sort'       => 'created_at',
            'dir'        => 'desc',
            'filters'    => ['category_id' => 0, 'author_id' => 0, 'q' => $q],
        ]);

        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('news/admin/form', [
            'title'      => 'Create News',
            'mode'       => 'create',
            'article'    => null,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'return'     => $this->resolveReturnUrl($request),
        ]);
        return new Response($html);
    }

    public function edit(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        $html = $this->container->get('renderer')->render('news/admin/form', [
            'title'      => 'Edit News',
            'mode'       => 'edit',
            'article'    => $item,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'tags'       => $this->tags->forEntity($this->entityType(), (int)($item['id'] ?? 0)),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'return'     => $this->resolveReturnUrl($request),
        ]);
        return new Response($html);
    }

    public function settings(Request $request): Response
    {
        $settings = array_merge([
            'per_page' => 9,
            'seo_title_en' => '',
            'seo_title_ru' => '',
            'seo_desc_en' => '',
            'seo_desc_ru' => '',
        ], $this->moduleSettings->all($this->moduleKey()));
        $html = $this->container->get('renderer')->render('news/admin/settings', [
            'title'    => 'News Settings',
            'csrf'     => Csrf::token('news_settings'),
            'settings' => $settings,
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('news_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $perPage = max(1, min(500, (int)($request->body['news_per_page'] ?? 9)));
        $this->moduleSettings->set($this->moduleKey(), 'per_page', $perPage);
        $this->moduleSettings->set($this->moduleKey(), 'seo_title_en', trim((string)($request->body['news_seo_title_en'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_title_ru', trim((string)($request->body['news_seo_title_ru'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_desc_en', trim((string)($request->body['news_seo_desc_en'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_desc_ru', trim((string)($request->body['news_seo_desc_ru'] ?? '')));

        return new Response('', 302, ['Location' => $this->adminBase() . '/settings?msg=saved']);
    }
}
