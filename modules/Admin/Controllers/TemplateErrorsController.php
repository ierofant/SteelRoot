<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class TemplateErrorsController
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
        $codes = $this->codes();
        $active = $request->query['code'] ?? '404';
        if (!in_array($active, $codes, true)) {
            $active = '404';
        }
        $defaults = $this->defaults();
        $data = array_merge($defaults, $this->settings->all());
        $html = $this->container->get('renderer')->render('admin/template_errors', [
            'title' => __('errors.settings.title'),
            'csrf' => Csrf::token('errors_settings'),
            'settings' => $data,
            'saved' => !empty($request->query['saved']),
            'codes' => $codes,
            'active' => $active,
        ]);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('errors_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $codes = $this->codes();
        $active = $request->body['code'] ?? '404';
        if (!in_array($active, $codes, true)) {
            $active = '404';
        }
        $current = array_merge($this->defaults(), $this->settings->all());
        $fields = [
            "error_{$active}_custom_enabled",
            "error_{$active}_title",
            "error_{$active}_message",
            "error_{$active}_description",
            "error_{$active}_cta_text",
            "error_{$active}_cta_url",
            "error_{$active}_show_home_button",
            "error_{$active}_icon",
        ];
        foreach ($fields as $field) {
            $val = $request->body[$field] ?? $current[$field] ?? '';
            if (str_ends_with($field, '_custom_enabled') || str_ends_with($field, '_show_home_button')) {
                $val = !empty($val) ? 1 : 0;
            } else {
                $val = trim((string)$val);
            }
            $current[$field] = $val;
        }
        foreach ($current as $k => $v) {
            $this->settings->set($k, $v);
        }
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/template/errors?code=' . urlencode($active) . '&saved=1']);
    }

    private function defaults(): array
    {
        $errors = $this->codes();
        $defaults = [];
        foreach ($errors as $code) {
            $defaults["error_{$code}_custom_enabled"] = 0;
            $defaults["error_{$code}_title"] = '';
            $defaults["error_{$code}_message"] = '';
            $defaults["error_{$code}_description"] = '';
            $defaults["error_{$code}_cta_text"] = '';
            $defaults["error_{$code}_cta_url"] = '';
            $defaults["error_{$code}_show_home_button"] = 0;
            $defaults["error_{$code}_icon"] = '';
        }
        return $defaults;
    }

    private function codes(): array
    {
        return ['404', '403', '500', '503'];
    }
}
