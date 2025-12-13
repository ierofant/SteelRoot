<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class SitemapController
{
    private Container $container;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $cfg = $this->loadConfig();
        $html = $this->container->get('renderer')->render('admin/sitemap', [
            'title' => 'Sitemap',
            'csrf' => Csrf::token('sitemap_cfg'),
            'config' => $cfg,
            'message' => !empty($request->query['saved']) ? 'Сохранено' : null,
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('sitemap_cfg', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $cfg = [
            'include_home' => isset($request->body['include_home']) ? '1' : '0',
            'include_contact' => isset($request->body['include_contact']) ? '1' : '0',
            'include_articles' => isset($request->body['include_articles']) ? '1' : '0',
            'include_gallery' => isset($request->body['include_gallery']) ? '1' : '0',
            'include_tags' => isset($request->body['include_tags']) ? '1' : '0',
        ];
        $this->settings->bulkSet(array_map(fn($v) => (string)$v, $cfg));
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/sitemap?saved=1']);
    }

    public function clearCache(Request $request): Response
    {
        if (!Csrf::check('sitemap_cfg', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        try {
            $cache = $this->container->get('cache');
            $cache->delete('sitemap');
            $msg = 'Sitemap cache cleared';
        } catch (\Throwable $e) {
            $msg = 'Failed to clear cache: ' . $e->getMessage();
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/sitemap?saved=1&msg=' . rawurlencode($msg)]);
    }

    private function loadConfig(): array
    {
        $defaults = [
            'include_home' => '1',
            'include_contact' => '1',
            'include_articles' => '1',
            'include_gallery' => '1',
            'include_tags' => '0',
        ];
        $all = $this->settings->all();
        foreach ($defaults as $k => $v) {
            $defaults[$k] = $all[$k] ?? $v;
        }
        return $defaults;
    }
}
