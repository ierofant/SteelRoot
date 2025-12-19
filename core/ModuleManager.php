<?php
namespace Core;

use App\Services\SettingsService;
use ZipArchive;

class ModuleManager
{
    private string $modulesPath;
    private Container $container;
    private Router $router;
    private Config $config;
    private ?SettingsService $settings = null;
    private array $modules = [];
    private array $enabled = [];
    private bool $enableByDefault = true;
    private array $assetsMap = [];

    public function __construct(string $modulesPath, Container $container, Router $router, Config $config)
    {
        $this->modulesPath = rtrim($modulesPath, '/');
        $this->container = $container;
        $this->router = $router;
        $this->config = $config;
        try {
            $this->settings = $this->container->get(SettingsService::class);
            $this->loadEnabledFromSettings();
        } catch (\Throwable $e) {
            // DB may be unavailable during install; keep settings null.
            Logger::log('ModuleManager settings unavailable: ' . $e->getMessage());
        }
    }

    /**
     * Scan filesystem and build module registry.
     */
    public function discover(): void
    {
        $this->modules = [];
        $dirs = glob($this->modulesPath . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $meta = $this->readModule($dir);
            if ($meta === null) {
                continue;
            }
            $this->modules[$meta['slug']] = $meta;
        }
    }

    /**
     * Register enabled modules: routes, views, lang, events, configs.
     */
    public function registerEnabled(): void
    {
        foreach ($this->modules as $slug => $module) {
            if (empty($module['enabled'])) {
                continue;
            }
            try {
                if ($module['type'] === 'legacy') {
                    $this->registerLegacy($module);
                } else {
                    $this->registerDeclarative($module);
                }
            } catch (\Throwable $e) {
                Logger::log("Module {$slug} failed to register: " . $e->getMessage());
                $this->modules[$slug]['errors'][] = $e->getMessage();
            }
        }
    }

    public function list(): array
    {
        return $this->modules;
    }

    public function get(string $slug): ?array
    {
        return $this->modules[$slug] ?? null;
    }

    public function isEnabled(string $slug): bool
    {
        return !empty($this->modules[$slug]['enabled']);
    }

    public function enable(string $slug): void
    {
        if (!isset($this->modules[$slug])) {
            return;
        }
        $this->modules[$slug]['enabled'] = true;
        if (!in_array($slug, $this->enabled, true)) {
            $this->enabled[] = $slug;
        }
        $this->saveEnabled();
    }

    public function disable(string $slug): void
    {
        if (!isset($this->modules[$slug])) {
            return;
        }
        $this->modules[$slug]['enabled'] = false;
        $this->enabled = array_values(array_filter($this->enabled, fn($s) => $s !== $slug));
        $this->saveEnabled();
    }

    public function migrate(string $slug): array
    {
        $module = $this->modules[$slug] ?? null;
        if (!$module) {
            return ["Module {$slug} not found"];
        }
        try {
            $db = $this->container->get(Database::class);
            $runner = new ModuleMigrationRunner($module['migrations_path'], $db, $slug);
            return $runner->up();
        } catch (\Throwable $e) {
            Logger::log("Module {$slug} migration error: " . $e->getMessage());
            return ['Error: ' . $e->getMessage()];
        }
    }

    public function rollback(string $slug, int $steps = 1): array
    {
        $module = $this->modules[$slug] ?? null;
        if (!$module) {
            return ["Module {$slug} not found"];
        }
        try {
            $db = $this->container->get(Database::class);
            $runner = new ModuleMigrationRunner($module['migrations_path'], $db, $slug);
            return $runner->down($steps);
        } catch (\Throwable $e) {
            Logger::log("Module {$slug} rollback error: " . $e->getMessage());
            return ['Error: ' . $e->getMessage()];
        }
    }

    /**
     * Fully remove module: rollback migrations, delete files, drop from settings.
     */
    public function remove(string $slug): bool
    {
        $module = $this->modules[$slug] ?? null;
        if (!$module) {
            return false;
        }
        try {
            $this->rollback($slug, PHP_INT_MAX);
        } catch (\Throwable $e) {
            Logger::log("Module {$slug} rollback before delete failed: " . $e->getMessage());
        }
        $this->disable($slug);
        $this->saveEnabled();
        $this->dropFromSettings($slug);
        $this->clearCache();
        $path = $module['path'];
        if (str_starts_with($path, $this->modulesPath)) {
            $this->deleteDir($path);
        }
        unset($this->modules[$slug]);
        return true;
    }

    /**
     * Install module from zip archive and enable it.
     */
    public function installFromZip(string $zipFile): array
    {
        $result = ['ok' => false, 'message' => ''];
        if (!file_exists($zipFile)) {
            return ['ok' => false, 'message' => 'Archive not found'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            return ['ok' => false, 'message' => 'Cannot open archive'];
        }
        $tmpDir = sys_get_temp_dir() . '/module_' . uniqid();
        @mkdir($tmpDir, 0775, true);
        $zip->extractTo($tmpDir);
        $zip->close();
        $moduleDir = $this->detectModuleDir($tmpDir);
        if (!$moduleDir || !file_exists($moduleDir . '/module.php')) {
            $this->deleteDir($tmpDir);
            return ['ok' => false, 'message' => 'Invalid module structure'];
        }
        $definition = include $moduleDir . '/module.php';
        $slug = is_array($definition) && !empty($definition['slug']) ? $definition['slug'] : strtolower(basename($moduleDir));
        $target = $this->modulesPath . '/' . basename($moduleDir);
        if (is_dir($target)) {
            $this->deleteDir($tmpDir);
            return ['ok' => false, 'message' => 'Module already exists'];
        }
        @rename($moduleDir, $target);
        $this->deleteDir($tmpDir);
        $this->discover();
        $this->enable($slug);
        $this->registerEnabled();
        $this->migrate($slug);
        return ['ok' => true, 'message' => "Module {$slug} installed"];
    }

    public function assets(): array
    {
        return $this->assetsMap;
    }

    public function dispatch(string $event, array $payload = []): void
    {
        try {
            /** @var Events|null $events */
            $events = null;
            try {
                $events = $this->container->get('events');
            } catch (\Throwable $e) {
                $events = null;
            }
            if ($events instanceof Events) {
                $events->dispatch($event, $payload);
            }
        } catch (\Throwable $e) {
            Logger::log("Dispatch error {$event}: " . $e->getMessage());
        }
    }

    private function registerLegacy(array $module): void
    {
        $class = 'Modules\\' . ucfirst($module['folder']) . '\\Module';
        if (!class_exists($class)) {
            require_once $module['path'] . '/Module.php';
        }
        if (class_exists($class)) {
            $instance = new $class($module['path']);
            if (method_exists($instance, 'register')) {
                $instance->register($this->container, $this->router);
            }
        }
    }

    private function registerDeclarative(array $module): void
    {
        $definition = $module['definition'];
        $slug = $module['slug'];
        $path = $module['path'];
        $providers = $definition['providers'] ?? [];

        // Auto scaffold from schema if present.
        $schemaFile = $path . '/schema.json';
        if (file_exists($schemaFile)) {
            $json = json_decode((string)file_get_contents($schemaFile), true);
            if (is_array($json)) {
                try {
                    $scaffolder = new ModuleSchemaScaffolder($path, $definition, $json);
                    $scaffolder->generate();
                } catch (\Throwable $e) {
                    Logger::log("Schema scaffold failed for {$slug}: " . $e->getMessage());
                }
            }
        }

        // Config
        $cfgFile = $providers['config'] ?? null;
        if ($cfgFile && file_exists($path . '/' . $cfgFile)) {
            $configData = include $path . '/' . $cfgFile;
            if (is_array($configData)) {
                $this->config->merge(['modules' => [$slug => $configData]]);
            }
        }

        // Views
        if (!empty($providers['views'])) {
            $viewPath = $path . '/' . trim($providers['views'], '/');
            if (is_dir($viewPath)) {
                $renderer = $this->container->get('renderer');
                if (method_exists($renderer, 'addPath')) {
                    $renderer->addPath('@' . strtoupper($slug), $viewPath);
                    $renderer->addPath('@' . ucfirst($slug), $viewPath);
                }
            }
        }

        // Lang
        if (!empty($providers['lang'])) {
            $langPath = $path . '/' . trim($providers['lang'], '/');
            if (is_dir($langPath)) {
                $lang = $this->container->get('lang');
                if (method_exists($lang, 'addNamespace')) {
                    $lang->addNamespace($slug, $langPath);
                }
            }
        }

        // Routes
        if (!empty($providers['routes'])) {
            $routesFile = $path . '/' . $providers['routes'];
            if (file_exists($routesFile)) {
                $routes = include $routesFile;
                if (is_callable($routes)) {
                    $routes($this->router, $this->container);
                }
            }
        }

        // Events
        if (!empty($definition['events']) && is_array($definition['events'])) {
            try {
                $events = $this->container->get('events');
                if ($events instanceof Events) {
                    foreach ($definition['events'] as $event => $handler) {
                        $events->listen($event, $this->buildListener($handler));
                    }
                }
            } catch (\Throwable $e) {
                Logger::log('Events wiring failed for ' . $slug . ': ' . $e->getMessage());
            }
        }

        // Assets
        $assetsPath = $path . '/assets';
        if (is_dir($assetsPath)) {
            $this->assetsMap[$slug] = $assetsPath;
        }

        // Migrations path recorded for CLI/admin usage.
        if (!empty($providers['migrations'])) {
            $migrationPath = $path . '/' . trim($providers['migrations'], '/');
            $this->modules[$slug]['migrations_path'] = $migrationPath;
        }
    }

    private function buildListener(string $handler): callable
    {
        return function (array $payload) use ($handler) {
            [$class, $method] = explode('@', $handler) + [1 => 'handle'];
            if (!class_exists($class)) {
                return;
            }
            $instance = new $class($this->container);
            if (method_exists($instance, $method)) {
                $instance->{$method}($payload);
            }
        };
    }

    private function readModule(string $dir): ?array
    {
        $folder = basename($dir);
        $definitionFile = $dir . '/module.php';
        $legacyFile = $dir . '/Module.php';
        if (file_exists($definitionFile)) {
            try {
                $definition = include $definitionFile;
                if (!is_array($definition)) {
                    throw new \RuntimeException('module.php must return array');
                }
                if (empty($definition['slug']) || empty($definition['name'])) {
                    throw new \RuntimeException('Module missing slug or name');
                }
                $slug = strtolower((string)$definition['slug']);
                $enabled = $this->enableByDefault || in_array($slug, $this->enabled, true);
                $migrationsPath = !empty($definition['providers']['migrations'])
                    ? $dir . '/' . trim($definition['providers']['migrations'], '/')
                    : $dir . '/migrations';
                return [
                    'type' => 'declarative',
                    'name' => (string)$definition['name'],
                    'slug' => $slug,
                    'version' => $definition['version'] ?? '',
                    'description' => $definition['description'] ?? '',
                    'providers' => $definition['providers'] ?? [],
                    'permissions' => $definition['permissions'] ?? [],
                    'definition' => $definition,
                    'path' => $dir,
                    'folder' => $folder,
                    'enabled' => $enabled,
                    'errors' => [],
                    'migrations_path' => $migrationsPath,
                ];
            } catch (\Throwable $e) {
                Logger::log("Module {$folder} invalid: " . $e->getMessage());
                return [
                    'type' => 'declarative',
                    'name' => $folder,
                    'slug' => strtolower($folder),
                    'version' => '',
                    'description' => '',
                    'providers' => [],
                    'permissions' => [],
                    'definition' => [],
                    'path' => $dir,
                    'folder' => $folder,
                    'enabled' => false,
                    'errors' => [$e->getMessage()],
                    'migrations_path' => $dir . '/migrations',
                ];
            }
        }
        if (file_exists($legacyFile)) {
            return [
                'type' => 'legacy',
                'name' => $folder,
                'slug' => strtolower($folder),
                'version' => '',
                'description' => '',
                'providers' => [],
                'permissions' => [],
                'definition' => [],
                'path' => $dir,
                'folder' => $folder,
                'enabled' => $this->enableByDefault || in_array(strtolower($folder), $this->enabled, true),
                'errors' => [],
                'migrations_path' => $dir . '/migrations',
            ];
        }
        return null;
    }

    private function loadEnabledFromSettings(): void
    {
        if (!$this->settings) {
            return;
        }
        $raw = $this->settings->get('modules_enabled', null);
        if ($raw === null || $raw === '') {
            $this->enableByDefault = true;
            $this->enabled = [];
            return;
        }
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $this->enabled = array_values(array_unique(array_map('strtolower', $decoded)));
            if (!in_array('admin', $this->enabled, true)) {
                $this->enabled[] = 'admin';
            }
            if (!in_array('menu', $this->enabled, true)) {
                $this->enabled[] = 'menu';
            }
            $this->enableByDefault = false;
        }
    }

    private function saveEnabled(): void
    {
        if (!$this->settings) {
            return;
        }
        // Persist actual enabled modules to avoid dropping defaults on first toggle.
        $current = [];
        foreach ($this->modules as $slug => $meta) {
            if (!empty($meta['enabled'])) {
                $current[] = $slug;
            }
        }
        if (!in_array('admin', $current, true)) {
            $current[] = 'admin';
        }
        $this->enabled = $current;
        $json = json_encode($this->enabled, JSON_UNESCAPED_SLASHES);
        try {
            $this->settings->set('modules_enabled', $json);
        } catch (\Throwable $e) {
            Logger::log('Cannot persist modules_enabled: ' . $e->getMessage());
        }
    }

    private function dropFromSettings(string $slug): void
    {
        if (!$this->settings) {
            return;
        }
        try {
            $this->settings->set('modules_enabled', json_encode($this->enabled, JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Logger::log('Settings cleanup failed: ' . $e->getMessage());
        }
    }

    private function clearCache(): void
    {
        try {
            $cache = $this->container->get('cache');
            if ($cache instanceof Cache) {
                $cache->clear();
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function detectModuleDir(string $tmpDir): ?string
    {
        $candidates = glob($tmpDir . '/*', GLOB_ONLYDIR) ?: [];
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        foreach ($candidates as $c) {
            if (file_exists($c . '/module.php')) {
                return $c;
            }
        }
        return null;
    }
}
