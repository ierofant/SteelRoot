<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Database;
use Core\ModuleManager;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class SitemapController
{
    private Container      $container;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings  = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $flash = $_SESSION['sitemap_flash'] ?? null;
        unset($_SESSION['sitemap_flash']);

        $providers = $this->discoverProviders();
        $all       = $this->settings->all();
        $config    = $this->buildConfig($all, $providers);

        $html = $this->container->get('renderer')->render('admin/sitemap', [
            'title'     => 'Sitemap',
            'csrf'      => Csrf::token('sitemap_cfg'),
            'config'    => $config,
            'providers' => $providers,
            'flash'     => $flash,
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('sitemap_cfg', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $b        = $request->body;
        $providers = $this->discoverProviders();
        $toSave   = [];

        // Global
        $ttl = max(60, (int)($b['sitemap_cache_ttl'] ?? 600));
        $toSave['sitemap_cache_ttl'] = (string)$ttl;

        // Core sections
        $coreSections = ['home', 'contact', 'tags'];
        foreach ($coreSections as $key) {
            $toSave["sitemap_include_{$key}"]   = isset($b["sitemap_include_{$key}"]) ? '1' : '0';
            $toSave["sitemap_priority_{$key}"]  = $this->sanitizePriority($b["sitemap_priority_{$key}"] ?? '0.5');
            $toSave["sitemap_changefreq_{$key}"] = $this->sanitizeFreq($b["sitemap_changefreq_{$key}"] ?? 'monthly');
        }

        // Module sections
        foreach ($providers as $key => $prov) {
            $toSave["sitemap_include_{$key}"]    = isset($b["sitemap_include_{$key}"]) ? '1' : '0';
            $toSave["sitemap_priority_{$key}"]   = $this->sanitizePriority($b["sitemap_priority_{$key}"] ?? $prov['priority']);
            $toSave["sitemap_changefreq_{$key}"] = $this->sanitizeFreq($b["sitemap_changefreq_{$key}"] ?? $prov['changefreq']);
        }

        $this->settings->bulkSet($toSave);

        // Clear cached sitemap so changes take effect immediately
        try {
            $this->container->get('cache')->delete('sitemap');
        } catch (\Throwable $e) {}

        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        $_SESSION['sitemap_flash'] = 'saved';
        return new Response('', 302, ['Location' => $prefix . '/sitemap']);
    }

    public function clearCache(Request $request): Response
    {
        if (!Csrf::check('sitemap_cfg', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        try {
            $this->container->get('cache')->delete('sitemap');
            $_SESSION['sitemap_flash'] = 'Cache cleared.';
        } catch (\Throwable $e) {
            $_SESSION['sitemap_flash'] = 'Failed: ' . $e->getMessage();
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/sitemap']);
    }

    /**
     * JSON: count of URLs per section that will appear in the sitemap.
     */
    public function previewCount(Request $request): Response
    {
        $appConfig = $this->container->get('config');
        $base      = rtrim($appConfig['app']['url'] ?? '', '/');
        $all       = $this->settings->all();
        $providers = $this->discoverProviders();

        $db = null;
        try {
            $db = $this->container->get(Database::class);
        } catch (\Throwable $e) {}

        $counts = ['_total' => 0];

        // Core: home, contact (1 URL each if enabled)
        foreach (['home' => '/', 'contact' => '/contact'] as $key => $path) {
            $inc = $all["sitemap_include_{$key}"] ?? $all["include_{$key}"] ?? '1';
            $counts[$key] = ($inc === '1') ? 1 : 0;
            $counts['_total'] += $counts[$key];
        }

        // Tags
        $incTags = $all['sitemap_include_tags'] ?? $all['include_tags'] ?? '0';
        if ($incTags === '1' && $db) {
            try {
                $n = (int)($db->fetch("SELECT COUNT(*) AS c FROM tags")['c'] ?? 0);
                $counts['tags'] = $n;
                $counts['_total'] += $n;
            } catch (\Throwable $e) {
                $counts['tags'] = 0;
            }
        } else {
            $counts['tags'] = 0;
        }

        // Module providers
        foreach ($providers as $key => $prov) {
            $default = $prov['default'] ? '1' : '0';
            $inc     = $all["sitemap_include_{$key}"] ?? $default;
            if ($inc !== '1') {
                $counts[$key] = 0;
                continue;
            }
            try {
                $data = include $prov['file'];
                if (isset($data['provider']) && is_callable($data['provider'])) {
                    $entries = ($data['provider'])($base, $db);
                    $n = count(is_array($entries) ? $entries : []);
                } else {
                    $n = 0;
                }
                $counts[$key] = $n;
                $counts['_total'] += $n;
            } catch (\Throwable $e) {
                $counts[$key] = -1;
            }
        }

        return new Response(
            json_encode($counts, JSON_UNESCAPED_SLASHES),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    // ── Internals ────────────────────────────────────────────────

    /**
     * Discover module sitemap providers (new declarative format only).
     * Returns keyed by provider 'key'.
     */
    private function discoverProviders(): array
    {
        $modulesPath = APP_ROOT . '/modules';
        $enabled     = [];
        try {
            $mgr = $this->container->get(ModuleManager::class);
            foreach ($mgr->list() as $slug => $meta) {
                if (!empty($meta['enabled'])) {
                    $enabled[$slug] = true;
                }
            }
        } catch (\Throwable $e) {}

        $providers = [];
        foreach (glob($modulesPath . '/*/sitemap.php') ?: [] as $file) {
            try {
                $data = include $file;
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data) || !isset($data['key'])) {
                continue; // legacy format — skip for discovery
            }
            $key        = (string)$data['key'];
            $folderSlug = strtolower(basename(dirname($file)));
            $isEnabled  = $enabled[$folderSlug] ?? false;

            $providers[$key] = [
                'key'         => $key,
                'label'       => $data['label']       ?? ucfirst($key),
                'description' => $data['description'] ?? '',
                'default'     => $data['default']     ?? true,
                'priority'    => $data['priority']    ?? '0.6',
                'changefreq'  => $data['changefreq']  ?? 'weekly',
                'module_slug' => $folderSlug,
                'module_on'   => $isEnabled,
                'file'        => $file,
            ];
        }

        // Sort by label
        uasort($providers, fn($a, $b) => strcmp($a['label'], $b['label']));
        return $providers;
    }

    private function buildConfig(array $all, array $providers): array
    {
        $cfg = [
            'sitemap_cache_ttl'          => (int)($all['sitemap_cache_ttl'] ?? 600),
            'sitemap_include_home'       => $all['sitemap_include_home']       ?? $all['include_home']    ?? '1',
            'sitemap_priority_home'      => $all['sitemap_priority_home']      ?? '1.0',
            'sitemap_changefreq_home'    => $all['sitemap_changefreq_home']    ?? 'daily',
            'sitemap_include_contact'    => $all['sitemap_include_contact']    ?? $all['include_contact'] ?? '1',
            'sitemap_priority_contact'   => $all['sitemap_priority_contact']   ?? '0.5',
            'sitemap_changefreq_contact' => $all['sitemap_changefreq_contact'] ?? 'monthly',
            'sitemap_include_tags'       => $all['sitemap_include_tags']       ?? $all['include_tags']    ?? '0',
            'sitemap_priority_tags'      => $all['sitemap_priority_tags']      ?? '0.4',
            'sitemap_changefreq_tags'    => $all['sitemap_changefreq_tags']    ?? 'weekly',
        ];

        foreach ($providers as $key => $prov) {
            $def = $prov['default'] ? '1' : '0';
            $cfg["sitemap_include_{$key}"]    = $all["sitemap_include_{$key}"]    ?? ($prov['module_on'] ? $def : '0');
            $cfg["sitemap_priority_{$key}"]   = $all["sitemap_priority_{$key}"]   ?? $prov['priority'];
            $cfg["sitemap_changefreq_{$key}"] = $all["sitemap_changefreq_{$key}"] ?? $prov['changefreq'];
        }

        return $cfg;
    }

    private function sanitizePriority(string $v): string
    {
        $f = round((float)$v, 1);
        return (string)max(0.0, min(1.0, $f));
    }

    private function sanitizeFreq(string $v): string
    {
        $allowed = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        return in_array($v, $allowed, true) ? $v : 'weekly';
    }
}
