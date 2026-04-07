<?php
namespace App\Controllers;

use App\Services\SettingsService;
use Core\Container;
use Core\Request;
use Core\Response;

class PwaController
{
    private Container $container;
    private SettingsService $settings;
    private array $defaults;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);

        $app = include APP_ROOT . '/app/config/app.php';
        $siteName = trim((string)$this->settings->get('site_name', $app['name'] ?? 'SteelRoot'));
        if ($siteName === '') {
            $siteName = 'SteelRoot';
        }

        $localeMode = (string)$this->settings->get('locale_mode', 'multi');
        $lang = in_array($localeMode, ['ru', 'en'], true) ? $localeMode : 'en';

        $this->defaults = [
            'pwa_enabled' => '1',
            'pwa_name' => $siteName,
            'pwa_short_name' => mb_strlen($siteName, 'UTF-8') > 20 ? mb_substr($siteName, 0, 20, 'UTF-8') : $siteName,
            'pwa_description' => 'SteelRoot as an installable, offline-aware web app.',
            'pwa_lang' => $lang,
            'pwa_start_url' => '/',
            'pwa_scope' => '/',
            'pwa_display' => 'standalone',
            'pwa_orientation' => 'any',
            'pwa_theme_color' => '#1f6feb',
            'pwa_bg_color' => '#ffffff',
            'pwa_icon_192' => '',
            'pwa_icon' => '',
            'pwa_icon_maskable' => '',
            'pwa_sw_version' => 'v2',
            'pwa_cache_strategy' => 'network-first',
            'pwa_offline_page' => '/offline',
            'pwa_cache_list' => $this->defaultCacheList(),
            'pwa_offline_title' => 'Offline mode',
            'pwa_offline_message' => 'The connection is unavailable right now. You can retry when the network is back.',
            'pwa_offline_button' => 'Try again',
        ];
    }

    public function manifest(Request $request): Response
    {
        if ($this->setting('pwa_enabled') !== '1') {
            return new Response('Not found', 404);
        }

        $name = $this->setting('pwa_name');
        $short = $this->setting('pwa_short_name', $name) ?: $name;

        $manifest = [
            'name' => $name,
            'short_name' => $short,
            'start_url' => $this->setting('pwa_start_url'),
            'scope' => $this->setting('pwa_scope'),
            'display' => $this->setting('pwa_display'),
            'orientation' => $this->setting('pwa_orientation'),
            'theme_color' => $this->setting('pwa_theme_color'),
            'background_color' => $this->setting('pwa_bg_color'),
            'lang' => $this->setting('pwa_lang'),
        ];

        $desc = $this->setting('pwa_description');
        if ($desc !== '') {
            $manifest['description'] = $desc;
        }

        $icons = [];
        $icon192 = $this->setting('pwa_icon_192');
        $icon512 = $this->setting('pwa_icon');
        $iconMask = $this->setting('pwa_icon_maskable');

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

        return new Response(json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(Request $request): Response
    {
        if ($this->setting('pwa_enabled') !== '1') {
            return new Response('', 404);
        }

        $version = preg_replace('/[^a-zA-Z0-9._-]/', '', $this->setting('pwa_sw_version', 'v2')) ?: 'v2';
        $strategy = $this->setting('pwa_cache_strategy', 'network-first');
        $offline = $this->setting('pwa_offline_page', '/offline') ?: '/offline';
        $adminPrefix = $this->container->get('config')['admin_prefix'] ?? '/admin';

        $cacheRaw = $this->setting('pwa_cache_list');
        $cacheItems = array_values(array_unique(array_filter(array_map('trim', explode(',', $cacheRaw)))));
        foreach ($this->requiredCacheItems($offline) as $requiredUrl) {
            if (!in_array($requiredUrl, $cacheItems, true)) {
                $cacheItems[] = $requiredUrl;
            }
        }

        $sw = <<<JS
const PREFIX = 'steelroot-pwa-';
const VERSION = {$this->quoteJs('steelroot-pwa-' . $version)};
const STATIC_CACHE = VERSION + '-static';
const IMAGE_CACHE = VERSION + '-images';
const PRECACHE = {$this->quoteJsArray($cacheItems)};
const OFFLINE = {$this->quoteJs($offline)};
const RESOURCE_STRATEGY = {$this->quoteJs($strategy)};
const ADMIN_PREFIX = {$this->quoteJs($adminPrefix)};
const IMAGE_EXT_RE = /\\.(?:png|jpe?g|gif|webp|avif|svg)$/i;
const STATIC_EXT_RE = /\\.(?:css|js|mjs|woff2?|ttf|eot)$/i;
const EXCLUDED_PREFIXES = [ADMIN_PREFIX, '/api', '/login', '/register', '/logout', '/profile', '/profile/', '/users/account', '/checkout', '/cart'];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(cache => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter(key => key.startsWith(PREFIX) && !key.startsWith(VERSION)).map(key => caches.delete(key)));
    await self.clients.claim();
  })());
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname === '/sw.js') return;

  if (shouldBypass(url.pathname)) {
    return;
  }

  if (request.mode === 'navigate' || acceptsHtml(request)) {
    event.respondWith(handleHtml(request));
    return;
  }

  if (STATIC_EXT_RE.test(url.pathname)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  if (IMAGE_EXT_RE.test(url.pathname)) {
    event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE));
    return;
  }

  event.respondWith(handleResource(request));
});

function shouldBypass(pathname) {
  if (pathname === OFFLINE || pathname === '/manifest.json') {
    return false;
  }
  return EXCLUDED_PREFIXES.some(prefix => pathname === prefix || pathname.startsWith(prefix + '/'));
}

function acceptsHtml(request) {
  const accept = request.headers.get('accept') || '';
  return accept.includes('text/html');
}

async function cacheFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) {
    return cached;
  }
  const response = await fetch(request);
  if (isCacheable(response)) {
    await cache.put(request, response.clone());
  }
  return response;
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request).then(response => {
    if (isCacheable(response)) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => null);
  return cached || networkPromise || new Response('', { status: 504, statusText: 'Gateway Timeout' });
}

async function networkFirst(request, cacheName) {
  const cache = await caches.open(cacheName);
  try {
    const response = await fetch(request);
    if (isCacheable(response)) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await cache.match(request);
    return cached || new Response('', { status: 504, statusText: 'Gateway Timeout' });
  }
}

async function handleHtml(request) {
  try {
    return await fetch(request);
  } catch (error) {
    return offlineResponse();
  }
}

async function handleResource(request) {
  if (RESOURCE_STRATEGY === 'cache-first') {
    return cacheFirst(request, STATIC_CACHE);
  }
  if (RESOURCE_STRATEGY === 'network-first') {
    return networkFirst(request, STATIC_CACHE);
  }
  return staleWhileRevalidate(request, STATIC_CACHE);
}

async function offlineResponse() {
  const staticCache = await caches.open(STATIC_CACHE);
  return (await staticCache.match(OFFLINE)) || Response.error();
}

function isCacheable(response) {
  return response && response.ok && (response.type === 'basic' || response.type === 'default');
}
JS;

        return new Response($sw, 200, [
            'Content-Type' => 'application/javascript',
            'Cache-Control' => 'no-store',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(Request $request): Response
    {
        ob_start();
        $settings = $this->settings->all();
        $title = $this->setting('pwa_offline_title');
        $message = $this->setting('pwa_offline_message');
        $button = $this->setting('pwa_offline_button');
        include APP_ROOT . '/app/views/pwa/offline.php';
        $html = (string)ob_get_clean();

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function setting(string $key, ?string $fallback = null): string
    {
        $default = $fallback ?? (string)($this->defaults[$key] ?? '');
        return (string)$this->settings->get($key, $default);
    }

    private function quoteJs(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function quoteJsArray(array $items): string
    {
        return json_encode(array_values($items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function defaultCacheList(): string
    {
        return implode(',', $this->requiredCacheItems('/offline'));
    }

    private function requiredCacheItems(string $offline): array
    {
        return [
            '/',
            $offline !== '' ? $offline : '/offline',
            '/assets/css/app.css',
            '/assets/css/pwa-offline.css',
            '/assets/js/pwa-init.js',
            '/assets/js/popup.js',
            '/assets/js/profile-panel.js',
            '/assets/js/gallery-lightbox.js',
            '/modules/Gallery/assets/js/gallery.js',
            '/manifest.json',
        ];
    }
}
