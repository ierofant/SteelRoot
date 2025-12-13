<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class ThemeController
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
        $html = $this->container->get('renderer')->render('admin/theme', [
            'title' => 'Theme',
            'csrf' => Csrf::token('theme_settings'),
            'settings' => $this->settings->all(),
            'saved' => !empty($request->query['saved']),
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('theme_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $defaults = [
            'theme_primary' => '#22d3ee',
            'theme_secondary' => '#0f172a',
            'theme_accent' => '#f97316',
            'theme_bg' => '#f7f9fc',
            'theme_text' => '#0f172a',
            'theme_card' => '#ffffff',
            'theme_radius' => '12',
            'theme_logo' => '',
            'theme_favicon' => '',
        ];
        if (!empty($request->body['reset_theme'])) {
            $this->settings->bulkSet($defaults);
        } else {
            $keys = array_keys($defaults);
            $data = [];
            foreach ($keys as $k) {
                $data[$k] = trim((string)($request->body[$k] ?? $defaults[$k]));
            }
            // Handle uploads
            $uploads = $this->handleUploads();
            if (!empty($uploads['logo'])) {
                $data['theme_logo'] = $uploads['logo'];
            }
            if (!empty($uploads['favicon'])) {
                $data['theme_favicon'] = $uploads['favicon'];
            }
            $this->settings->bulkSet($data);
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/theme?saved=1']);
    }

    private function handleUploads(): array
    {
        $result = ['logo' => null, 'favicon' => null];
        $files = $_FILES ?? [];
        $destDir = APP_ROOT . '/storage/uploads/theme';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }
        foreach (['logo' => 'logo_file', 'favicon' => 'favicon_file'] as $key => $input) {
            if (empty($files[$input]['tmp_name'])) {
                continue;
            }
            $tmp = $files[$input]['tmp_name'];
            $name = $files[$input]['name'] ?? ($input . '.png');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','svg','ico','webp'], true)) {
                continue;
            }
            $safe = uniqid($input . '_', true) . '.' . $ext;
            $target = $destDir . '/' . $safe;
            if (@move_uploaded_file($tmp, $target)) {
                $result[$key] = '/storage/uploads/theme/' . $safe;
            }
        }
        return $result;
    }
}
