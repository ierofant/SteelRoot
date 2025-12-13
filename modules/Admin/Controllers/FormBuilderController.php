<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class FormBuilderController
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
        $raw = $this->settings->get('contact_form_schema', json_encode($this->defaultSchema(), JSON_PRETTY_PRINT));
        $decoded = json_decode($raw, true);
        $fields = is_array($decoded) ? $decoded : $this->defaultSchema();
        $html = $this->render($fields, Csrf::token('form_builder'), !empty($request->query['saved']));
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('form_builder', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $schema = $request->body['schema'] ?? '[]';
        $decoded = json_decode($schema, true);
        if (!is_array($decoded)) {
            return new Response('Invalid JSON schema', 422);
        }
        $this->settings->set('contact_form_schema', json_encode($decoded, JSON_PRETTY_PRINT));
        $this->settings->set('contact_blacklist', (string)($request->body['contact_blacklist'] ?? ''));
        $this->settings->set('contact_block_regex', (string)($request->body['contact_block_regex'] ?? ''));
        $this->settings->set('contact_block_domains', (string)($request->body['contact_block_domains'] ?? ''));
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/forms?saved=1']);
    }

    private function render(array $fields, string $csrf, bool $saved): string
    {
        return $this->container->get('renderer')->render('admin/forms', [
            'title' => 'Form Builder',
            'fields' => $fields,
            'csrf' => $csrf,
            'saved' => $saved,
            'blacklist' => (string)$this->settings->get('contact_blacklist', ''),
            'blockRegex' => (string)$this->settings->get('contact_block_regex', ''),
            'blockDomains' => (string)$this->settings->get('contact_block_domains', ''),
        ]);
    }

    private function defaultSchema(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ];
    }
}
