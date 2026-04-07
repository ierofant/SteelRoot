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

    private function uiLabels(): array
    {
        return [
            'eyebrow' => 'News',
            'list_title' => 'Управление новостями',
            'settings_label' => 'Настройки',
            'categories_label' => 'Категории',
            'create_label' => 'Создать новость',
            'edit_eyebrow' => 'Редактирование новости',
            'new_eyebrow' => 'Новая новость',
            'edit_title' => 'Редактировать новость',
            'create_title' => 'Создать новость',
            'comments_eyebrow' => 'Комментарии',
            'comments_title' => 'Доступ к комментированию',
            'comments_help' => 'Отдельные правила для этой новости.',
            'upload_folder_label' => 'Папка загрузки',
            'upload_folder_help' => 'Подпапка внутри',
            'upload_folder_help_suffix' => 'Оставьте пустым для корня.',
            'image_hint' => 'превью в списке',
            'cover_label' => 'Обложка новости',
            'cover_hint' => 'большое фото в новости',
            'cover_placeholder' => 'URL обложки',
            'cover_pick' => 'Выбрать обложку',
            'identity_eyebrow' => 'Identity',
            'identity_title' => 'Заголовок и маршрут',
            'seo_eyebrow' => 'SEO',
            'seo_title' => 'Превью и описания',
            'taxonomy_eyebrow' => 'Taxonomy',
            'taxonomy_title' => 'Категория, автор и теги',
            'empty_categories' => 'Категорий пока нет.',
            'manage_categories' => 'Управление категориями',
        ];
    }

    public function index(Request $request): Response
    {
        $page    = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $hasCategory = $this->columnExists('category_id') && $this->tableExists($this->categoriesTable());
        $hasAuthor = $this->columnExists('author_id') && $this->tableExists('users');
        $filters = [
            'category_id' => $hasCategory ? max(0, (int)($request->query['category_id'] ?? 0)) : 0,
            'author_id' => $hasAuthor ? max(0, (int)($request->query['author_id'] ?? 0)) : 0,
            'q' => trim((string)($request->query['q'] ?? '')),
        ];
        $allowedSort = ['created_at', 'title_en', 'title_ru', 'slug', 'views', 'likes', 'category', 'author'];
        $sort = in_array((string)($request->query['sort'] ?? ''), $allowedSort, true) ? (string)$request->query['sort'] : 'created_at';
        $dir  = (($request->query['dir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        $catSelect  = $hasCategory
            ? ", ac.name_en AS category_name_en, ac.name_ru AS category_name_ru" : '';
        $catJoin    = $catSelect !== '' ? " LEFT JOIN {$this->categoriesTable()} ac ON ac.id = a.category_id" : '';
        $authorSelect = $hasAuthor ? ", u.name AS author_name" : '';
        $authorJoin   = $authorSelect !== '' ? " LEFT JOIN users u ON u.id = a.author_id" : '';
        $sortMap = [
            'created_at' => 'a.created_at',
            'title_en' => 'a.title_en',
            'title_ru' => 'a.title_ru',
            'slug' => 'a.slug',
            'views' => 'a.views',
            'likes' => 'a.likes',
            'category' => $this->localeMode === 'ru'
                ? "COALESCE(NULLIF(ac.name_ru, ''), ac.name_en, '')"
                : "COALESCE(NULLIF(ac.name_en, ''), ac.name_ru, '')",
            'author' => "COALESCE(NULLIF(u.name, ''), '')",
        ];
        if (($sort === 'category' && !$hasCategory) || ($sort === 'author' && !$hasAuthor)) {
            $sort = 'created_at';
        }
        $sortSql = $sortMap[$sort] ?? 'a.created_at';

        $whereParts = [];
        $params     = [];
        if ($filters['category_id'] > 0 && $hasCategory) {
            $whereParts[] = 'a.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }
        if ($filters['author_id'] > 0 && $hasAuthor) {
            $whereParts[] = 'a.author_id = :author_id';
            $params[':author_id'] = $filters['author_id'];
        }
        if ($filters['q'] !== '') {
            $whereParts[] = '(a.title_en LIKE :q OR a.title_ru LIKE :q OR a.slug LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        $whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

        $total  = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()} a {$whereSql}", $params)['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT a.*{$catSelect}{$authorSelect}
             FROM {$this->table()} a {$catJoin} {$authorJoin}
             {$whereSql}
             ORDER BY {$sortSql} {$dir} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $html = $this->container->get('renderer')->render('articles/form', [
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
            'sort'       => $sort,
            'dir'        => $dir,
            'filters'    => $filters,
            'adminBase'  => $this->adminBase(),
            'categoriesBase' => $this->adminBase() . '/categories',
            'settingsBase' => $this->adminBase() . '/settings',
            'contentType' => 'news',
            'uploadRoot' => '/storage/uploads/news',
            'ui' => $this->uiLabels(),
        ]);

        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('articles/form', [
            'title'      => 'Create News',
            'mode'       => 'create',
            'article'    => null,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => ['mode' => 'default', 'group_ids' => []],
            'commentsUi' => $this->commentsUiState($this->entityType()),
            'uploadFolder' => $this->defaultUploadFolder(),
            'return'     => $this->resolveReturnUrl($request),
            'adminBase'  => $this->adminBase(),
            'categoriesBase' => $this->adminBase() . '/categories',
            'settingsBase' => $this->adminBase() . '/settings',
            'contentType' => 'news',
            'uploadRoot' => '/storage/uploads/news',
            'ui' => $this->uiLabels(),
        ]);
        return new Response($html);
    }

    public function edit(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        $html = $this->container->get('renderer')->render('articles/form', [
            'title'      => 'Edit News',
            'mode'       => 'edit',
            'article'    => $item,
            'csrf'       => Csrf::token('articles_form'),
            'uploadCsrf' => Csrf::token('article_upload'),
            'tags'       => $this->tags->forEntity($this->entityType(), (int)($item['id'] ?? 0)),
            'categories' => $this->loadCategories(),
            'localeMode' => $this->localeMode,
            'users'      => $this->loadUsers(),
            'commentGroups' => $this->loadCommentGroups(),
            'commentPolicy' => $this->commentPolicyService()->load($this->entityType(), (int)($item['id'] ?? 0)),
            'commentsUi' => $this->commentsUiState($this->entityType()),
            'uploadFolder' => $this->detectUploadFolder($item) ?: $this->defaultUploadFolder(),
            'return'     => $this->resolveReturnUrl($request),
            'adminBase'  => $this->adminBase(),
            'categoriesBase' => $this->adminBase() . '/categories',
            'settingsBase' => $this->adminBase() . '/settings',
            'contentType' => 'news',
            'uploadRoot' => '/storage/uploads/news',
            'ui' => $this->uiLabels(),
        ]);
        return new Response($html);
    }

    public function settings(Request $request): Response
    {
        $settings = array_merge([
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
            'description_enabled' => true,
            'grid_cols' => 3,
            'per_page' => 9,
            'seo_title_en' => '',
            'seo_title_ru' => '',
            'seo_desc_en' => '',
            'seo_desc_ru' => '',
            'default_upload_folder' => '',
        ], $this->moduleSettings->all($this->moduleKey()));
        $html = $this->container->get('renderer')->render('news/admin/settings', [
            'title'    => 'News Settings',
            'csrf'     => Csrf::token('news_settings'),
            'settings' => $settings,
            'flash'    => (($request->query['msg'] ?? '') === 'saved') ? 'Saved' : null,
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('news_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        foreach ([
            'show_author' => 'news_show_author',
            'show_date' => 'news_show_date',
            'show_likes' => 'news_show_likes',
            'show_views' => 'news_show_views',
            'show_tags' => 'news_show_tags',
            'description_enabled' => 'news_description_enabled',
        ] as $key => $field) {
            $this->moduleSettings->set($this->moduleKey(), $key, !empty($request->body[$field]));
        }

        $gridCols = max(1, min(6, (int)($request->body['news_grid_cols'] ?? 3)));
        $perPage = max(1, min(500, (int)($request->body['news_per_page'] ?? 9)));
        $this->moduleSettings->set($this->moduleKey(), 'grid_cols', $gridCols);
        $this->moduleSettings->set($this->moduleKey(), 'per_page', $perPage);
        $this->moduleSettings->set($this->moduleKey(), 'seo_title_en', trim((string)($request->body['news_seo_title_en'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_title_ru', trim((string)($request->body['news_seo_title_ru'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_desc_en', trim((string)($request->body['news_seo_desc_en'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'seo_desc_ru', trim((string)($request->body['news_seo_desc_ru'] ?? '')));
        $this->moduleSettings->set($this->moduleKey(), 'default_upload_folder', $this->normalizeUploadFolder((string)($request->body['news_default_upload_folder'] ?? '')));

        return new Response('', 302, ['Location' => $this->adminBase() . '/settings?msg=saved']);
    }

    private function commentsUiState(string $entityType): array
    {
        $settings = $this->moduleSettings->all('comments');
        $enabled = !empty($settings['enabled']);
        $entityEnabled = in_array($entityType, (array)($settings['enabled_entity_types'] ?? []), true);

        return [
            'available' => $enabled && $entityEnabled,
            'enabled' => $enabled,
            'entity_enabled' => $entityEnabled,
            'settings_url' => ($this->container->get('config')['admin_prefix'] ?? '/admin') . '/comments/settings',
        ];
    }
}
