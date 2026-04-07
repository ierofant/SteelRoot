<?php
declare(strict_types=1);

namespace Modules\Users\Controllers;

use Core\Container;
use Core\Csrf;
use Core\ModuleSettings;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\MasterContactService;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\UserRepository;

class MasterContactController
{
    private Container $container;
    private Auth $auth;
    private UserRepository $users;
    private MasterContactService $contacts;
    private UserAccessService $access;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
        $this->contacts = $container->get(MasterContactService::class);
        $this->access = $container->get(UserAccessService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
    }

    public function form(Request $request): Response
    {
        $master = $this->findMaster((string)($request->params['id'] ?? ''));
        if (!$master) {
            return new Response('Not found', 404);
        }

        $availability = $this->contacts->publicAvailability($master);
        if (empty($availability['available'])) {
            return new Response('Not found', 404);
        }

        $viewer = $this->auth->user();
        $prefillName = trim((string)($viewer['display_name'] ?? ($viewer['name'] ?? '')));
        $prefillContact = trim((string)($viewer['email'] ?? ''));
        $html = $this->container->get('renderer')->render('users/master_contact_form', [
            '_layout' => true,
            'title' => __('users.master_contact.form.title'),
            'master' => $master,
            'contactToken' => Csrf::token('master_contact_form'),
            'message' => (string)($request->query['msg'] ?? ''),
            'error' => (string)($request->query['err'] ?? ''),
            'prefillName' => $prefillName,
            'prefillContact' => $prefillContact,
            'breadcrumbs' => [
                ['label' => __('users.directory.title'), 'url' => '/users'],
                ['label' => (string)($master['display_name'] ?? ($master['name'] ?? '')), 'url' => '/users/' . rawurlencode((string)($master['username'] ?? $master['id']))],
                ['label' => __('users.master_contact.public.cta')],
            ],
        ], [
            'title' => __('users.master_contact.form.title') . ' · ' . (string)($master['display_name'] ?? ($master['name'] ?? '')),
        ]);

        return new Response($html);
    }

    public function submit(Request $request): Response
    {
        if (!Csrf::check('master_contact_form', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $master = $this->findMaster((string)($request->params['id'] ?? ''));
        if (!$master) {
            return new Response('Not found', 404);
        }

        try {
            $viewer = $this->auth->user();
            $requestId = $this->contacts->createRequest((int)$master['id'], $request->body, $request->files, $request->server, $master, (int)($viewer['id'] ?? 0));
        } catch (\RuntimeException $e) {
            $error = match ($e->getMessage()) {
                'contact-unavailable' => 'users.master_contact.flash.unavailable',
                'contact-rate-limit' => 'users.master_contact.flash.rate_limit',
                'contact-files-invalid' => 'users.master_contact.flash.files_invalid',
                default => 'users.master_contact.flash.invalid',
            };
            return new Response('', 302, ['Location' => $this->contactUrl($master) . '?err=' . urlencode((string)__($error))]);
        }

        $contactSettings = $this->contacts->settingsForMaster((int)$master['id'], $master);
        $msg = !empty($settings['auto_reply_text']) ? (string)$settings['auto_reply_text'] : (string)__('users.master_contact.flash.sent');
        if (!empty($viewer['id'])) {
            return new Response('', 302, ['Location' => '/profile/my-requests/' . $requestId . '?msg=' . urlencode($msg)]);
        }

        return new Response('', 302, ['Location' => $this->contactUrl($master) . '?msg=' . urlencode($msg) . '&request=' . $requestId]);
    }

    public function inbox(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $master = $this->users->findFull((int)$user['id']) ?? $user;
        if (!$this->contacts->isEligibleMaster($master)) {
            return new Response('Forbidden', 403);
        }

        $status = strtolower(trim((string)($request->query['status'] ?? '')));
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $total = $this->contacts->countForMaster((int)$master['id'], $status);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $items = $this->contacts->listForMaster((int)$master['id'], $perPage, ($page - 1) * $perPage, $status);
        $selectedId = max(0, (int)($request->params['id'] ?? ($request->query['request'] ?? 0)));
        if ($selectedId < 1 && $items !== []) {
            $selectedId = (int)($items[0]['id'] ?? 0);
        }
        $selected = $selectedId > 0 ? $this->contacts->findForMaster((int)$master['id'], $selectedId, true) : null;
        $contactSettings = $this->contacts->settingsForMaster((int)$master['id'], $master);

        $html = $this->container->get('renderer')->render('users/master_requests', [
            '_layout' => true,
            'title' => __('users.master_contact.inbox.title'),
            'user' => $master,
            'master' => $master,
            'items' => $items,
            'selected' => $selected,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'currentStatus' => $status,
            'statuses' => $this->contacts->statuses(),
            'statusToken' => Csrf::token('master_contact_status'),
            'settingsToken' => Csrf::token('master_contact_settings'),
            'telegramBindToken' => Csrf::token('master_contact_telegram_bind'),
            'telegramUnbindToken' => Csrf::token('master_contact_telegram_unbind'),
            'contactSettings' => $contactSettings,
            'groups' => $this->access->groupsForUser((int)$master['id']),
            'canManageMasterWorks' => $this->canManageMasterWorks($master),
            'activeTab' => 'master_requests',
            'message' => (string)($request->query['msg'] ?? ''),
            'error' => (string)($request->query['err'] ?? ''),
        ], [
            'title' => __('users.master_contact.inbox.title'),
        ]);

        return new Response($html);
    }

    public function clientInbox(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $status = strtolower(trim((string)($request->query['status'] ?? '')));
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $total = $this->contacts->countForRequester((int)$user['id'], $status);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $items = $this->contacts->listForRequester((int)$user['id'], $perPage, ($page - 1) * $perPage, $status);
        $selectedId = max(0, (int)($request->params['id'] ?? ($request->query['request'] ?? 0)));
        if ($selectedId < 1 && $items !== []) {
            $selectedId = (int)($items[0]['id'] ?? 0);
        }
        $selected = $selectedId > 0 ? $this->contacts->findForRequester((int)$user['id'], $selectedId) : null;

        $html = $this->container->get('renderer')->render('users/client_requests', [
            '_layout' => true,
            'title' => __('users.master_contact.client.title'),
            'user' => $user,
            'items' => $items,
            'selected' => $selected,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'currentStatus' => $status,
            'statuses' => $this->contacts->statuses(),
            'groups' => $this->access->groupsForUser((int)$user['id']),
            'canManageMasterWorks' => $this->canManageMasterWorks($user),
            'activeTab' => 'my_requests',
            'message' => (string)($request->query['msg'] ?? ''),
            'error' => (string)($request->query['err'] ?? ''),
        ], [
            'title' => __('users.master_contact.client.title'),
        ]);

        return new Response($html);
    }

    public function updateStatus(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('master_contact_status', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $requestId = (int)($request->params['id'] ?? 0);
        $status = strtolower(trim((string)($request->body['status'] ?? '')));
        $ok = $this->contacts->updateStatus((int)$user['id'], $requestId, $status);
        return new Response('', 302, ['Location' => '/profile/master-requests/' . $requestId . ($ok ? '?msg=status' : '?err=status')]);
    }

    public function updateSettings(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('master_contact_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $master = $this->users->findFull((int)$user['id']) ?? $user;
        if (!$this->contacts->isEligibleMaster($master)) {
            return new Response('Forbidden', 403);
        }

        $this->contacts->updateSettings((int)$master['id'], $request->body, $master);
        return new Response('', 302, ['Location' => '/profile/master-requests?msg=settings']);
    }

    public function telegramBind(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('master_contact_telegram_bind', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $master = $this->users->findFull((int)$user['id']) ?? $user;
        $link = $this->contacts->createTelegramBindLink((int)$master['id'], $master);
        if ($link === null) {
            return new Response('', 302, ['Location' => '/profile/master-requests?err=telegram']);
        }

        return new Response('', 302, ['Location' => $link]);
    }

    public function telegramUnbind(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('master_contact_telegram_unbind', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $master = $this->users->findFull((int)$user['id']) ?? $user;
        if (!$this->contacts->isEligibleMaster($master)) {
            return new Response('Forbidden', 403);
        }

        $this->contacts->unbindTelegram((int)$master['id']);
        return new Response('', 302, ['Location' => '/profile/master-requests?msg=settings']);
    }

    public function file(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $file = $this->contacts->findFileForMaster((int)$user['id'], (int)($request->params['id'] ?? 0));
        if (!$file) {
            return new Response('Not found', 404);
        }

        $path = (string)($file['absolute_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return new Response('Not found', 404);
        }

        $body = (string)file_get_contents($path);
        return new Response($body, 200, [
            'Content-Type' => (string)($file['mime_type'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="' . addslashes((string)($file['original_name'] ?? 'reference.jpg')) . '"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function clientFile(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }

        $file = $this->contacts->findFileForRequester((int)$user['id'], (int)($request->params['id'] ?? 0));
        if (!$file) {
            return new Response('Not found', 404);
        }

        $path = (string)($file['absolute_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return new Response('Not found', 404);
        }

        $body = (string)file_get_contents($path);
        return new Response($body, 200, [
            'Content-Type' => (string)($file['mime_type'] ?? 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="' . addslashes((string)($file['original_name'] ?? 'reference.jpg')) . '"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function findMaster(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $user = ctype_digit($identifier) ? $this->users->findFull((int)$identifier) : $this->users->findByUsername($identifier);
        if (!$user) {
            return null;
        }
        $full = $this->users->findFull((int)($user['id'] ?? 0)) ?? $user;
        return !empty($full['is_master']) ? $full : null;
    }

    private function contactUrl(array $master): string
    {
        return '/users/' . rawurlencode((string)($master['username'] ?? $master['id'])) . '/contact';
    }

    private function canManageMasterWorks(array $user): bool
    {
        $settings = (array)$this->moduleSettings->all('Users');
        $masterUploadsEnabled = array_key_exists('master_uploads_enabled', $settings) ? !empty($settings['master_uploads_enabled']) : true;
        $verifiedOnly = array_key_exists('verified_masters_only_upload', $settings) ? !empty($settings['verified_masters_only_upload']) : true;
        if (!$masterUploadsEnabled || empty($user['is_master'])) {
            return false;
        }
        if ($verifiedOnly && empty($user['is_verified'])) {
            return false;
        }
        return true;
    }
}
