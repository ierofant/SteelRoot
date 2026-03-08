<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class CacheController
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request): Response
    {
        $stats = $this->stats();
        $msg = match($request->query['msg'] ?? null) {
            'cleared' => __('cache.msg.cleared'),
            'deleted' => __('cache.msg.deleted'),
            'saved'   => __('cache.msg.saved'),
            default   => null,
        };
        /** @var \Core\Cache $cache */
        $cache   = $this->container->get('cache');
        $entries = $cache->entries();
        $settings = $this->container->get(SettingsService::class);
        $html = $this->container->get('renderer')->render('admin/cache', [
            'title'    => 'Cache',
            'csrf'     => Csrf::token('admin_cache'),
            'stats'    => $stats,
            'entries'  => $entries,
            'message'  => $msg,
            'settings' => $settings,
        ]);
        return new Response($html);
    }

    public function clear(Request $request): Response
    {
        if (!Csrf::check('admin_cache', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        /** @var \Core\Cache $cache */
        $cache = $this->container->get('cache');
        $cache->clear();
        return new Response('', 302, ['Location' => $this->prefix() . '/cache?msg=cleared']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('admin_cache', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $key = trim($request->body['key'] ?? '');
        if ($key !== '') {
            /** @var \Core\Cache $cache */
            $cache = $this->container->get('cache');
            $cache->delete($key);
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/cache?msg=deleted']);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('admin_cache', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $s = $this->container->get(SettingsService::class);
        $fields = [
            'cache_articles', 'cache_articles_ttl',
            'cache_news',     'cache_news_ttl',
            'cache_gallery',  'cache_gallery_ttl',
            'cache_home',     'cache_home_ttl',
            'search_cache_ttl', 'sitemap_cache_ttl',
            'minify_html',
        ];
        foreach ($fields as $field) {
            $val = $request->body[$field] ?? null;
            if ($val === null) continue;
            $s->set($field, (string)$val);
        }
        // checkboxes которые не приходят при unchecked
        foreach (['cache_articles', 'cache_news', 'cache_gallery', 'cache_home', 'minify_html'] as $toggle) {
            if (!isset($request->body[$toggle])) {
                $s->set($toggle, '0');
            }
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/cache?msg=saved']);
    }

    private function stats(): array
    {
        $cfg = $this->container->get('config')['cache'] ?? [];
        $path = $cfg['path'] ?? APP_ROOT . '/storage/cache';
        $files = glob(rtrim($path, '/') . '/*.cache') ?: [];
        $size = 0;
        foreach ($files as $file) {
            $size += filesize($file) ?: 0;
        }
        return [
            'path'  => $path,
            'files' => count($files),
            'size'  => $size,
        ];
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }
}
