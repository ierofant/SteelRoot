<?php
namespace App\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use App\Services\SettingsService;

class PwaController
{
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->settings = $container->get(SettingsService::class);
    }

    public function manifest(Request $request): Response
    {
        if ($this->settings->get('pwa_enabled', '0') !== '1') {
            return new Response('Not found', 404);
        }

        $app   = include APP_ROOT . '/app/config/app.php';
        $name  = $this->settings->get('pwa_name', $app['name'] ?? 'SteelRoot');
        $short = $this->settings->get('pwa_short_name', $name) ?: $name;

        $manifest = [
            'name'             => $name,
            'short_name'       => $short,
            'start_url'        => $this->settings->get('pwa_start_url', '/'),
            'scope'            => $this->settings->get('pwa_scope', '/'),
            'display'          => $this->settings->get('pwa_display', 'standalone'),
            'orientation'      => $this->settings->get('pwa_orientation', 'any'),
            'theme_color'      => $this->settings->get('pwa_theme_color', '#1f6feb'),
            'background_color' => $this->settings->get('pwa_bg_color', '#ffffff'),
            'lang'             => $this->settings->get('pwa_lang', 'en'),
        ];

        $desc = $this->settings->get('pwa_description', '');
        if ($desc !== '') {
            $manifest['description'] = $desc;
        }

        $icons = [];
        $icon192  = $this->settings->get('pwa_icon_192', '');
        $icon512  = $this->settings->get('pwa_icon', '');
        $iconMask = $this->settings->get('pwa_icon_maskable', '');

        if ($icon192 !== '') {
            $icons[] = ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
        }
        if ($icon512 !== '') {
            $icons[] = ['src' => $icon512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
        }
        if ($iconMask !== '') {
            $icons[] = ['src' => $iconMask, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        }
        if ($icons) {
            $manifest['icons'] = $icons;
        }

        $headers = [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ];
        return new Response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, $headers);
    }

    public function serviceWorker(Request $request): Response
    {
        if ($this->settings->get('pwa_enabled', '0') !== '1') {
            return new Response('', 404);
        }

        $version  = $this->settings->get('pwa_sw_version', 'v1') ?: 'v1';
        $strategy = $this->settings->get('pwa_cache_strategy', 'network-first');
        $offline  = $this->settings->get('pwa_offline_page', '/offline') ?: '/offline';

        $cacheRaw   = $this->settings->get('pwa_cache_list', '/,/assets/css/app.css,/manifest.json');
        $cacheItems = array_values(array_unique(array_filter(array_map('trim', explode(',', $cacheRaw)))));
        // Always pre-cache the offline fallback page if not already included
        if ($offline && !in_array($offline, $cacheItems, true)) {
            $cacheItems[] = $offline;
        }
        $urls    = json_encode($cacheItems, JSON_UNESCAPED_SLASHES);
        $offlineJs = json_encode($offline, JSON_UNESCAPED_SLASHES);

        $fetchHandler = $this->buildFetchHandler($strategy, $offlineJs);

        $sw = <<<JS
/* SteelRoot PWA – generated, do not edit */
const CACHE_NAME = 'steelroot-{$version}';
const PRECACHE   = {$urls};
const OFFLINE    = {$offlineJs};

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_NAME).then(c => c.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  return self.clients.claim();
});

{$fetchHandler}
JS;

        $headers = [
            'Content-Type'           => 'application/javascript',
            'Cache-Control'          => 'no-store',
            'Service-Worker-Allowed' => '/',
        ];
        return new Response($sw, 200, $headers);
    }

    private function buildFetchHandler(string $strategy, string $offlineJs): string
    {
        if ($strategy === 'cache-first') {
            return <<<JS
/* Strategy: cache-first */
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.match(e.request).then(cached => {
      if (cached) return cached;
      return fetch(e.request).then(resp => {
        if (!resp || resp.status !== 200 || resp.type !== 'basic') return resp;
        caches.open(CACHE_NAME).then(c => c.put(e.request, resp.clone()));
        return resp;
      }).catch(() => e.request.mode === 'navigate' ? caches.match(OFFLINE) : null);
    })
  );
});
JS;
        }

        if ($strategy === 'stale-while-revalidate') {
            return <<<JS
/* Strategy: stale-while-revalidate */
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.open(CACHE_NAME).then(cache =>
      cache.match(e.request).then(cached => {
        const networkFetch = fetch(e.request).then(resp => {
          if (resp && resp.status === 200 && resp.type === 'basic') {
            cache.put(e.request, resp.clone());
          }
          return resp;
        }).catch(() => null);
        return cached || networkFetch
          || (e.request.mode === 'navigate' ? cache.match(OFFLINE) : null);
      })
    )
  );
});
JS;
        }

        // Default: network-first
        return <<<JS
/* Strategy: network-first */
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    fetch(e.request)
      .then(resp => {
        if (!resp || resp.status !== 200 || resp.type !== 'basic') return resp;
        caches.open(CACHE_NAME).then(c => c.put(e.request, resp.clone()));
        return resp;
      })
      .catch(() =>
        caches.match(e.request).then(
          cached => cached || (e.request.mode === 'navigate' ? caches.match(OFFLINE) : null)
        )
      )
  );
});
JS;
    }
}
