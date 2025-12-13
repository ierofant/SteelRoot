<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;

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
        $msg = $request->query['msg'] ?? null;
        if ($msg === 'cleared') {
            $msg = 'Кэш очищен';
        } elseif ($msg === 'deleted') {
            $msg = 'Ключ удалён';
        }
        $html = $this->container->get('renderer')->render('admin/cache', [
            'title' => 'Cache',
            'csrf' => Csrf::token('admin_cache'),
            'stats' => $stats,
            'message' => $msg,
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
            'path' => $path,
            'files' => count($files),
            'size' => $size,
        ];
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }
}
