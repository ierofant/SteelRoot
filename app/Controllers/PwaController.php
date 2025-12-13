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
        if (!$this->settings->get('pwa_enabled', '0')) {
            return new Response('Not enabled', 404);
        }
        $app = include APP_ROOT . '/app/config/app.php';
        $name = $this->settings->get('pwa_name', $app['name'] ?? 'SteelRoot');
        $short = $this->settings->get('pwa_short_name', $name);
        $startUrl = $this->settings->get('pwa_start_url', '/');
        $themeColor = $this->settings->get('pwa_theme_color', '#1f6feb');
        $bgColor = $this->settings->get('pwa_bg_color', '#ffffff');
        $icon = $this->settings->get('pwa_icon', '');
        $display = $this->settings->get('pwa_display', 'standalone');

        $manifest = [
            'name' => $name,
            'short_name' => $short,
            'start_url' => $startUrl,
            'display' => $display,
            'theme_color' => $themeColor,
            'background_color' => $bgColor,
        ];
        if ($icon) {
            $manifest['icons'] = [
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png'],
            ];
        }
        $manifest['scope'] = '/';
        $manifest['lang'] = 'en';
        return new Response(json_encode($manifest, JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function serviceWorker(Request $request): Response
    {
        if (!$this->settings->get('pwa_enabled', '0')) {
            return new Response('', 404);
        }
        $version = $this->settings->get('pwa_sw_version', 'v1');
        $cacheListRaw = $this->settings->get('pwa_cache_list', '/,/assets/css/app.css,/assets/css/theme-light.css,/assets/css/theme-dark.css,/manifest.json');
        $cacheArray = array_filter(array_map('trim', explode(',', $cacheListRaw)));
        $urls = json_encode($cacheArray ?: ['/', '/assets/css/app.css', '/assets/css/theme-light.css', '/assets/css/theme-dark.css', '/manifest.json']);
        $sw = <<<JS
const CACHE_NAME='steelroot-pwa-{$version}';
const URLS={$urls};
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE_NAME).then(cache=>cache.addAll(URLS)));
});
self.addEventListener('activate',event=>{
  event.waitUntil(
    caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE_NAME).map(k=>caches.delete(k))))
  );
});
self.addEventListener('fetch',event=>{
  event.respondWith(
    caches.match(event.request).then(resp=>{
      return resp || fetch(event.request).then(response=>{
        if (!response || response.status !== 200 || response.type !== 'basic') return response;
        const respToCache = response.clone();
        caches.open(CACHE_NAME).then(cache=>{ cache.put(event.request, respToCache); });
        return response;
      });
    })
  );
});
JS;
        return new Response($sw, 200, ['Content-Type' => 'application/javascript']);
    }
}
