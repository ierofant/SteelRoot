<?php
namespace Modules\Admin\Controllers;

use App\Services\SettingsService;
use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;

class PwaAdminController
{
    private Container $container;
    private SettingsService $settings;
    private array $defaults;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);

        $siteName = trim((string)$this->settings->get('site_name', 'SteelRoot'));
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

    public function index(Request $request): Response
    {
        $flash = $_SESSION['pwa_flash'] ?? null;
        unset($_SESSION['pwa_flash']);

        $settings = array_merge($this->defaults, $this->settings->all());
        $settings['pwa_cache_list'] = $this->normalizeCacheList(
            (string)($settings['pwa_cache_list'] ?? ''),
            (string)($settings['pwa_offline_page'] ?? '/offline')
        );

        $html = $this->container->get('renderer')->render('admin/pwa', [
            'title' => 'PWA Settings',
            'csrf' => Csrf::token('pwa_settings'),
            'settings' => $settings,
            'flash' => $flash === 'saved' ? 'Settings saved.' : ($flash ?: null),
        ]);

        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('pwa_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $b = $request->body;

        $enabled = isset($b['pwa_enabled']) ? '1' : '0';
        $name = trim($b['pwa_name'] ?? '');
        $short = trim($b['pwa_short_name'] ?? '');
        $desc = trim($b['pwa_description'] ?? '');
        $lang = trim($b['pwa_lang'] ?? 'en');
        $startUrl = trim($b['pwa_start_url'] ?? '/');
        $scope = trim($b['pwa_scope'] ?? '/');
        $display = trim($b['pwa_display'] ?? 'standalone');
        $orient = trim($b['pwa_orientation'] ?? 'any');
        $theme = trim($b['pwa_theme_color'] ?? '#1f6feb');
        $bg = trim($b['pwa_bg_color'] ?? '#ffffff');
        $icon192 = trim($b['pwa_icon_192'] ?? '');
        $icon512 = trim($b['pwa_icon'] ?? '');
        $iconMask = trim($b['pwa_icon_maskable'] ?? '');
        $version = trim($b['pwa_sw_version'] ?? 'v2') ?: 'v2';
        $strategy = trim($b['pwa_cache_strategy'] ?? 'network-first');
        $offline = trim($b['pwa_offline_page'] ?? '/offline');
        $offlineTitle = trim($b['pwa_offline_title'] ?? $this->defaults['pwa_offline_title']);
        $offlineMessage = trim($b['pwa_offline_message'] ?? $this->defaults['pwa_offline_message']);
        $offlineButton = trim($b['pwa_offline_button'] ?? $this->defaults['pwa_offline_button']);

        $cacheLines = array_filter(array_map('trim', explode("\n", $b['pwa_cache_list'] ?? '')));
        $cacheList = implode(',', $cacheLines);

        if (!in_array($display, ['standalone', 'minimal-ui', 'fullscreen', 'browser'], true)) {
            $display = 'standalone';
        }
        if (!in_array($orient, ['any', 'portrait', 'landscape', 'portrait-primary', 'landscape-primary'], true)) {
            $orient = 'any';
        }
        if (!in_array($strategy, ['network-first', 'cache-first', 'stale-while-revalidate'], true)) {
            $strategy = 'network-first';
        }
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $theme)) {
            $theme = '#1f6feb';
        }
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $bg)) {
            $bg = '#ffffff';
        }
        if ($lang && !preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
            $lang = 'en';
        }
        if ($offline === '' || $offline[0] !== '/') {
            $offline = '/offline';
        }

        $cacheList = $this->normalizeCacheList($cacheList, $offline);
        if ($offlineTitle === '') {
            $offlineTitle = $this->defaults['pwa_offline_title'];
        }
        if ($offlineMessage === '') {
            $offlineMessage = $this->defaults['pwa_offline_message'];
        }
        if ($offlineButton === '') {
            $offlineButton = $this->defaults['pwa_offline_button'];
        }

        $this->settings->bulkSet([
            'pwa_enabled' => $enabled,
            'pwa_name' => $name,
            'pwa_short_name' => $short,
            'pwa_description' => $desc,
            'pwa_lang' => $lang,
            'pwa_start_url' => $startUrl,
            'pwa_scope' => $scope,
            'pwa_display' => $display,
            'pwa_orientation' => $orient,
            'pwa_theme_color' => $theme,
            'pwa_bg_color' => $bg,
            'pwa_icon_192' => $icon192,
            'pwa_icon' => $icon512,
            'pwa_icon_maskable' => $iconMask,
            'pwa_sw_version' => $version,
            'pwa_cache_strategy' => $strategy,
            'pwa_offline_page' => $offline,
            'pwa_cache_list' => $cacheList,
            'pwa_offline_title' => $offlineTitle,
            'pwa_offline_message' => $offlineMessage,
            'pwa_offline_button' => $offlineButton,
        ]);

        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        $_SESSION['pwa_flash'] = 'saved';
        return new Response('', 302, ['Location' => $prefix . '/pwa']);
    }

    private function defaultCacheList(): string
    {
        return implode(',', $this->requiredCacheItems('/offline'));
    }

    private function normalizeCacheList(string $cacheList, string $offline): string
    {
        $items = array_values(array_unique(array_filter(array_map('trim', explode(',', $cacheList)))));
        foreach ($this->requiredCacheItems($offline) as $requiredUrl) {
            if (!in_array($requiredUrl, $items, true)) {
                $items[] = $requiredUrl;
            }
        }

        return implode(',', $items);
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
