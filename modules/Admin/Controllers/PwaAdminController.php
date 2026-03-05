<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class PwaAdminController
{
    private Container $container;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings  = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $flash = $_SESSION['pwa_flash'] ?? null;
        unset($_SESSION['pwa_flash']);

        $html = $this->container->get('renderer')->render('admin/pwa', [
            'title'    => 'PWA Settings',
            'csrf'     => Csrf::token('pwa_settings'),
            'settings' => $this->settings->all(),
            'flash'    => $flash === 'saved' ? 'Settings saved.' : ($flash ?: null),
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('pwa_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $b = $request->body;

        $enabled  = isset($b['pwa_enabled'])  ? '1' : '0';
        $name     = trim($b['pwa_name']        ?? '');
        $short    = trim($b['pwa_short_name']  ?? '');
        $desc     = trim($b['pwa_description'] ?? '');
        $lang     = trim($b['pwa_lang']        ?? 'en');
        $startUrl = trim($b['pwa_start_url']   ?? '/');
        $scope    = trim($b['pwa_scope']       ?? '/');
        $display  = trim($b['pwa_display']     ?? 'standalone');
        $orient   = trim($b['pwa_orientation'] ?? 'any');
        $theme    = trim($b['pwa_theme_color'] ?? '#1f6feb');
        $bg       = trim($b['pwa_bg_color']    ?? '#ffffff');
        $icon192  = trim($b['pwa_icon_192']    ?? '');
        $icon512  = trim($b['pwa_icon']        ?? '');
        $iconMask = trim($b['pwa_icon_maskable'] ?? '');
        $version  = trim($b['pwa_sw_version']  ?? 'v1') ?: 'v1';
        $strategy = trim($b['pwa_cache_strategy'] ?? 'network-first');
        $offline  = trim($b['pwa_offline_page']   ?? '/offline');

        // Textarea cache list: one URL per line → comma-separated storage
        $cacheLines = array_filter(array_map('trim', explode("\n", $b['pwa_cache_list'] ?? '')));
        $cacheList  = implode(',', $cacheLines);

        // Whitelist validation
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

        $this->settings->bulkSet([
            'pwa_enabled'        => $enabled,
            'pwa_name'           => $name,
            'pwa_short_name'     => $short,
            'pwa_description'    => $desc,
            'pwa_lang'           => $lang,
            'pwa_start_url'      => $startUrl,
            'pwa_scope'          => $scope,
            'pwa_display'        => $display,
            'pwa_orientation'    => $orient,
            'pwa_theme_color'    => $theme,
            'pwa_bg_color'       => $bg,
            'pwa_icon_192'       => $icon192,
            'pwa_icon'           => $icon512,
            'pwa_icon_maskable'  => $iconMask,
            'pwa_sw_version'     => $version,
            'pwa_cache_strategy' => $strategy,
            'pwa_offline_page'   => $offline,
            'pwa_cache_list'     => $cacheList,
        ]);

        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        $_SESSION['pwa_flash'] = 'saved';
        return new Response('', 302, ['Location' => $prefix . '/pwa']);
    }
}
