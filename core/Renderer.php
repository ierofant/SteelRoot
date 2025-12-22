<?php
namespace Core;

use RuntimeException;
use Core\Meta\MetaResolver;

/**
 * Explicit PHP renderer with layout, sections, and resolved meta.
 */
class Renderer
{
    private string $appViews;
    private string $modulesPath;
    private array $namespaces = [];
    private array $sections = [];
    private array $metaDefaults = [];
    private array $sharedData = [];
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

    public function share(array $data): void
    {
        $this->sharedData = $data;
    }

    public function addPath(string $namespace, string $path): void
    {
        $key = ltrim($namespace, '@');
        $this->namespaces[$key] = rtrim($path, '/');
    }

    public function render(string $view, array $data = [], array $meta = []): string
    {
        $data = array_merge($this->sharedData, $data);
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
        $this->currentMeta = $this->resolveMeta($meta);
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

    private function resolveMeta(array $contentMeta): array
    {
        $settings = $this->sharedData['settings'] ?? [];
        $baseUrl = $this->sharedData['baseUrl'] ?? null;
        $requestPath = $this->sharedData['requestPath'] ?? '/';
        $base = [
            'title' => $this->metaDefaults['title'] ?? 'SteelRoot',
            'description' => $this->metaDefaults['description'] ?? '',
            'canonical' => $this->metaDefaults['canonical'] ?? null,
            'image' => $this->metaDefaults['image'] ?? null,
        ];
        $menu = $this->menuMetaCandidate();
        $resolved = MetaResolver::resolve($base, $contentMeta, $menu);

        $meta = [
            'title' => $resolved['title'] ?? $base['title'],
            'description' => $resolved['description'] ?? $base['description'],
            'canonical' => $resolved['canonical'] ?? null,
            'image' => $resolved['image'] ?? null,
            'keywords' => $contentMeta['keywords'] ?? '',
            'robots' => $contentMeta['robots'] ?? null,
            'jsonld' => $contentMeta['jsonld'] ?? null,
            'og' => $contentMeta['og'] ?? [],
            'twitter' => $contentMeta['twitter'] ?? [],
        ];

        if (empty($meta['canonical']) && $baseUrl) {
            $meta['canonical'] = rtrim($baseUrl, '/') . $requestPath;
        }

        $defaultImage = $meta['image'];
        if (!$defaultImage) {
            if (!empty($settings['og_image'])) {
                $defaultImage = $settings['og_image'];
            } elseif (!empty($settings['theme_logo'])) {
                $defaultImage = $settings['theme_logo'];
            } else {
                $defaultImage = '/assets/theme/og-default.png';
            }
        }
        $meta['image'] = $meta['image'] ?: $defaultImage;

        $meta['og'] = array_merge([
            'title' => $meta['title'] ?? '',
            'description' => $meta['description'] ?? '',
            'image' => $meta['image'],
            'url' => $meta['canonical'] ?? null,
        ], $meta['og']);
        $meta['twitter'] = array_merge([
            'card' => 'summary_large_image',
            'title' => $meta['og']['title'] ?? ($meta['title'] ?? ''),
            'description' => $meta['og']['description'] ?? ($meta['description'] ?? ''),
            'image' => $meta['image'],
        ], $meta['twitter']);

        if ($baseUrl) {
            $baseUrl = rtrim($baseUrl, '/');
            foreach (['image', 'og', 'twitter'] as $key) {
                if ($key === 'image') {
                    if (!empty($meta['image']) && str_starts_with($meta['image'], '/')) {
                        $meta['image'] = $baseUrl . $meta['image'];
                    }
                    continue;
                }
                if (!empty($meta[$key]['image']) && str_starts_with($meta[$key]['image'], '/')) {
                    $meta[$key]['image'] = $baseUrl . $meta[$key]['image'];
                }
            }
        }

        return $meta;
    }

    private function menuMetaCandidate(): array
    {
        $path = $this->sharedData['requestPath'] ?? '/';
        $locale = $this->sharedData['currentLocale'] ?? 'en';
        $items = $this->sharedData['menuItems'] ?? [];
        $isAdmin = !empty($this->sharedData['isAdmin']);
        foreach ($items as $item) {
            if (empty($item['enabled'])) {
                continue;
            }
            if (!empty($item['admin_only']) && !$isAdmin) {
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
        $paths = [];
        $templatePath = $this->sharedData['templatePath'] ?? null;
        if ($templatePath) {
            $paths[] = rtrim($templatePath, '/') . '/' . $view . '.php';
        }
        $paths[] = $this->appViews . '/' . $view . '.php';

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
