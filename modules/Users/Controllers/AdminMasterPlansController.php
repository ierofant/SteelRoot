<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\Users\Services\UserRepository;

class AdminMasterPlansController
{
    private Container $container;
    private UserRepository $users;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
    }

    public function index(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/plans_list', [
            'title' => 'Master plans',
            'plans' => $this->users->listMasterPlans(false),
            'flash' => $this->messageFromQuery((string)($request->query['msg'] ?? '')),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        return new Response($this->renderForm('Create master plan', [
            'active' => 1,
            'featured' => 0,
            'currency' => 'USD',
            'gallery_limit' => 0,
            'pinned_works_limit' => 0,
        ]));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('admin_master_plans', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $data = $this->normalizePayload($request);
        if ($data['name'] === '' || $data['slug'] === '') {
            return new Response($this->renderForm('Create master plan', $data, 'Name and slug are required.'), 400);
        }
        $this->users->saveMasterPlan(0, $data);
        return new Response('', 302, ['Location' => $this->prefix() . '/users/plans?msg=created']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $plan = $this->users->findMasterPlan($id);
        if (!$plan) {
            return new Response('Not found', 404);
        }
        return new Response($this->renderForm('Edit master plan', $plan));
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('admin_master_plans', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $plan = $this->users->findMasterPlan($id);
        if (!$plan) {
            return new Response('Not found', 404);
        }
        $data = $this->normalizePayload($request);
        if ($data['name'] === '' || $data['slug'] === '') {
            $data['id'] = $id;
            return new Response($this->renderForm('Edit master plan', $data, 'Name and slug are required.'), 400);
        }
        $this->users->saveMasterPlan($id, $data);
        return new Response('', 302, ['Location' => $this->prefix() . '/users/plans?msg=updated']);
    }

    private function renderForm(string $title, array $plan, ?string $error = null): string
    {
        return $this->container->get('renderer')->render('users/admin/plan_form', [
            'title' => $title,
            'plan' => $plan,
            'error' => $error,
            'csrf' => Csrf::token('admin_master_plans'),
        ]);
    }

    private function normalizePayload(Request $request): array
    {
        $capabilities = array_values(array_filter(array_map('trim', (array)($request->body['capabilities'] ?? []))));
        return [
            'name' => trim((string)($request->body['name'] ?? '')),
            'slug' => $this->slugify((string)($request->body['slug'] ?? '')),
            'description' => trim((string)($request->body['description'] ?? '')),
            'active' => !empty($request->body['active']) ? 1 : 0,
            'sort_order' => (int)($request->body['sort_order'] ?? 0),
            'price' => trim((string)($request->body['price'] ?? '')),
            'currency' => strtoupper(trim((string)($request->body['currency'] ?? 'USD'))),
            'period_label' => trim((string)($request->body['period_label'] ?? '')),
            'featured' => !empty($request->body['featured']) ? 1 : 0,
            'duration_days' => (int)($request->body['duration_days'] ?? 0),
            'gallery_limit' => (int)($request->body['gallery_limit'] ?? 0),
            'pinned_works_limit' => (int)($request->body['pinned_works_limit'] ?? 0),
            'allow_cover' => !empty($request->body['allow_cover']) ? 1 : 0,
            'allow_contacts' => !empty($request->body['allow_contacts']) ? 1 : 0,
            'allow_social_links' => !empty($request->body['allow_social_links']) ? 1 : 0,
            'allow_ratings' => !empty($request->body['allow_ratings']) ? 1 : 0,
            'priority_boost' => (int)($request->body['priority_boost'] ?? 0),
            'capabilities_json' => $capabilities ? json_encode($capabilities, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ];
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value);
        return trim((string)$value, '-_');
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }

    private function messageFromQuery(string $code): ?string
    {
        return match ($code) {
            'created' => 'Plan created',
            'updated' => 'Plan updated',
            default => null,
        };
    }
}
