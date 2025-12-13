<?php
namespace Modules\Popups\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;

class AdminPopupsController
{
    private ModuleSettings $settings;

    public function __construct(Container $container)
    {
        $this->settings = $container->get(ModuleSettings::class);
    }

    public function index(Request $request): Response
    {
        $data = $this->settings->all('popups');
        $html = $this->render('modules/Popups/views/admin/index.php', [
            'title' => 'Popups',
            'csrf' => Csrf::token('admin_popups'),
            'settings' => $data,
            'saved' => !empty($request->query['saved']),
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('admin_popups', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $s = $request->body;
        $bool = fn($key) => isset($s[$key]) ? '1' : '0';
        $int = fn($key, $min, $max, $def) => max($min, min($max, (int)($s[$key] ?? $def)));
        $text = fn($key, $def = '') => trim($s[$key] ?? $def);

        $this->settings->set('popups', 'adult_enabled', $bool('adult_enabled'));
        $pagesRaw = $s['adult_pages'] ?? '[]';
        $pages = json_decode($pagesRaw, true);
        if (!is_array($pages)) {
            $pages = [];
        }
        $this->settings->set('popups', 'adult_pages', $pages);
        $this->settings->set('popups', 'adult_delay', $int('adult_delay', 0, 60000, 500));
        $this->settings->set('popups', 'adult_once_per_session', $bool('adult_once_per_session'));
        $this->settings->set('popups', 'adult_text_ru', $text('adult_text_ru'));
        $this->settings->set('popups', 'adult_text_en', $text('adult_text_en'));

        $this->settings->set('popups', 'cookie_enabled', $bool('cookie_enabled'));
        $this->settings->set('popups', 'cookie_text_ru', $text('cookie_text_ru'));
        $this->settings->set('popups', 'cookie_text_en', $text('cookie_text_en'));
        $this->settings->set('popups', 'cookie_button_text', $text('cookie_button_text', 'OK'));
        $position = $text('cookie_position', 'bottom-right');
        $this->settings->set('popups', 'cookie_position', in_array($position, ['bottom-left','bottom-right','top'], true) ? $position : 'bottom-right');
        $store = $text('cookie_store', 'local');
        $this->settings->set('popups', 'cookie_store', in_array($store, ['local','session'], true) ? $store : 'local');
        $this->settings->set('popups', 'cookie_key', $text('cookie_key', 'cookie_policy_accepted'));

        $prefix = $this->settingsUrlPrefix();
        return new Response('', 302, ['Location' => $prefix . '/popups?saved=1']);
    }

    private function settingsUrlPrefix(): string
    {
        return defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
    }

    private function render(string $path, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include APP_ROOT . '/' . ltrim($path, '/');
        return ob_get_clean();
    }
}
