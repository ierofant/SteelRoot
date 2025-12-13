<?php
namespace Core;

use RuntimeException;

class Renderer
{
    private string $appViews;
    private string $modulesPath;
    private array $namespaces = [];

    public function __construct(string $appViews, string $modulesPath)
    {
        $this->appViews = rtrim($appViews, '/');
        $this->modulesPath = rtrim($modulesPath, '/');
    }

    public function addPath(string $namespace, string $path): void
    {
        $key = ltrim($namespace, '@');
        $this->namespaces[$key] = rtrim($path, '/');
    }

    public function render(string $view, array $data = []): string
    {
        if (!array_key_exists('cspNonce', $data)) {
            $data['cspNonce'] = null;
        }
        $file = $this->resolve($view);
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
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

        // Если имя вида содержит префикс модуля (admin/login, gallery/list и т.п.),
        // пробуем искать внутри конкретного модуля с таким префиксом.
        if (str_contains($view, '/')) {
            [$prefix, $rest] = explode('/', $view, 2);
            $modulePath = $this->modulesPath . '/' . ucfirst($prefix) . '/views/' . $rest . '.php';
            $paths[] = $modulePath;
        }

        // Общее совпадение: modules/*/views/<view>.php
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
