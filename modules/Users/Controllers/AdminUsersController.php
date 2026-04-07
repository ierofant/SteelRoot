<?php
namespace Modules\Users\Controllers;

use App\Services\UserPublicSummaryService;
use Core\Container;
use Core\Csrf;
use Core\Request;
use Core\Response;
use Core\ModuleSettings;
use Modules\Users\Services\CommunityPollService;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\UserRepository;

class AdminUsersController
{
    private Container $container;
    private UserRepository $users;
    private ModuleSettings $moduleSettings;
    private UserAccessService $access;
    private CommunityPollService $communityPolls;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->users = $container->get(UserRepository::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->access = $container->get(UserAccessService::class);
        $this->communityPolls = $container->get(CommunityPollService::class);
    }

    public function index(Request $request): Response
    {
        $filters = [
            'email'  => trim($request->query['email'] ?? ''),
            'username' => trim($request->query['username'] ?? ''),
            'role'   => trim($request->query['role'] ?? ''),
            'status' => trim($request->query['status'] ?? ''),
            'group' => trim($request->query['group'] ?? ''),
        ];
        $sort = trim((string)($request->query['sort'] ?? 'created_at'));
        $dir = strtolower(trim((string)($request->query['dir'] ?? 'desc')));
        $page    = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 20;
        $total   = $this->users->count($filters);
        $list    = $this->users->list($filters, $perPage, ($page - 1) * $perPage, $sort, $dir);
        $html = $this->container->get('renderer')->render('users/admin/users_list', [
            'title'      => 'Users',
            'users'      => $list,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'csrf'       => Csrf::token('admin_users'),
            'blockToken' => Csrf::token('admin_users_block'),
            'resetToken' => Csrf::token('admin_users_reset'),
            'deleteToken' => Csrf::token('admin_users_delete'),
            'flash'      => $this->listMessageFromQuery((string)($request->query['msg'] ?? '')),
            'page'       => $page,
            'total'      => $total,
            'perPage'    => $perPage,
            'groupOptions' => $this->users->groupOptions(),
            'planOptions' => $this->users->listMasterPlans(true),
        ]);
        return new Response($html);
    }

    public function export(Request $request): Response
    {
        $filters = [
            'email'  => trim($request->query['email'] ?? ''),
            'username' => trim($request->query['username'] ?? ''),
            'role'   => trim($request->query['role'] ?? ''),
            'status' => trim($request->query['status'] ?? ''),
            'group' => trim($request->query['group'] ?? ''),
        ];
        $sort = trim((string)($request->query['sort'] ?? 'created_at'));
        $dir = strtolower(trim((string)($request->query['dir'] ?? 'desc')));
        $rows = $this->users->export($filters, $sort, $dir);

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return new Response('Unable to generate CSV', 500);
        }

        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['email', 'name'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, [
                (string)($row['email'] ?? ''),
                (string)($row['name'] ?? ''),
            ], ',', '"', '');
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return new Response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="users-export.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function create(Request $request): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_create', [
            'title' => 'Create User',
            'csrf' => Csrf::token('admin_users'),
            'error' => null,
            'visibilityOptions' => ['public', 'private'],
            'groupOptions' => $this->visibleGroupOptions(),
            'planOptions' => $this->users->listMasterPlans(true),
        ]);
        return new Response($html);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check('admin_users', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $role = $this->normalizeRole($request->body['role'] ?? 'user');
        $status = $this->normalizeStatus($request->body['status'] ?? 'active');
        $pass = (string)($request->body['password'] ?? '');
        $usernameInput = (string)($request->body['username'] ?? $name);
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? 'public'));
        $isMaster = !empty($request->body['is_master']) ? 1 : 0;
        $isVerified = !empty($request->body['is_verified']) ? 1 : 0;
        if ($isVerified) {
            $isMaster = 1;
        }
        if ($this->forbidRawExternalLinks() && $this->hasRawLinksInProfileText($request->body, (bool)$isMaster)) {
            return $this->renderCreateError((string)__('users.profile.error.raw_links'));
        }
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        $groupIds = array_map('intval', (array)($request->body['group_ids'] ?? []));
        $primaryGroupId = (int)($request->body['primary_group_id'] ?? 0);
        $planId = (int)($request->body['plan_id'] ?? 0);
        $planExpiresAt = trim((string)($request->body['plan_expires_at'] ?? ''));
        $planStatus = trim((string)($request->body['plan_status'] ?? 'active'));
        $planNote = trim((string)($request->body['plan_note'] ?? ''));
        if ($name === '' || $email === '' || $pass === '') {
            return $this->renderCreateError('All fields are required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderCreateError('Invalid email');
        }
        if (strlen($pass) < 8) {
            return $this->renderCreateError('Password must be at least 8 characters');
        }
        if ($this->users->emailExists($email)) {
            return $this->renderCreateError('Email already exists');
        }
        $username = $this->normalizeUsername($usernameInput ?: $name);
        if ($username === '' || strlen($username) < $this->usernameMin()) {
            return $this->renderCreateError('Username is too short or invalid');
        }
        if ($this->users->usernameExists($username)) {
            return $this->renderCreateError('Username already exists');
        }
        $id = $this->users->create($name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $status, null, $username, $visibility, $signature);
        $this->users->upsertProfile($id, [
            'is_master' => $isMaster,
            'is_verified' => $isVerified,
        ]);
        $this->container->get(UserPublicSummaryService::class)->invalidate($id);
        [$groupIds, $primaryGroupId] = $this->normalizeGroupSelection($groupIds, $primaryGroupId, $isMaster, $isVerified);
        $this->users->assignGroups($id, $groupIds, $primaryGroupId > 0 ? $primaryGroupId : null);
        if ($planId > 0) {
            $this->users->assignMasterPlan($id, $planId, $planExpiresAt !== '' ? $planExpiresAt . ' 23:59:59' : null, $planStatus, $this->currentAdminUserId(), $planNote);
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=created']);
    }

    public function settings(Request $request): Response
    {
        $settings = $this->moduleSettings->all('users');
        $defaults = $this->defaults();
        $data = array_merge($defaults, $settings);
        $html = $this->container->get('renderer')->render('users/admin/settings', [
            'title' => __('users.settings.title'),
            'csrf' => Csrf::token('users_settings'),
            'settings' => $data,
            'flash' => !empty($request->query['saved']) ? 'Saved' : null,
        ]);
        return new Response($html);
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check('users_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $map = [
            'registration_enabled' => 'users_registration_enabled',
            'email_verification_required' => 'users_email_verification_required',
            'auto_login_after_register' => 'users_auto_login_after_register',
            'default_role' => 'users_default_role',
            'email_blacklist' => 'users_email_blacklist',
            'email_domain_blacklist' => 'users_email_domain_blacklist',
            'email_domain_whitelist' => 'users_email_domain_whitelist',
            'username_min_length' => 'users_username_min_length',
            'username_max_length' => 'users_username_max_length',
            'password_min_length' => 'users_password_min_length',
            'password_require_numbers' => 'users_password_require_numbers',
            'password_require_special' => 'users_password_require_special',
            'registration_rate_limit' => 'users_registration_rate_limit',
            'blocked_ips' => 'users_blocked_ips',
            'extended_profiles_enabled' => 'users_extended_profiles_enabled',
            'groups_enabled' => 'users_groups_enabled',
            'master_profiles_enabled' => 'users_master_profiles_enabled',
            'master_uploads_enabled' => 'users_master_uploads_enabled',
            'verified_masters_only_upload' => 'users_verified_masters_only_upload',
            'favorites_enabled' => 'users_favorites_enabled',
            'ratings_enabled' => 'users_ratings_enabled',
            'reviews_enabled' => 'users_reviews_enabled',
            'profile_comments_enabled' => 'users_profile_comments_enabled',
            'cover_enabled' => 'users_cover_enabled',
            'external_links_enabled' => 'users_external_links_enabled',
            'contacts_enabled' => 'users_contacts_enabled',
            'master_contact_enabled' => 'users_master_contact_enabled',
            'master_gallery_moderation' => 'users_master_gallery_moderation',
            'reviews_require_moderation' => 'users_reviews_require_moderation',
            'links_require_approval' => 'users_links_require_approval',
            'master_verification_required' => 'users_master_verification_required',
            'master_plans_enabled' => 'users_master_plans_enabled',
            'manual_plan_assignment_only' => 'users_manual_plan_assignment_only',
            'allowed_social_platforms' => 'users_allowed_social_platforms',
            'master_contact_bot_username' => 'users_master_contact_bot_username',
            'forbid_raw_external_links' => 'users_forbid_raw_external_links',
            'username_change_disabled' => 'users_username_change_disabled',
        ];
        foreach ($map as $key => $field) {
            $val = $request->body[$field] ?? null;
            if (in_array($key, ['registration_enabled','email_verification_required','auto_login_after_register','password_require_numbers','password_require_special','extended_profiles_enabled','groups_enabled','master_profiles_enabled','master_uploads_enabled','verified_masters_only_upload','favorites_enabled','ratings_enabled','reviews_enabled','profile_comments_enabled','cover_enabled','external_links_enabled','contacts_enabled','master_contact_enabled','master_gallery_moderation','reviews_require_moderation','links_require_approval','master_verification_required','master_plans_enabled','manual_plan_assignment_only','forbid_raw_external_links','username_change_disabled'], true)) {
                $val = !empty($val) ? 1 : 0;
            } elseif (in_array($key, ['username_min_length','username_max_length','password_min_length','registration_rate_limit'], true)) {
                $val = (int)$val;
            } else {
                $val = (string)$val;
            }
            $this->moduleSettings->set('users', $key, $val);
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/users/settings?saved=1']);
    }

    public function communityPoll(Request $request): Response
    {
        $audience = trim((string)($request->query['audience'] ?? 'all'));
        $report = $this->communityPolls->adminReport($audience);
        $html = $this->container->get('renderer')->render('users/admin/community_poll', [
            'title' => __('users.community_poll.admin.title'),
            'csrf' => Csrf::token('users_community_poll_settings'),
            'report' => $report,
            'flash' => !empty($request->query['saved']) ? __('users.community_poll.admin.saved') : null,
        ]);
        return new Response($html);
    }

    public function saveCommunityPoll(Request $request): Response
    {
        if (!Csrf::check('users_community_poll_settings', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $this->communityPolls->saveSurveySettings($request->body);

        return new Response('', 302, ['Location' => $this->prefix() . '/users/community-poll?saved=1']);
    }

    private function defaults(): array
    {
        return [
            'registration_enabled' => 1,
            'email_verification_required' => 0,
            'auto_login_after_register' => 0,
            'default_role' => 'user',
            'email_blacklist' => '',
            'email_domain_blacklist' => '',
            'email_domain_whitelist' => '',
            'username_min_length' => 3,
            'username_max_length' => 32,
            'password_min_length' => 8,
            'password_require_numbers' => 0,
            'password_require_special' => 0,
            'registration_rate_limit' => 5,
            'blocked_ips' => '',
            'extended_profiles_enabled' => 1,
            'groups_enabled' => 1,
            'master_profiles_enabled' => 1,
            'master_uploads_enabled' => 1,
            'verified_masters_only_upload' => 1,
            'favorites_enabled' => 1,
            'ratings_enabled' => 1,
            'reviews_enabled' => 1,
            'profile_comments_enabled' => 1,
            'cover_enabled' => 1,
            'external_links_enabled' => 1,
            'contacts_enabled' => 1,
            'master_contact_enabled' => 1,
            'master_gallery_moderation' => 1,
            'reviews_require_moderation' => 1,
            'links_require_approval' => 1,
            'master_verification_required' => 0,
            'master_plans_enabled' => 1,
            'manual_plan_assignment_only' => 1,
            'allowed_social_platforms' => 'telegram,vk,instagram,youtube,tiktok,whatsapp',
            'master_contact_bot_username' => '',
            'forbid_raw_external_links' => 1,
            'username_change_disabled' => 1,
        ];
    }

    public function edit(Request $request): Response
    {
        $id = (int)($request->params['id'] ?? 0);
        $user = $this->users->find($id);
        if (!$user) {
            return new Response('Not found', 404);
        }
        $html = $this->container->get('renderer')->render('users/admin/user_edit', [
            'title' => 'Edit User',
            'user' => $this->users->findFull($id) ?? $user,
            'csrf' => Csrf::token('admin_users'),
            'deleteToken' => Csrf::token('admin_users_delete'),
            'error' => null,
            'flash' => $this->editMessageFromQuery((string)($request->query['msg'] ?? '')),
            'visibilityOptions' => ['public', 'private'],
            'groupOptions' => $this->visibleGroupOptions(),
            'planOptions' => $this->users->listMasterPlans(true),
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        if (!Csrf::check('admin_users', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $user = $this->users->find($id);
        if (!$user) {
            return new Response('Not found', 404);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $role = $this->normalizeRole($request->body['role'] ?? 'user');
        $status = $this->normalizeStatus($request->body['status'] ?? 'active');
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        $usernameInput = (string)($request->body['username'] ?? ($user['username'] ?? $user['name'] ?? ''));
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? ($user['profile_visibility'] ?? 'public')));
        $isMaster = !empty($request->body['is_master']) ? 1 : 0;
        $isVerified = !empty($request->body['is_verified']) ? 1 : 0;
        if ($isVerified) {
            $isMaster = 1;
        }
        if ($this->forbidRawExternalLinks() && $this->hasRawLinksInProfileText($request->body, (bool)$isMaster)) {
            return $this->renderEditError($user, (string)__('users.profile.error.raw_links'));
        }
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        $groupIds = array_map('intval', (array)($request->body['group_ids'] ?? []));
        $primaryGroupId = (int)($request->body['primary_group_id'] ?? 0);
        $planId = (int)($request->body['plan_id'] ?? 0);
        $planExpiresAt = trim((string)($request->body['plan_expires_at'] ?? ''));
        $planStatus = trim((string)($request->body['plan_status'] ?? 'active'));
        $planNote = trim((string)($request->body['plan_note'] ?? ''));
        if ($name === '' || $email === '') {
            return $this->renderEditError($user, 'Name and email required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->renderEditError($user, 'Invalid email');
        }
        if ($this->users->emailExists($email, $id)) {
            return $this->renderEditError($user, 'Email already exists');
        }
        $username = $this->normalizeUsername($usernameInput);
        if ($username === '' || strlen($username) < $this->usernameMin()) {
            return $this->renderEditError($user, 'Username is too short or invalid');
        }
        if ($this->users->usernameExists($username, $id)) {
            return $this->renderEditError($user, 'Username already exists');
        }
        $data = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'username' => $username,
            'profile_visibility' => $visibility,
            'signature' => $signature,
        ];
        $this->users->update($id, $data);
        $this->users->upsertProfile($id, [
            'is_master' => $isMaster,
            'is_verified' => $isVerified,
        ]);
        $this->container->get(UserPublicSummaryService::class)->invalidate($id);
        [$groupIds, $primaryGroupId] = $this->normalizeGroupSelection($groupIds, $primaryGroupId, $isMaster, $isVerified);
        $this->users->assignGroups($id, $groupIds, $primaryGroupId > 0 ? $primaryGroupId : null);
        $this->users->assignMasterPlan($id, $planId > 0 ? $planId : null, $planExpiresAt !== '' ? $planExpiresAt . ' 23:59:59' : null, $planStatus, $this->currentAdminUserId(), $planNote);
        if ($pass !== '') {
            if ($pass !== $pass2) {
                return $this->renderEditError($this->users->find($id) ?? $user, 'Passwords do not match');
            }
            if (strlen($pass) < 8) {
                return $this->renderEditError($this->users->find($id) ?? $user, 'Password must be at least 8 characters');
            }
            $this->users->setPassword($id, password_hash($pass, PASSWORD_DEFAULT));
        }
        return new Response('', 302, ['Location' => $this->prefix() . '/users/edit/' . $id . '?msg=updated']);
    }

    public function block(Request $request): Response
    {
        if (!Csrf::check('admin_users_block', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->users->update($id, ['status' => 'blocked']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=blocked']);
    }

    public function unblock(Request $request): Response
    {
        if (!Csrf::check('admin_users_block', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $this->users->update($id, ['status' => 'active']);
        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=unblocked']);
    }

    public function delete(Request $request): Response
    {
        if (!Csrf::check('admin_users_delete', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $id = (int)($request->params['id'] ?? 0);
        if ($id < 1) {
            return new Response('Invalid user', 400);
        }

        if ($this->currentAdminUserId() === $id) {
            return new Response('', 302, ['Location' => $this->prefix() . '/users/edit/' . $id . '?msg=cannot-delete-self']);
        }

        $user = $this->users->find($id);
        if (!$user) {
            return new Response('Not found', 404);
        }

        $this->users->deleteUser($id);
        $this->container->get(UserPublicSummaryService::class)->invalidate($id);

        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=deleted']);
    }

    public function bulkDelete(Request $request): Response
    {
        if (!Csrf::check('admin_users_delete', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($request->body['ids'] ?? [])), static function (int $id): bool {
            return $id > 0;
        })));

        if ($ids === []) {
            return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=bulk-delete-empty']);
        }

        $currentAdminId = $this->currentAdminUserId();
        $selfSkipped = false;

        foreach ($ids as $id) {
            if ($currentAdminId !== null && $currentAdminId === $id) {
                $selfSkipped = true;
                continue;
            }

            $user = $this->users->find($id);
            if (!$user) {
                continue;
            }

            $this->users->deleteUser($id);
            $this->container->get(UserPublicSummaryService::class)->invalidate($id);
        }

        $message = $selfSkipped ? 'bulk-delete-self-skipped' : 'bulk-deleted';

        return new Response('', 302, ['Location' => $this->prefix() . '/users?msg=' . $message]);
    }

    public function resetPassword(Request $request): Response
    {
        if (!Csrf::check('admin_users_reset', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        $newPass = bin2hex(random_bytes(6));
        $this->users->setPassword($id, password_hash($newPass, PASSWORD_DEFAULT));
        return new Response('', 302, ['Location' => $this->prefix() . '/users/edit/' . $id . '?msg=Password:' . urlencode($newPass)]);
    }

    private function renderCreateError(string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_create', [
            'title' => 'Create User',
            'csrf' => Csrf::token('admin_users'),
            'error' => $msg,
            'visibilityOptions' => ['public', 'private'],
            'groupOptions' => $this->visibleGroupOptions(),
            'planOptions' => $this->users->listMasterPlans(true),
        ]);
        return new Response($html, 400);
    }

    private function currentAdminUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    private function renderEditError(array $user, string $msg): Response
    {
        $html = $this->container->get('renderer')->render('users/admin/user_edit', [
            'title' => 'Edit User',
            'user' => $user,
            'csrf' => Csrf::token('admin_users'),
            'error' => $msg,
            'flash' => null,
            'visibilityOptions' => ['public', 'private'],
            'groupOptions' => $this->visibleGroupOptions(),
            'planOptions' => $this->users->listMasterPlans(true),
        ]);
        return new Response($html, 400);
    }

    private function visibleGroupOptions(): array
    {
        return $this->users->groupOptions();
    }

    private function normalizeGroupSelection(array $groupIds, int $primaryGroupId, int $isMaster, int $isVerified): array
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static function (int $id): bool {
            return $id > 0;
        })));

        $systemGroupIds = [];
        foreach ($this->users->groupOptions() as $group) {
            $groupId = (int)($group['id'] ?? 0);
            $slug = (string)($group['slug'] ?? '');
            if ($groupId > 0 && in_array($slug, self::SYSTEM_MASTER_GROUP_SLUGS, true)) {
                $systemGroupIds[$slug] = $groupId;
            }
        }

        if ($isMaster && !empty($systemGroupIds['master'])) {
            $groupIds[] = (int)$systemGroupIds['master'];
        }

        if ($isVerified && !empty($systemGroupIds['verified_master'])) {
            $groupIds[] = (int)$systemGroupIds['verified_master'];
        }

        $groupIds = array_values(array_unique($groupIds));

        return [$groupIds, $primaryGroupId];
    }

    private function normalizeRole(string $role): string
    {
        $allowed = ['user', 'editor', 'admin'];
        $role = strtolower(trim($role));
        return in_array($role, $allowed, true) ? $role : 'user';
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['active', 'blocked', 'pending'];
        $status = strtolower(trim($status));
        return in_array($status, $allowed, true) ? $status : 'active';
    }

    private function normalizeVisibility(string $raw): string
    {
        return $raw === 'private' ? 'private' : 'public';
    }

    private function prefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }

    private function sanitizeSignature($value): ?string
    {
        $plain = trim((string)$value);
        if ($plain === '') {
            return null;
        }
        $plain = strip_tags($plain);
        $plain = preg_replace('~(?:https?://|www\.)\S+~iu', ' ', $plain);
        $plain = preg_replace('~(?<!@)\b[\p{L}\p{N}][\p{L}\p{N}\-._]*\.[a-z]{2,}(?:/[^\s]*)?~iu', ' ', $plain);
        $plain = preg_replace('/\\s+/', ' ', $plain);
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        if (mb_strlen($plain) > 300) {
            $plain = mb_substr($plain, 0, 300);
        }
        return $plain;
    }

    private function hasRawLinksInProfileText(array $body, bool $isMaster): bool
    {
        $fields = [
            'signature',
            'display_name',
            'bio',
            'specialization',
            'styles',
            'city',
            'studio_name',
            'contacts_text',
            'photo_copyright_text',
        ];
        if ($isMaster) {
            $fields[] = 'artist_note';
        }

        foreach ($fields as $field) {
            if ($this->containsRawLink((string)($body[$field] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function containsRawLink(string $value): bool
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return false;
        }

        return (bool)preg_match(
            '~(?:https?://|www\.)\S+|(?<!@)\b[\p{L}\p{N}][\p{L}\p{N}\-._]*\.[a-z]{2,}(?:/[^\s]*)?~iu',
            $value
        );
    }

    private function forbidRawExternalLinks(): bool
    {
        $settings = $this->moduleSettings->all('users');
        return array_key_exists('forbid_raw_external_links', $settings)
            ? !empty($settings['forbid_raw_external_links'])
            : true;
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9_.\\-]+/', '', $value);
        $value = trim($value, '-_.');
        $max = $this->usernameMax();
        if ($max > 0 && strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }
        if (ctype_digit($value)) {
            $value = 'u' . $value;
        }
        return $value;
    }

    private function usernameMin(): int
    {
        $settings = $this->moduleSettings->all('users');
        $min = (int)($settings['username_min_length'] ?? 3);
        return $min > 0 ? $min : 3;
    }

    private function usernameMax(): int
    {
        $settings = $this->moduleSettings->all('users');
        $min = $this->usernameMin();
        $max = (int)($settings['username_max_length'] ?? 32);
        if ($max < $min) {
            $max = $min;
        }
        return $max;
    }

    private function listMessageFromQuery(string $code): ?string
    {
        return match ($code) {
            'created' => 'User created',
            'blocked' => 'User blocked',
            'unblocked' => 'User unblocked',
            'deleted' => 'User deleted',
            'bulk-deleted' => 'Selected users deleted',
            'bulk-delete-empty' => 'Select at least one user to delete',
            'bulk-delete-self-skipped' => 'Selected users deleted, current admin was skipped',
            default => null,
        };
    }

    private function editMessageFromQuery(string $code): ?string
    {
        if ($code === 'updated') {
            return 'User updated';
        }

        if (str_starts_with($code, 'Password:')) {
            return $code;
        }

        if ($code === 'cannot-delete-self') {
            return 'You cannot delete your own account';
        }

        return null;
    }
}
