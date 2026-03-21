<?php
namespace Modules\FAQ\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\ModuleSettings;
use Core\Request;
use Core\Response;
use Modules\FAQ\Models\FaqModel;

class FaqAdminController
{
    private Container $container;
    private Database $db;
    private ModuleSettings $moduleSettings;
    private FaqModel $model;
    private array $fields = array (
  0 => 
  array (
    'name' => 'question',
    'type' => 'string',
    'required' => true,
  ),
  1 => 
  array (
    'name' => 'answer',
    'type' => 'text',
  ),
  2 => 
  array (
    'name' => 'status',
    'type' => 'enum',
    'values' => 
    array (
      0 => 'draft',
      1 => 'published',
    ),
  ),
);
    private array $listColumns = array (
  0 => 'question',
  1 => 'status',
  2 => 'updated_at',
);
    private array $formFields = array (
  0 => 'question',
  1 => 'answer',
  2 => 'status',
);

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->model = new FaqModel($this->db);
    }

    public function index(Request $request): Response
    {
        $items = $this->model->all();
        $html = $this->container->get('renderer')->render('@' . strtoupper('faq') . '/admin/index', [
            'title' => __('faq.title'),
            'items' => $items,
            'listColumns' => $this->listColumns,
            'schemaFields' => $this->fields,
            'csrf' => Csrf::token('faq_admin'),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('@' . strtoupper('faq') . '/admin/edit', [
            'title' => __('faq.create'),
            'schemaFields' => $this->fields,
            'formFields' => $this->formFields,
            'csrf' => Csrf::token('faq_admin'),
            'item' => null,
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('faq_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $data = $this->sanitize($request->body);
        $error = $this->validate($data);
        if ($error) {
            return new Response($error, 422);
        }
        $this->model->create($data);
        return $this->redirect();
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $item = $this->model->find($id);
        $html = $this->container->get('renderer')->render('@' . strtoupper('faq') . '/admin/edit', [
            'title' => __('faq.edit'),
            'schemaFields' => $this->fields,
            'formFields' => $this->formFields,
            'csrf' => Csrf::token('faq_admin'),
            'item' => $item,
        ]);
        return new Response($html);
    }

    public function settings(Request $request): Response
    {
        $defaults = [
            'seo_title_en' => 'Tattoo FAQ',
            'seo_title_ru' => 'FAQ о татуировках',
            'seo_desc_en' => 'Tattoo FAQ about booking, pain, healing, aftercare and safety.',
            'seo_desc_ru' => 'Частые вопросы о татуировках: запись, боль, заживление, уход и безопасность.',
            'og_title_en' => 'Tattoo FAQ',
            'og_title_ru' => 'FAQ о татуировках',
            'og_desc_en' => 'Answers to the most common tattoo questions before and after your session.',
            'og_desc_ru' => 'Ответы на самые частые вопросы о татуировках до и после сеанса.',
            'og_image' => '',
        ];
        $settings = array_merge($defaults, $this->moduleSettings->all('faq'));

        $html = $this->container->get('renderer')->render('@FAQ/admin/settings', [
            'title' => __('faq.settings.page_title'),
            'csrf' => Csrf::token('faq_settings'),
            'settings' => $settings,
        ]);

        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('faq_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $map = [
            'seo_title_en' => 'faq_seo_title_en',
            'seo_title_ru' => 'faq_seo_title_ru',
            'seo_desc_en' => 'faq_seo_desc_en',
            'seo_desc_ru' => 'faq_seo_desc_ru',
            'og_title_en' => 'faq_og_title_en',
            'og_title_ru' => 'faq_og_title_ru',
            'og_desc_en' => 'faq_og_desc_en',
            'og_desc_ru' => 'faq_og_desc_ru',
            'og_image' => 'faq_og_image',
        ];

        foreach ($map as $key => $field) {
            $this->moduleSettings->set('faq', $key, trim((string)($request->body[$field] ?? '')));
        }

        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/settings?msg=saved']);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('faq_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $data = $this->sanitize($request->body);
        $error = $this->validate($data);
        if ($error) {
            return new Response($error, 422);
        }
        $this->model->update($id, $data);
        return $this->redirect();
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('faq_admin', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->model->delete($id);
        return $this->redirect();
    }

    private function redirect(): Response
    {
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/faq']);
    }

    private function sanitize(array $input): array
    {
        $data = [];
        foreach ($this->fields as $field) {
            $name = $field['name'] ?? null;
            if (!$name) {
                continue;
            }
            $data[$name] = trim((string)($input[$name] ?? ''));
        }
        return $data;
    }

    private function validate(array $data): ?string
    {
        foreach ($this->fields as $field) {
            $name = $field['name'] ?? '';
            if ($name === '') {
                continue;
            }
            $required = !empty($field['required']);
            if ($required && ($data[$name] ?? '') === '') {
                return "Field {$name} is required";
            }
            if (($field['type'] ?? '') === 'enum' && !empty($field['values'])) {
                if ($data[$name] !== '' && !in_array($data[$name], $field['values'], true)) {
                    return "Field {$name} has invalid value";
                }
            }
        }
        return null;
    }
}
