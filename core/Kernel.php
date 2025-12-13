<?php
namespace Core;

use Throwable;
use App\Services\SettingsService;
use App\Services\RedirectService;
use App\Services\SecurityLog;
use Core\Search\SearchRegistry;
use Core\Search\SearchManager;

class Kernel
{
    private string $root;
    private Container $container;
    private Router $router;
    private Config $config;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
        $this->container = new Container();
        $this->router = new Router();
        $this->boot();
    }

    private function boot(): void
    {
        $this->loadConfig();
        $this->registerServices();
        $this->registerCoreRoutes();
        $this->loadModules();
        $this->loadSearchProviders();
        $this->router->setNotFound(function (Request $request) {
            try {
                $redirects = $this->container->get(RedirectService::class);
                $match = $redirects->resolve($request->path);
                if ($match) {
                    $redirects->touch((int)$match['id']);
                    return new Response('', (int)($match['status_code'] ?? 301), ['Location' => $match['to_url']]);
                }
            } catch (Throwable $e) {
                Logger::log('Redirect resolve failed: ' . $e->getMessage());
            }
            try {
                \App\Services\SecurityLog::log('404', ['path' => $request->path]);
            } catch (Throwable $e) {
                Logger::log('Security log 404 failed: ' . $e->getMessage());
            }
            return $this->renderError(404, $request);
        });
    }

    private function loadConfig(): void
    {
        $items = [];
        $items['app'] = include $this->root . '/app/config/app.php';
        $items['cache'] = include $this->root . '/app/config/cache.php';
        $items['lang'] = include $this->root . '/app/config/lang.php';
        $items['database'] = @include $this->root . '/app/config/database.php';
        $items['limits'] = include $this->root . '/app/config/limits.php';
        $items['captcha'] = include $this->root . '/app/config/captcha.php';
        $secret = $items['app']['admin_secret'] ?? '';
        $prefix = '/admin';
        if ($secret !== '') {
            $prefix = '/admin-' . trim($secret);
        }
        $items['admin_prefix'] = $prefix;
        $this->config = new Config($items);
    }

    private function registerServices(): void
    {
        $this->container->singleton('renderer', function () {
            return new Renderer($this->root . '/app/views', $this->root . '/modules');
        });

        $this->container->singleton('cache', function () {
            $cfg = $this->config['cache'];
            return new Cache($cfg['path'], (int)$cfg['default_ttl']);
        });

        $this->container->singleton(Database::class, function () {
            if (empty($this->config['database'])) {
                throw new \RuntimeException('Database config missing');
            }
            return new Database($this->config['database']);
        });

        $this->container->singleton('lang', function () {
            $cfg = [
                'default' => $this->config['app']['locale'],
                'fallback' => $this->config['app']['fallback_locale'],
                'available' => $this->config['lang']['available'],
            ];
            return new Lang($cfg, $this->root . '/app/lang', $this->root . '/modules');
        });

        $this->container->singleton('router', function () {
            return $this->router;
        });

        $this->container->singleton('config', function () {
            return $this->config;
        });

        $this->container->singleton('events', function () {
            return new Events();
        });

        $this->container->singleton(ModuleManager::class, function () {
            return new ModuleManager($this->root . '/modules', $this->container, $this->router, $this->config);
        });

        $this->container->singleton(SettingsService::class, function () {
            return new SettingsService($this->container->get(Database::class));
        });
        $this->container->singleton(\App\Services\CaptchaService::class, function () {
            return new \App\Services\CaptchaService($this->container->get(SettingsService::class));
        });
        $this->container->singleton(\App\Services\SearchIndexService::class, function () {
            return new \App\Services\SearchIndexService($this->container->get(Database::class));
        });
        $this->container->singleton(\App\Services\RedirectService::class, function () {
            return new \App\Services\RedirectService(
                $this->container->get(Database::class),
                $this->container->get('cache')
            );
        });
        $this->container->singleton(\Core\ModuleSettings::class, function () {
            return new \Core\ModuleSettings($this->container->get(Database::class));
        });

        $this->container->singleton(SearchRegistry::class, function () {
            return new SearchRegistry();
        });
        $this->container->singleton(SearchManager::class, function () {
            return new SearchManager($this->container->get(SearchRegistry::class));
        });

        $this->container->singleton('migrationRunner', function () {
            require_once $this->root . '/database/MigrationRunner.php';
            $db = $this->container->get(Database::class);
            return new \Database\MigrationRunner($this->root . '/database/migrations', $db);
        });

        $renderer = $this->container->get('renderer');
        $errorHandler = new ErrorHandler($renderer);
        $errorHandler->register();
    }

    private function registerCoreRoutes(): void
    {
        $this->router->get('/', [\App\Controllers\HomeController::class, 'index']);
        $this->router->get('/contact', [\App\Controllers\HomeController::class, 'contact']);
        $this->router->get('/sitemap.xml', function (Request $request) {
            return $this->sitemap($request);
        });
        $this->router->get('/sitemap', function (Request $request) {
            return $this->sitemap($request);
        });
        $this->router->get('/migrate', function (Request $request) {
            return $this->handleMigrations($request);
        });
        $this->router->get('/search', [\App\Controllers\SearchController::class, 'index']);
        $this->router->get('/tags/autocomplete', [\App\Controllers\SearchController::class, 'autocomplete']);
        $this->router->get('/tags', [\App\Controllers\TagsController::class, 'index']);
        $this->router->post('/api/v1/view', [\App\Controllers\Api\InteractionController::class, 'view']);
        $this->router->post('/api/v1/like', [\App\Controllers\Api\InteractionController::class, 'like']);
        $this->router->get('/api/v1/search', [\App\Controllers\Api\V1\SearchApiController::class, 'search']);
        $this->router->get('/api/v1/autocomplete', [\App\Controllers\Api\V1\SearchApiController::class, 'autocomplete']);
        $this->router->post('/api/v1/attachments', [\App\Controllers\AttachmentController::class, 'upload']);
        $this->router->get('/manifest.json', [\App\Controllers\PwaController::class, 'manifest']);
        $this->router->get('/sw.js', [\App\Controllers\PwaController::class, 'serviceWorker']);
    }

    private function loadModules(): void
    {
        try {
            $manager = $this->container->get(ModuleManager::class);
            $manager->discover();
            $manager->registerEnabled();
            // Load search providers after modules are registered
            $this->loadSearchProviders();
        } catch (\Throwable $e) {
            Logger::log('Modules bootstrap failed: ' . $e->getMessage());
        }
    }

    private function loadSearchProviders(): void
    {
        try {
            $registry = $this->container->get(SearchRegistry::class);
            $container = $this->container;
            $path = APP_ROOT . '/bootstrap/search_providers.php';
            if (file_exists($path)) {
                $registry = $registry; // for include scope
                include $path;
            }
        } catch (\Throwable $e) {
            Logger::log('Search providers load failed: ' . $e->getMessage());
        }
    }

    private function localesByMode(string $mode): array
    {
        switch ($mode) {
            case 'ru':
                return ['ru'];
            case 'en':
                return ['en'];
            default:
                return ['ru', 'en'];
        }
    }

    public function handle(Request $request): Response
    {
        global $langInstance;
        /** @var Lang $lang */
        $lang = $this->container->get('lang');
        /** @var SettingsService $settingsSvc */
        $settingsSvc = $this->container->get(SettingsService::class);
        $localeMode = $settingsSvc->get('locale_mode', 'multi');
        $availableLocales = $this->localesByMode($localeMode);
        $lang->setAvailable($availableLocales);
        $lang->setDefault($availableLocales[0] ?? $this->config['app']['locale']);
        $lang->setFallback($availableLocales[0] ?? $this->config['app']['fallback_locale']);
        $locale = $lang->detect($request);
        if (!empty($request->query['lang']) && in_array($locale, $availableLocales, true)) {
            @setcookie('locale', $locale, time() + 3600 * 24 * 30, '/');
        }
        $lang->load($locale);
        $langInstance = $lang;
        $GLOBALS['currentLocale'] = $locale;
        $GLOBALS['availableLocales'] = $availableLocales;
        $GLOBALS['localeMode'] = $localeMode;
        if (!defined('ADMIN_PREFIX')) {
            define('ADMIN_PREFIX', $this->config['admin_prefix']);
        }

        try {
            $settings = $this->container->get(SettingsService::class);
            $GLOBALS['viewTheme'] = $settings->get('theme', 'light');
            $GLOBALS['customThemeUrl'] = $settings->get('theme_custom_url', null);
            $GLOBALS['settingsAll'] = $settings->all();
            // Глобальная блокировка IP для всего сайта
            $blockedSite = array_filter(array_map('trim', explode(',', (string)$settings->get('site_blocked_ips', ''))));
            $clientIp = $request->server['REMOTE_ADDR'] ?? '';
            if ($clientIp && in_array($clientIp, $blockedSite, true)) {
                return new Response('Forbidden', 403);
            }
            $sessionPath = $settings->get('session_path', APP_ROOT . '/storage/tmp/sessions');
            if (!is_dir($sessionPath)) {
                @mkdir($sessionPath, 0775, true);
            }
            @session_write_close();
            @session_save_path($sessionPath);
            @session_start();
            $result = $this->router->dispatch($request, $this->container);
            if ($result instanceof Response) {
                return $result;
            }
            if (is_string($result)) {
                return new Response($result);
            }
            return $this->renderError(404, $request);
        } catch (Throwable $e) {
            Logger::log('Error: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'Database connection failed')) {
                $msg = 'Database connection failed or missing. Run /installer.php or update app/config/database.php.';
                if ($request->isJson()) {
                    return Response::json(['error' => 'db_not_configured', 'message' => $msg], 500);
                }
                return new Response($this->container->get('renderer')->render('errors/500', ['error' => new \Exception($msg)]), 500);
            }
            if ($request->isJson()) {
                return Response::json(['error' => 'server_error'], 500);
            }
            return new Response($this->container->get('renderer')->render('errors/500', ['error' => $e]), 500);
        }
    }

    private function renderError(int $status, Request $request): Response
    {
        $view = $status === 404 ? 'errors/404' : 'errors/500';
        if ($request->isJson()) {
            return Response::json(['error' => $status === 404 ? 'not_found' : 'server_error'], $status);
        }
        return new Response($this->container->get('renderer')->render($view), $status);
    }

    private function sitemap(Request $request): Response
    {
        /** @var Cache $cache */
        $cache = $this->container->get('cache');
        $cached = $cache->get('sitemap');
        if ($cached) {
            return new Response($cached, 200, ['Content-Type' => 'application/xml']);
        }
        $base = rtrim($this->config['app']['url'] ?? 'http://localhost', '/');
        if ($base === '') {
            $base = 'http://localhost';
        }
        $settings = $this->container->get(SettingsService::class);
        $cfg = [
            'include_home' => $this->boolSetting($settings, 'include_home', true),
            'include_contact' => $this->boolSetting($settings, 'include_contact', true),
            'include_articles' => $this->boolSetting($settings, 'include_articles', true),
            'include_gallery' => $this->boolSetting($settings, 'include_gallery', true),
            'include_tags' => $this->boolSetting($settings, 'include_tags', false),
        ];
        $urls = [];
        if ($cfg['include_home']) {
            $urls[] = "{$base}/";
        }
        if ($cfg['include_contact']) {
            $urls[] = "{$base}/contact";
        }
        if ($cfg['include_articles']) {
            $urls[] = "{$base}/articles";
        }
        if ($cfg['include_gallery']) {
            $urls[] = "{$base}/gallery";
        }

        foreach (glob($this->root . '/modules/*/sitemap.php') as $provider) {
            $entries = include $provider;
            if (is_array($entries)) {
                $urls = array_merge($urls, $entries);
            }
        }

        try {
            $db = $this->container->get(Database::class);
            if ($cfg['include_articles']) {
                $articles = $db->fetchAll("SELECT slug FROM articles");
                foreach ($articles as $article) {
                    $urls[] = $base . '/articles/' . rawurlencode($article['slug']);
                }
            }
            if ($cfg['include_gallery']) {
                $gallery = $db->fetchAll("SELECT id, slug FROM gallery_items");
                foreach ($gallery as $item) {
                    $urls[] = $base . '/gallery/view?id=' . (int)$item['id'];
                }
            }
            if ($cfg['include_tags']) {
                $tags = $db->fetchAll("SELECT slug FROM tags");
                foreach ($tags as $tag) {
                    $urls[] = $base . '/tags/' . rawurlencode($tag['slug']);
                }
            }
        } catch (\Throwable $e) {
            // Ignore DB issues in sitemap generation
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach (array_unique($urls) as $url) {
            $xml .= "  <url><loc>" . htmlspecialchars($url, ENT_XML1) . "</loc></url>\n";
        }
        $xml .= "</urlset>";
        $cache->set('sitemap', $xml, 600);
        return new Response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    private function handleMigrations(Request $request): Response
    {
        $runner = $this->container->get('migrationRunner');
        $action = $request->query['up'] ?? ($request->query['down'] ?? ($request->query['status'] ?? null));
        if ($action === null) {
            return new Response('Migration actions: ?up or ?down or ?status', 400);
        }
        if (isset($request->query['up'])) {
            $log = $runner->up();
        } elseif (isset($request->query['down'])) {
            $log = $runner->down();
        } else {
            $log = $runner->status();
        }
        return new Response(nl2br(htmlspecialchars($log)), 200);
    }

    private function boolSetting(SettingsService $settings, string $key, bool $default): bool
    {
        $val = $settings->get($key, $default ? '1' : '0');
        if ($val === '' || $val === null) {
            return $default;
        }
        return $val === '1' || strtolower((string)$val) === 'true';
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
