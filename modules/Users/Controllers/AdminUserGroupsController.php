<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\UserRepository;

class AdminUserGroupsController
{
    private Container $container;
    private UserRepository $users;
    private UserAccessService $access;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
        $this->access = $container->get(UserAccessService::class);
    }

    public function index(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/groups_list', [
            'title' => 'User groups',
            'groups' => $this->users->listGroups(),
            'flash' => $this->messageFromQuery((string)($request->query['msg'] ?? '')),
            'csrf' => Csrf::token('admin_user_groups'),
        ]);
        return new Response($html);
    }

    public function create(Request $request): Response
    {
        return new Response($this->renderForm([
            'title' => 'Create group',
            'group' => ['enabled' => 1, 'is_system' => 0],
            'error' => null,
        ]));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('admin_user_groups', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $data = $this->normalizeGroupPayload($request);
        if ($data['name'] === '' || $data['slug'] === '') {
            return new Response($this->renderForm([
                'title' => 'Create group',
                'group' => $data,
                'error' => 'Name and slug are required.',
            ]), 400);
        }

        $this->users->saveGroup(0, $data, $data['permissions']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users/groups?msg=created']);
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $group = $this->users->findGroup($id);
        if (!$group) {
            return new Response('Not found', 404);
        }

        $group['permissions'] = $this->users->permissionsForGroup($id);
        return new Response($this->renderForm([
            'title' => 'Edit group',
            'group' => $group,
            'error' => null,
        ]));
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('admin_user_groups', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $id = (int)($request->params['id'] ?? 0);
        $group = $this->users->findGroup($id);
        if (!$group) {
            return new Response('Not found', 404);
        }

        $data = $this->normalizeGroupPayload($request, $group);
        if ($data['name'] === '' || $data['slug'] === '') {
            return new Response($this->renderForm([
                'title' => 'Edit group',
                'group' => $data + ['id' => $id],
                'error' => 'Name and slug are required.',
            ]), 400);
        }

        $this->users->saveGroup($id, $data, $data['permissions']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users/groups?msg=updated']);
    }

    private function renderForm(array $data): string
    {
        return $this->container->get('renderer')->render('users/admin/group_form', [
            'title' => $data['title'],
            'group' => $data['group'],
            'error' => $data['error'],
            'csrf' => Csrf::token('admin_user_groups'),
            'capabilityOptions' => $this->access->capabilityOptions(),
        ]);
    }

    private function normalizeGroupPayload(Request $request, array $existing = []): array
    {
        $permissions = array_values(array_unique(array_filter(array_map('trim', (array)($request->body['permissions'] ?? [])))));

        return [
            'name' => trim((string)($request->body['name'] ?? '')),
            'slug' => $this->normalizeSlug((string)($request->body['slug'] ?? ($existing['slug'] ?? ''))),
            'description' => trim((string)($request->body['description'] ?? '')),
            'enabled' => !empty($request->body['enabled']) ? 1 : 0,
            'is_system' => !empty($existing['is_system']) ? 1 : (!empty($request->body['is_system']) ? 1 : 0),
            'permissions' => $permissions,
        ];
    }

    private function normalizeSlug(string $value): string
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
            'created' => 'Group created',
            'updated' => 'Group updated',
            default => null,
        };
    }
}
