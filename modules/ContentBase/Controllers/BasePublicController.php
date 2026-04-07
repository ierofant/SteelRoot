<?php
namespace Modules\ContentBase\Controllers;

use Core\Container;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;

abstract class BasePublicController
{
    protected Container $container;
    protected Database $db;
    protected ModuleSettings $moduleSettings;

    abstract protected function table(): string;
    abstract protected function categoriesTable(): string;
    abstract protected function moduleKey(): string;
    abstract protected function publicBase(): string;
    abstract protected function entityType(): string;
    abstract protected function listTitle(string $locale): string;
    abstract protected function listDescription(string $locale): string;

    public function __construct(Container $container)
    {
        $this->container      = $container;
        $this->db             = $container->get(Database::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->moduleSettings->loadDefaults($this->moduleKey(), [
            'per_page' => 9,
            'grid_cols' => 3,
            'show_date' => true,
            'show_views' => true,
            'show_likes' => true,
            'show_author' => true,
            'show_tags' => true,
        ]);
    }

    public function index(Request $request): Response
    {
        $settings = $this->moduleSettings->all($this->moduleKey());
        $perPage  = max(1, (int)($settings['per_page'] ?? 9));
        $gridCols = max(1, min(6, (int)($settings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $locale   = $this->container->get('lang')->current();

        [$select, $join] = $this->buildSelect();

        $total  = (int)($this->db->fetch("SELECT COUNT(*) as cnt FROM {$this->table()}")['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        $items = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table()} a {$join}
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );

        $display = $this->mergeDisplay($settings);
        $enabledCategories = $this->loadEnabledCategories();

        $html = $this->container->get('renderer')->render('articles/list', [
            '_layout' => true,
            'title' => $this->listTitle($locale),
            'description' => $this->listDescription($locale),
            'articles' => $items,
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
            'gridCols' => $gridCols,
            'locale' => $locale,
            'display' => $display,
            'enabledCategories' => $enabledCategories,
            'paginationBase' => $this->publicBase(),
            'categoryBaseUrl' => $this->publicBase() . '/category',
            'breadcrumbs' => [['label' => $this->listTitle($locale)]],
        ], [
            'title' => $this->listTitle($locale),
            'description' => $this->listDescription($locale),
            'canonical' => $this->canonical($request),
        ]);
        return new Response($html);
    }

    public function byCategory(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $category = $this->db->fetch(
            "SELECT * FROM {$this->categoriesTable()} WHERE slug = ? AND enabled = 1",
            [$slug]
        );
        if (!$category) {
            return new Response('Not found', 404);
        }

        $settings = $this->moduleSettings->all($this->moduleKey());
        $perPage  = max(1, (int)($settings['per_page'] ?? 9));
        $gridCols = max(1, min(6, (int)($settings['grid_cols'] ?? 3)));
        $page     = max(1, (int)($request->params['page'] ?? 1));
        $locale   = $this->container->get('lang')->current();

        $total  = (int)($this->db->fetch(
            "SELECT COUNT(*) as cnt FROM {$this->table()} WHERE category_id = ?",
            [(int)$category['id']]
        )['cnt'] ?? 0);
        $offset = ($page - 1) * $perPage;

        [$select, $join] = $this->buildSelect();
        $items = $this->db->fetchAll(
            "SELECT {$select} FROM {$this->table()} a {$join}
             WHERE a.category_id = ?
             ORDER BY a.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [(int)$category['id']]
        );

        $titleKey = $locale === 'ru' ? 'name_ru' : 'name_en';
        $catTitle = $category[$titleKey] ?: ($category['name_en'] ?: $category['name_ru']);
        $display  = $this->mergeDisplay($settings);

        $html = $this->container->get('renderer')->render('articles/list', [
            '_layout' => true,
            'title' => $catTitle,
            'description' => '',
            'articles' => $items,
            'page' => $page,
            'total' => $total,
            'perPage' => $perPage,
            'gridCols' => $gridCols,
            'locale' => $locale,
            'display' => $display,
            'category' => $category,
            'paginationBase' => $this->publicBase() . '/category/' . rawurlencode($slug),
            'categoryBaseUrl' => $this->publicBase() . '/category',
            'breadcrumbs' => [
                ['label' => $this->listTitle($locale), 'url' => $this->publicBase()],
                ['label' => $catTitle],
            ],
        ], [
            'title' => $catTitle,
            'canonical' => $this->canonical($request),
        ]);
        return new Response($html);
    }

    public function view(Request $request): Response
    {
        $slug = $request->params['slug'] ?? '';
        $item = $this->db->fetch("SELECT * FROM {$this->table()} WHERE slug = ?", [$slug]);
        if (!$item) {
            return new Response('Not found', 404);
        }

        $this->db->execute("UPDATE {$this->table()} SET views = views + 1 WHERE id = ?", [$item['id']]);

        $locale   = $this->container->get('lang')->current();
        $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
        $bodyKey  = $locale === 'ru' ? 'body_ru' : 'body_en';
        $display  = $this->mergeDisplay($this->moduleSettings->all($this->moduleKey()));

        $tags = [];
        try {
            $tags = $this->db->fetchAll(
                "SELECT t.name, t.slug FROM taggables tg JOIN tags t ON t.id = tg.tag_id
                 WHERE tg.entity_type = ? AND tg.entity_id = ?",
                [$this->entityType(), (int)$item['id']]
            );
        } catch (\Throwable $e) {
        }

        $html = $this->container->get('renderer')->render('articles/item', [
            '_layout' => true,
            'title' => $item[$titleKey] ?? '',
            'article' => $item,
            'locale' => $locale,
            'tags' => $tags,
            'display' => $display,
            'breadcrumbs' => [
                ['label' => $this->listTitle($locale), 'url' => $this->publicBase()],
                ['label' => $item[$titleKey] ?? ''],
            ],
        ], [
            'title' => $item[$titleKey] ?? '',
            'description' => substr(strip_tags((string)($item[$bodyKey] ?? '')), 0, 160),
            'canonical' => $this->canonical($request),
            'image' => $item['image_url'] ?? null,
        ]);
        return new Response($html);
    }

    protected function buildSelect(): array
    {
        $select = "a.id, a.slug, a.title_en, a.title_ru, a.created_at";
        $join = '';
        if ($this->columnExists('views')) { $select .= ", a.views"; }
        if ($this->columnExists('likes')) { $select .= ", a.likes"; }
        if ($this->columnExists('preview_en')) { $select .= ", a.preview_en, a.preview_ru"; }
        if ($this->columnExists('image_url')) { $select .= ", a.image_url"; }
        if ($this->columnExists('author_id') && $this->tableExists('users')) {
            $select .= ", a.author_id";
        }
        if ($this->columnExists('category_id')) {
            $cats = $this->categoriesTable();
            $select .= ", a.category_id, ac.slug AS category_slug, ac.name_en AS category_name_en, ac.name_ru AS category_name_ru";
            $join .= " LEFT JOIN {$cats} ac ON ac.id = a.category_id";
        }
        return [$select, $join];
    }

    protected function loadEnabledCategories(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, slug, name_en, name_ru FROM {$this->categoriesTable()}
                 WHERE enabled = 1 ORDER BY position ASC, id ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function mergeDisplay(array $settings): array
    {
        return array_merge([
            'show_author' => true,
            'show_date' => true,
            'show_likes' => true,
            'show_views' => true,
            'show_tags' => true,
        ], $settings);
    }

    protected function canonical(Request $request): string
    {
        $cfg  = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . ($request->server['HTTP_HOST'] ?? 'localhost');
        }
        return $base . ($request->path ?? '/');
    }

    private array $columnCache = [];
    private array $tableCache = [];

    protected function columnExists(string $col): bool
    {
        $key = $this->table() . '.' . $col;
        if (!isset($this->columnCache[$key])) {
            $row = $this->db->fetch("SHOW COLUMNS FROM {$this->table()} LIKE ?", [$col]);
            $this->columnCache[$key] = (bool)$row;
        }
        return $this->columnCache[$key];
    }

    protected function tableExists(string $t): bool
    {
        if (!isset($this->tableCache[$t])) {
            $row = $this->db->fetch("SHOW TABLES LIKE ?", [$t]);
            $this->tableCache[$t] = (bool)$row;
        }
        return $this->tableCache[$t];
    }
}
