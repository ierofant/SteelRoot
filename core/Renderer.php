<?php
namespace Core;

use RuntimeException;
use Core\Meta\MetaResolver;

// Simple explicit renderer: resolves PHP templates, optional layout/sections, no magic.
class Renderer
{
    private string $appViews;
    private string $modulesPath;
    private array $namespaces = [];
    private array $sections = [];
    private array $metaDefaults = [];
    private ?Database $db;
    private ?string $currentTemplate = null;
    private array $currentData = [];
    private array $currentMeta = [];

    public function __construct(string $appViews, string $modulesPath, ?array $metaDefaults = null, ?Database $db = null)
    {
        $this->appViews = rtrim($appViews, '/');
        $this->modulesPath = rtrim($modulesPath, '/');
        $this->metaDefaults = $metaDefaults ?? [];
        $this->db = $db;
    }

    public function addPath(string $namespace, string $path): void
    {
        $key = ltrim($namespace, '@');
        $this->namespaces[$key] = rtrim($path, '/');
    }

    public function render(string $view, array $data = [], array $meta = []): string
    {
        if (!array_key_exists('cspNonce', $data)) {
            $data['cspNonce'] = null;
        }
        $useLayout = array_key_exists('_layout', $data) ? (bool)$data['_layout'] : false;
        $layoutName = $data['_layout_file'] ?? 'layout';
        unset($data['_layout'], $data['_layout_file']);

        if (!$useLayout) {
            $file = $this->resolve($view);
            extract($data, EXTR_SKIP);
            ob_start();
            include $file;
            return ob_get_clean();
        }

        $this->sections = [];
        $this->currentTemplate = $this->resolve($view);
        $this->currentData = $data;
        $this->currentMeta = $this->resolveMeta($meta, $data['meta'] ?? []);
        $layout = $this->resolve($layoutName);

        extract($data, EXTR_SKIP);
        $meta = $this->currentMeta;

        ob_start();
        include $layout;
        return ob_get_clean();
    }

    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    public function getSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function renderContent(): void
    {
        if (!$this->currentTemplate) {
            return;
        }
        extract($this->currentData, EXTR_SKIP);
        include $this->currentTemplate;
    }

    public function hasContentTemplate(): bool
    {
        return (bool)$this->currentTemplate;
    }

    public function menuItems(string $locale = 'en'): array
    {
        $items = $GLOBALS['menuItemsPublic'] ?? [];
        if (!empty($items)) {
            return $items;
        }
        // Fallback to legacy settings-based schema
        $settingsAll = $GLOBALS['settingsAll'] ?? [];
        $raw = $settingsAll['menu_schema'] ?? '';
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && !empty($decoded)) {
            $mapped = [];
            foreach ($decoded as $row) {
                $url = $row['url'] ?? '';
                if ($url === '') {
                    continue;
                }
                $mapped[] = [
                    'url' => $url,
                    'enabled' => !empty($row['enabled']),
                    'admin_only' => !empty($row['requires_admin'] ?? $row['admin_only'] ?? false),
                    'label' => $locale === 'ru' ? ($row['label_ru'] ?? '') : ($row['label_en'] ?? ''),
                    'label_ru' => $row['label_ru'] ?? '',
                    'label_en' => $row['label_en'] ?? '',
                    'title' => $locale === 'ru' ? ($row['title_ru'] ?? '') : ($row['title_en'] ?? ''),
                    'description' => $locale === 'ru' ? ($row['description_ru'] ?? '') : ($row['description_en'] ?? ''),
                    'canonical_url' => $row['canonical_url'] ?? '',
                    'image_url' => $row['image_url'] ?? '',
                ];
            }
            if (!empty($mapped)) {
                return $mapped;
            }
        }
        // Hard defaults
        $adminPrefix = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        return [
            ['url' => '/', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Главная' : 'Home', 'label_ru' => 'Главная', 'label_en' => 'Home', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
            ['url' => '/contact', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Контакты' : 'Contact', 'label_ru' => 'Контакты', 'label_en' => 'Contact', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
            ['url' => '/articles', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Статьи' : 'Articles', 'label_ru' => 'Статьи', 'label_en' => 'Articles', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
            ['url' => '/gallery', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Галерея' : 'Gallery', 'label_ru' => 'Галерея', 'label_en' => 'Gallery', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
            ['url' => '/search', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Поиск' : 'Search', 'label_ru' => 'Поиск', 'label_en' => 'Search', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
            ['url' => $adminPrefix, 'enabled' => true, 'admin_only' => true, 'label' => $locale === 'ru' ? 'Админ' : 'Admin', 'label_ru' => 'Админ', 'label_en' => 'Admin', 'title' => '', 'description' => '', 'canonical_url' => null, 'image_url' => null],
        ];
    }

    private function resolveMeta(array $contentMeta, array $dataMeta): array
    {
        $base = [
            'title' => $this->metaDefaults['title'] ?? 'SteelRoot',
            'description' => $this->metaDefaults['description'] ?? '',
            'canonical' => $this->metaDefaults['canonical'] ?? null,
            'image' => $this->metaDefaults['image'] ?? null,
        ];
        $content = array_merge($dataMeta, $contentMeta);
        $menu = $this->menuMetaCandidate();
        return MetaResolver::resolve($base, $content, $menu);
    }

    private function menuMetaCandidate(): array
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $locale = $this->currentData['locale'] ?? ($this->currentData['currentLocale'] ?? 'en');
        foreach ($this->menuItems($locale) as $item) {
            if (empty($item['enabled'])) {
                continue;
            }
            if (!empty($item['admin_only']) && empty($_SESSION['admin_auth'])) {
                continue;
            }
            if (($item['url'] ?? '') === $path) {
                return [
                    'title' => $item['title'] ?? null,
                    'description' => $item['description'] ?? null,
                    'canonical' => $item['canonical_url'] ?? null,
                    'image' => $item['image_url'] ?? null,
                ];
            }
        }
        return [];
    }

    private function resolve(string $view): string
    {
        $view = trim($view, '/');
        $paths = [$this->appViews . '/' . $view . '.php'];

        if (str_starts_with($view, '@')) {
            [$ns, $rest] = explode('/', substr($view, 1), 2) + [1 => ''];
            if (isset($this->namespaces[$ns])) {
                $paths[] = $this->namespaces[$ns] . '/' . $rest . '.php';
            }
        }

        if (str_contains($view, '/')) {
            [$prefix, $rest] = explode('/', $view, 2);
            $modulePath = $this->modulesPath . '/' . ucfirst($prefix) . '/views/' . $rest . '.php';
            $paths[] = $modulePath;
        }

        foreach (glob($this->modulesPath . '/*/views/' . $view . '.php') as $moduleView) {
            $paths[] = $moduleView;
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        throw new RuntimeException("View {$view} not found");
    }
}
