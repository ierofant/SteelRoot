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
        $this->settings = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $html = $this->render([
            'title' => 'PWA Settings',
            'csrf' => Csrf::token('pwa_settings'),
            'settings' => $this->settings->all(),
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('pwa_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $enabled = isset($request->body['pwa_enabled']) ? '1' : '0';
        $name = trim($request->body['pwa_name'] ?? '');
        $short = trim($request->body['pwa_short_name'] ?? '');
        $start = trim($request->body['pwa_start_url'] ?? '/');
        $display = trim($request->body['pwa_display'] ?? 'standalone');
        $theme = trim($request->body['pwa_theme_color'] ?? '#1f6feb');
        $bg = trim($request->body['pwa_bg_color'] ?? '#ffffff');
        $icon = trim($request->body['pwa_icon'] ?? '');
        $version = trim($request->body['pwa_sw_version'] ?? 'v1');
        $cacheList = trim($request->body['pwa_cache_list'] ?? '/,/assets/css/app.css');
        $this->settings->bulkSet([
            'pwa_enabled' => $enabled,
            'pwa_name' => $name,
            'pwa_short_name' => $short,
            'pwa_start_url' => $start,
            'pwa_display' => $display,
            'pwa_theme_color' => $theme,
            'pwa_bg_color' => $bg,
            'pwa_icon' => $icon,
            'pwa_sw_version' => $version,
            'pwa_cache_list' => $cacheList,
        ]);
        $html = $this->render([
            'title' => 'PWA Settings',
            'csrf' => Csrf::token('pwa_settings'),
            'settings' => $this->settings->all(),
            'message' => 'Saved',
        ]);
        return new Response($html);
    }

    private function render(array $data): string
    {
        return $this->container->get('renderer')->render('admin/pwa', $data);
    }
}
