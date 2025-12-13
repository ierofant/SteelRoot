<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use Core\Database;
use App\Services\SettingsService;

class EmbeddableFormsController
{
    private Container $container;
    private Database $db;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->settings = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $forms = $this->db->fetchAll("SELECT * FROM embed_forms ORDER BY id DESC");
        $html = $this->container->get('renderer')->render('admin/forms_embeds', [
            'title' => __('forms.embed.title'),
            'forms' => $forms,
            'csrf' => Csrf::token('embed_forms_delete'),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        $html = $this->renderEdit([
            'name' => '',
            'slug' => '',
            'fields' => json_encode($this->defaultSchema(), JSON_PRETTY_PRINT),
            'recipient_email' => '',
            'success_en' => '',
            'success_ru' => '',
            'enabled' => 1,
        ], true);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('embed_forms_save', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $data = $this->sanitize($request);
        if ($this->slugExists($data['slug'])) {
            return new Response(__('forms.embed.errors.slug_exists'), 400);
        }
        $this->db->execute("
            INSERT INTO embed_forms (name, slug, fields, recipient_email, success_en, success_ru, enabled, created_at, updated_at)
            VALUES (:name, :slug, :fields, :recipient_email, :success_en, :success_ru, :enabled, NOW(), NOW())
        ", $data);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/forms/embeds']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $form = $this->db->fetch("SELECT * FROM embed_forms WHERE id = ?", [$id]);
        if (!$form) {
            return new Response('Not found', 404);
        }
        $html = $this->renderEdit($form, false);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('embed_forms_save', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $current = $this->db->fetch("SELECT slug FROM embed_forms WHERE id = ?", [$id]);
        if (!$current) {
            return new Response('Not found', 404);
        }
        $data = $this->sanitize($request);
        if ($data['slug'] !== $current['slug'] && $this->slugExists($data['slug'])) {
            return new Response(__('forms.embed.errors.slug_exists'), 400);
        }
        $data['id'] = $id;
        $this->db->execute("
            UPDATE embed_forms SET
                name = :name,
                slug = :slug,
                fields = :fields,
                recipient_email = :recipient_email,
                success_en = :success_en,
                success_ru = :success_ru,
                enabled = :enabled,
                updated_at = NOW()
            WHERE id = :id
        ", $data);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/forms/embeds']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('embed_forms_delete', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->db->execute("DELETE FROM embed_forms WHERE id = ?", [$id]);
        return new Response('', 302, ['Location' => (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/forms/embeds']);
    }

    private function renderEdit(array $form, bool $isNew): string
    {
        return $this->container->get('renderer')->render('admin/forms_embed_edit', [
            'title' => $isNew ? __('forms.embed.create') : __('forms.embed.edit'),
            'csrf' => Csrf::token('embed_forms_save'),
            'formData' => $form,
            'isNew' => $isNew,
        ]);
    }

    private function sanitize(Request $request): array
    {
        $slug = strtolower(trim($request->body['slug'] ?? ''));
        $slug = preg_replace('/[^a-z0-9\-]+/i', '-', $slug);
        $slug = trim($slug, '-');
        $fields = $request->body['fields'] ?? '[]';
        $decoded = json_decode($fields, true);
        if (!is_array($decoded)) {
            $decoded = $this->defaultSchema();
        }
        return [
            'name' => trim($request->body['name'] ?? ''),
            'slug' => $slug,
            'fields' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'recipient_email' => trim($request->body['recipient_email'] ?? ''),
            'success_en' => trim($request->body['success_en'] ?? ''),
            'success_ru' => trim($request->body['success_ru'] ?? ''),
            'enabled' => !empty($request->body['enabled']) ? 1 : 0,
        ];
    }

    private function slugExists(string $slug): bool
    {
        if ($slug === '') {
            return true;
        }
        $row = $this->db->fetch("SELECT id FROM embed_forms WHERE slug = ?", [$slug]);
        return (bool)$row;
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
