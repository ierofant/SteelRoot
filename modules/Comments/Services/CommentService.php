<?php
declare(strict_types=1);

namespace Modules\Comments\Services;

use Core\Cache;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Logger;
use Core\ModuleSettings;
use Core\RateLimiter;
use Core\Request;
use Core\Response;
use Core\Slot;
use Modules\Users\Services\Auth;

class CommentService
{
    private Container $container;
    private Database $db;
    private ModuleSettings $moduleSettings;
    private Cache $cache;
    private array $defaults;
    private EntityCommentPolicyService $policyService;
    private array $tableExists = [];
    private static bool $publicAssetsRegistered = false;
    private static bool $adminAssetsRegistered = false;

    private const MODULE = 'comments';
    private const SETTINGS_CSRF = 'comments_settings';
    private const MODERATE_CSRF = 'comments_admin';
    private const FORM_CSRF = 'comments_form';

    public function __construct(Container $container, string $configPath)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->cache = $container->get('cache');
        $this->policyService = $container->get(EntityCommentPolicyService::class);
        $this->defaults = require $configPath;
        $this->moduleSettings->loadDefaults(self::MODULE, $this->defaults);
    }

    public function renderForEntity(string $entityType, int $entityId, array $options = []): string
    {
        if ($entityId < 1 || !$this->isEntityEnabled($entityType)) {
            return '';
        }
        if (!$this->entityExists($entityType, $entityId)) {
            return '';
        }

        self::registerPublicAssets();
        $settings = $this->settings();
        $page = max(1, (int)($_GET['comments_page'] ?? 1));
        $thread = $this->thread($entityType, $entityId, $page, $settings);
        $state = $this->pullFormState($entityType, $entityId);
        $currentUser = $this->currentUser();
        $access = $this->policyService->evaluate(
            $entityType,
            $entityId,
            $currentUser,
            !empty($settings['allow_guests']),
            false
        );

        return $this->container->get('renderer')->render('comments/block', [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'comments' => $thread['items'],
            'commentCount' => $thread['count'],
            'page' => $page,
            'totalPages' => $thread['pages'],
            'maxDepth' => (int)$settings['max_depth'],
            'csrf' => !empty($access['can_post']) ? Csrf::token(self::FORM_CSRF) : '',
            'currentUser' => $currentUser,
            'currentUrl' => $options['currentUrl'] ?? $this->currentUrl(),
            'state' => $state,
            'settings' => $settings,
            'canPost' => !empty($access['can_post']),
            'postingMessage' => (string)($access['message'] ?? ''),
            'showLoginLink' => !empty($access['show_login_link']),
            'title' => $options['title'] ?? __('comments.block.title'),
        ]);
    }

    public function store(Request $request): Response
    {
        if (!Csrf::check(self::FORM_CSRF, $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $entityType = $this->normalizeEntityType((string)($request->body['entity_type'] ?? ''));
        $entityId = (int)($request->body['entity_id'] ?? 0);
        $returnTo = $this->sanitizeReturnTo((string)($request->body['return_to'] ?? $this->currentPath()));
        $settings = $this->settings();

        if (!$this->isEntityEnabled($entityType) || !$this->entityExists($entityType, $entityId)) {
            $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.invalid_target')]);
            return $this->redirect($returnTo . '#comments');
        }
        $currentUser = $this->currentUser();
        $access = $this->policyService->evaluate(
            $entityType,
            $entityId,
            $currentUser,
            !empty($settings['allow_guests']),
            false
        );
        if (empty($access['can_post'])) {
            $this->pushFormState($entityType, $entityId, ['error' => (string)($access['message'] ?? __('comments.flash.login_required'))]);
            if (!empty($access['show_login_link'])) {
                return $this->redirect('/login');
            }
            return $this->redirect($returnTo . '#comments');
        }

        $body = $this->normalizeBody((string)($request->body['body'] ?? ''));
        $parentId = (int)($request->body['parent_id'] ?? 0);
        $guestName = trim((string)($request->body['guest_name'] ?? ''));
        $guestEmail = trim((string)($request->body['guest_email'] ?? ''));
        $startedAt = (int)($request->body['form_started'] ?? 0);
        $honeypot = trim((string)($request->body['website'] ?? ''));
        $ip = $this->clientIp($request);
        $userAgent = substr((string)($request->headers['user-agent'] ?? ($request->server['HTTP_USER_AGENT'] ?? '')), 0, 255);

        $state = [
            'error' => null,
            'old' => [
                'body' => $body,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'parent_id' => $parentId,
            ],
        ];

        $minLength = max(1, (int)$settings['min_length']);
        $maxLength = max($minLength, (int)$settings['max_length']);
        $bodyLength = mb_strlen($body);
        if ($bodyLength < $minLength || $bodyLength > $maxLength) {
            $state['error'] = __('comments.flash.invalid_length');
            $this->pushFormState($entityType, $entityId, $state);
            return $this->redirect($returnTo . '#comments');
        }

        if (!$currentUser) {
            if ($guestName === '') {
                $state['error'] = __('comments.flash.name_required');
                $this->pushFormState($entityType, $entityId, $state);
                return $this->redirect($returnTo . '#comments');
            }
            if (!empty($settings['require_guest_email']) && !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                $state['error'] = __('comments.flash.email_required');
                $this->pushFormState($entityType, $entityId, $state);
                return $this->redirect($returnTo . '#comments');
            }
        }

        if ($this->tooManyAttempts($ip, $settings)) {
            $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.rate_limited')]);
            return $this->redirect($returnTo . '#comments');
        }

        $parent = null;
        $depth = 0;
        $rootId = null;
        if ($parentId > 0) {
            $parent = $this->db->fetch(
                "SELECT id, entity_type, entity_id, depth, root_id, status FROM comments WHERE id = ? LIMIT 1",
                [$parentId]
            );
            if (!$parent || $parent['entity_type'] !== $entityType || (int)$parent['entity_id'] !== $entityId) {
                $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.invalid_parent')]);
                return $this->redirect($returnTo . '#comments');
            }
            if (($parent['status'] ?? '') !== 'approved') {
                $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.invalid_parent')]);
                return $this->redirect($returnTo . '#comments');
            }
            $depth = (int)$parent['depth'] + 1;
            $rootId = (int)($parent['root_id'] ?: $parent['id']);
            if ($depth >= (int)$settings['max_depth']) {
                $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.max_depth')]);
                return $this->redirect($returnTo . '#comments');
            }
        }

        $spam = $this->detectSpam($body, $settings, $honeypot, $startedAt, $ip);
        if ($spam['blocked']) {
            $this->logSuspicious($entityType, $entityId, $ip, $userAgent, $spam['reason']);
            $this->pushFormState($entityType, $entityId, ['error' => __('comments.flash.rejected')]);
            return $this->redirect($returnTo . '#comments');
        }

        $status = $this->resolveStatus($spam['moderate'], $currentUser, $settings);
        $now = date('Y-m-d H:i:s');
        $approvedAt = $status === 'approved' ? $now : null;

        $this->db->execute(
            "INSERT INTO comments (
                entity_type, entity_id, parent_id, root_id, depth, user_id,
                guest_name, guest_email, body, status, ip, user_agent,
                created_at, updated_at, approved_at
            ) VALUES (
                :entity_type, :entity_id, :parent_id, :root_id, :depth, :user_id,
                :guest_name, :guest_email, :body, :status, :ip, :user_agent,
                NOW(), NOW(), :approved_at
            )",
            [
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':parent_id' => $parentId ?: null,
                ':root_id' => $rootId,
                ':depth' => $depth,
                ':user_id' => $currentUser['id'] ?? null,
                ':guest_name' => $currentUser ? null : $guestName,
                ':guest_email' => $currentUser ? null : ($guestEmail !== '' ? $guestEmail : null),
                ':body' => $body,
                ':status' => $status,
                ':ip' => $ip,
                ':user_agent' => $userAgent !== '' ? $userAgent : null,
                ':approved_at' => $approvedAt,
            ]
        );

        $commentId = (int)$this->db->pdo()->lastInsertId();
        if ($rootId === null && $commentId > 0) {
            $this->db->execute("UPDATE comments SET root_id = ? WHERE id = ?", [$commentId, $commentId]);
        }

        $this->rememberSubmissionHash($entityType, $entityId, $parentId, $body);
        $this->clearEntityCache($entityType, $entityId);
        $this->markAttempts($ip, $settings);

        $this->pushFormState($entityType, $entityId, [
            'success' => $status === 'approved' ? __('comments.flash.posted') : __('comments.flash.pending'),
        ]);

        return $this->redirect($returnTo . '#comments');
    }

    public function renderAdminIndex(Request $request): Response
    {
        self::registerAdminAssets();
        $filters = [
            'status' => trim((string)($request->query['status'] ?? '')),
            'entity_type' => $this->normalizeEntityType((string)($request->query['entity_type'] ?? '')),
            'q' => trim((string)($request->query['q'] ?? '')),
            'date_from' => trim((string)($request->query['date_from'] ?? '')),
            'date_to' => trim((string)($request->query['date_to'] ?? '')),
        ];
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 30;
        [$rows, $total] = $this->adminRows($filters, $page, $perPage);

        $html = $this->container->get('renderer')->render('comments/admin/index', [
            'title' => __('comments.admin.title'),
            'comments' => $rows,
            'filters' => $filters,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'csrf' => Csrf::token(self::MODERATE_CSRF),
            'adminPrefix' => $this->adminPrefix(),
            'entityOptions' => $this->entityLabels(),
            'flash' => !empty($request->query['saved']) ? 'Saved' : null,
        ]);

        return new Response($html);
    }

    public function renderSettings(Request $request): Response
    {
        self::registerAdminAssets();
        $html = $this->container->get('renderer')->render('comments/admin/settings', [
            'title' => __('comments.settings.title'),
            'settings' => $this->settings(),
            'csrf' => Csrf::token(self::SETTINGS_CSRF),
            'entityOptions' => $this->entityLabels(),
            'adminPrefix' => $this->adminPrefix(),
            'flash' => !empty($request->query['saved']) ? 'Saved' : null,
        ]);

        return new Response($html);
    }

    public function pendingModerationCount(): int
    {
        try {
            $row = $this->db->fetch("SELECT COUNT(*) AS cnt FROM comments WHERE status = 'pending'");
            return (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function saveSettings(Request $request): Response
    {
        if (!Csrf::check(self::SETTINGS_CSRF, $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }

        $body = $request->body;
        $enabledEntityTypes = array_values(array_filter(array_map(
            fn(string $value): string => $this->normalizeEntityType($value),
            (array)($body['enabled_entity_types'] ?? [])
        )));
        if ($enabledEntityTypes === []) {
            $enabledEntityTypes = $this->defaults['enabled_entity_types'];
        }

        $bool = static fn(string $key): bool => !empty($body[$key]);
        $int = static fn(string $key, int $min, int $max, int $default): int
            => max($min, min($max, (int)($body[$key] ?? $default)));
        $text = static fn(string $key, string $default = ''): string => trim((string)($body[$key] ?? $default));

        $this->moduleSettings->set(self::MODULE, 'enabled', $bool('enabled'));
        $this->moduleSettings->set(self::MODULE, 'allow_guests', false);
        $this->moduleSettings->set(self::MODULE, 'auth_only', true);
        $this->moduleSettings->set(self::MODULE, 'require_guest_email', $bool('require_guest_email'));
        $this->moduleSettings->set(self::MODULE, 'premoderation', $bool('premoderation'));
        $this->moduleSettings->set(self::MODULE, 'autopublish_admin', $bool('autopublish_admin'));
        $this->moduleSettings->set(self::MODULE, 'autopublish_authenticated', $bool('autopublish_authenticated'));
        $this->moduleSettings->set(self::MODULE, 'max_depth', $int('max_depth', 1, 6, 3));
        $this->moduleSettings->set(self::MODULE, 'per_page', $int('per_page', 5, 100, 20));
        $this->moduleSettings->set(self::MODULE, 'default_sort', in_array($text('default_sort', 'oldest'), ['oldest', 'newest'], true) ? $text('default_sort', 'oldest') : 'oldest');
        $this->moduleSettings->set(self::MODULE, 'pagination_mode', 'pages');
        $this->moduleSettings->set(self::MODULE, 'enabled_entity_types', $enabledEntityTypes);
        $this->moduleSettings->set(self::MODULE, 'disallow_links', $bool('disallow_links'));
        $this->moduleSettings->set(self::MODULE, 'max_url_patterns', $int('max_url_patterns', 0, 20, 0));
        $stopWords = preg_split('/\r\n|\r|\n/', (string)($body['stop_words'] ?? '')) ?: [];
        $stopWords = array_values(array_filter(array_map('trim', $stopWords)));
        $this->moduleSettings->set(self::MODULE, 'stop_words', $stopWords);
        $this->moduleSettings->set(self::MODULE, 'min_length', $int('min_length', 1, 500, 8));
        $this->moduleSettings->set(self::MODULE, 'max_length', $int('max_length', 20, 5000, 2000));
        $this->moduleSettings->set(self::MODULE, 'min_submit_seconds', $int('min_submit_seconds', 0, 120, 4));
        $this->moduleSettings->set(self::MODULE, 'delay_between_comments', $int('delay_between_comments', 0, 3600, 20));
        $this->moduleSettings->set(self::MODULE, 'comments_per_minute', $int('comments_per_minute', 1, 60, 2));
        $this->moduleSettings->set(self::MODULE, 'comments_per_hour', $int('comments_per_hour', 1, 500, 12));
        $this->moduleSettings->set(self::MODULE, 'honeypot_enabled', $bool('honeypot_enabled'));
        $this->moduleSettings->set(self::MODULE, 'time_filter_enabled', $bool('time_filter_enabled'));
        $this->moduleSettings->set(self::MODULE, 'spam_action', in_array($text('spam_action', 'moderate'), ['moderate', 'reject'], true) ? $text('spam_action', 'moderate') : 'moderate');

        return $this->redirect($this->adminPrefix() . '/comments/settings?saved=1');
    }

    public function moderateAction(Request $request, string $status): Response
    {
        if (!Csrf::check(self::MODERATE_CSRF, $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        if ($id > 0) {
            $this->updateStatuses([$id], $status);
        }
        return $this->redirect($this->adminPrefix() . '/comments?saved=1');
    }

    public function purgeAction(Request $request): Response
    {
        if (!Csrf::check(self::MODERATE_CSRF, $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $id = (int)($request->params['id'] ?? 0);
        if ($id > 0) {
            $this->purgeComments([$id]);
        }
        return $this->redirect($this->adminPrefix() . '/comments?saved=1');
    }

    public function bulkAction(Request $request): Response
    {
        if (!Csrf::check(self::MODERATE_CSRF, $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $ids = array_values(array_filter(array_map('intval', (array)($request->body['ids'] ?? []))));
        $action = (string)($request->body['bulk_action'] ?? '');
        $map = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'spam' => 'spam',
            'delete' => 'deleted',
        ];
        if ($ids !== [] && isset($map[$action])) {
            $this->updateStatuses($ids, $map[$action]);
        } elseif ($ids !== [] && $action === 'purge') {
            $this->purgeComments($ids);
        }
        return $this->redirect($this->adminPrefix() . '/comments?saved=1');
    }

    private function thread(string $entityType, int $entityId, int $page, array $settings): array
    {
        $cacheKey = $this->cacheKey($entityType, $entityId, 'approved_rows');
        $rows = $this->cache->get($cacheKey);
        if (!is_array($rows)) {
            $rows = $this->db->fetchAll($this->approvedCommentsSql(), [$entityType, $entityId]);
            $this->cache->set($cacheKey, $rows, 600);
        }

        $items = $this->buildTree($rows);
        if (($settings['default_sort'] ?? 'oldest') === 'newest') {
            $items = array_reverse($items);
        }

        $perPage = max(1, (int)$settings['per_page']);
        $count = count($items);
        $pages = max(1, (int)ceil($count / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'count' => $count,
            'pages' => $pages,
        ];
    }

    private function approvedCommentsSql(): string
    {
        $profileJoin = ($this->tableExists('users') && $this->tableExists('user_profiles'))
            ? "LEFT JOIN user_profiles up ON up.user_id = u.id"
            : '';
        $userJoin = $this->tableExists('users')
            ? "LEFT JOIN users u ON u.id = c.user_id"
            : '';
        $userFields = $this->tableExists('users')
            ? ", u.name AS user_name, u.username AS user_username, u.avatar AS user_avatar, u.profile_visibility AS user_profile_visibility, " .
              ($this->tableExists('user_profiles') ? "up.display_name AS user_display_name" : "NULL AS user_display_name")
            : ", NULL AS user_name, NULL AS user_username, NULL AS user_avatar, NULL AS user_profile_visibility, NULL AS user_display_name";

        return "
            SELECT c.*{$userFields}
            FROM comments c
            {$userJoin}
            {$profileJoin}
            WHERE c.entity_type = ? AND c.entity_id = ? AND c.status = 'approved'
            ORDER BY c.created_at ASC, c.id ASC
        ";
    }

    private function buildTree(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $row['author_display'] = $this->authorDisplay($row);
            $row['author_url'] = $this->authorUrl($row);
            $nodes[(int)$row['id']] = $row;
        }

        $roots = [];
        foreach ($nodes as $id => &$node) {
            $parentId = (int)($node['parent_id'] ?? 0);
            if ($parentId > 0 && isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    private function adminRows(array $filters, int $page, int $perPage): array
    {
        $profileJoin = ($this->tableExists('users') && $this->tableExists('user_profiles'))
            ? 'LEFT JOIN user_profiles up ON up.user_id = u.id'
            : '';
        $userJoin = $this->tableExists('users') ? 'LEFT JOIN users u ON u.id = c.user_id' : '';
        $hasProfiles = $this->tableExists('user_profiles');
        $userSearch = $this->tableExists('users')
            ? (' OR u.name LIKE :q OR u.username LIKE :q' . ($hasProfiles ? ' OR up.display_name LIKE :q' : ''))
            : '';
        $authorExpr = $this->tableExists('users')
            ? "COALESCE(" .
                ($hasProfiles ? "NULLIF(up.display_name, ''), " : '') .
                "NULLIF(u.name, ''), NULLIF(u.username, ''), NULLIF(c.guest_name, ''), 'Guest')"
            : "COALESCE(NULLIF(c.guest_name, ''), 'Guest')";
        $where = [];
        $params = [];
        if ($filters['status'] !== '') {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        } else {
            // Keep deleted comments out of the default admin list, but allow them via the status filter.
            $where[] = "c.status != 'deleted'";
        }
        if ($filters['entity_type'] !== '') {
            $where[] = 'c.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }
        if ($filters['q'] !== '') {
            $where[] = '(c.body LIKE :q OR c.guest_name LIKE :q OR c.guest_email LIKE :q OR c.ip LIKE :q' . $userSearch . ')';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if ($filters['date_from'] !== '') {
            $where[] = 'DATE(c.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if ($filters['date_to'] !== '') {
            $where[] = 'DATE(c.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $perPage;
        $countRow = $this->db->fetch(
            "SELECT COUNT(*) AS cnt
             FROM comments c
             {$userJoin}
             {$profileJoin}
             {$whereSql}",
            $params
        );
        $rows = $this->db->fetchAll(
            "SELECT c.*,
                    {$authorExpr} AS author_display,
                    (SELECT COUNT(*) FROM comments child WHERE child.parent_id = c.id AND child.status != 'deleted') AS reply_count
             FROM comments c
             {$userJoin}
             {$profileJoin}
             {$whereSql}
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [$rows, (int)($countRow['cnt'] ?? 0)];
    }

    private function updateStatuses(array $ids, string $status): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT id, entity_type, entity_id FROM comments WHERE id IN ({$placeholders})",
            $ids
        );

        $params = $ids;
        array_unshift($params, $status);
        $moderatedBy = $this->moderatorId();
        array_splice($params, 1, 0, [$moderatedBy]);
        $sql = "UPDATE comments
                SET status = ?, moderated_by = ?, moderated_at = NOW(), approved_at = " .
            ($status === 'approved' ? 'NOW()' : 'approved_at') .
            " WHERE id IN ({$placeholders})";
        $this->db->execute($sql, $params);

        foreach ($rows as $row) {
            $this->clearEntityCache((string)$row['entity_type'], (int)$row['entity_id']);
        }
    }

    private function purgeComments(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $allIds = $this->collectDescendantCommentIds($ids);
        if ($allIds === []) {
            return;
        }

        $rows = $this->fetchCommentRowsByIds($allIds);
        $placeholders = implode(',', array_fill(0, count($allIds), '?'));
        $this->db->execute("DELETE FROM comments WHERE id IN ({$placeholders})", $allIds);

        foreach ($rows as $row) {
            $this->clearEntityCache((string)$row['entity_type'], (int)$row['entity_id']);
        }
    }

    /**
     * @param array<int> $ids
     * @return array<int>
     */
    private function collectDescendantCommentIds(array $ids): array
    {
        $pending = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $all = [];

        while ($pending !== []) {
            $batch = array_splice($pending, 0, 200);
            $batch = array_values(array_unique(array_filter(array_map('intval', $batch))));
            if ($batch === []) {
                continue;
            }

            $newIds = [];
            foreach ($batch as $id) {
                if (isset($all[$id])) {
                    continue;
                }
                $all[$id] = true;
            }

            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $children = $this->db->fetchAll(
                "SELECT id FROM comments WHERE parent_id IN ({$placeholders})",
                $batch
            );
            foreach ($children as $child) {
                $childId = (int)($child['id'] ?? 0);
                if ($childId > 0 && !isset($all[$childId])) {
                    $newIds[] = $childId;
                }
            }

            if ($newIds !== []) {
                $pending = array_merge($pending, $newIds);
            }
        }

        return array_map('intval', array_keys($all));
    }

    /**
     * @param array<int> $ids
     * @return array<int, array<string, mixed>>
     */
    private function fetchCommentRowsByIds(array $ids): array
    {
        $rows = [];
        foreach (array_chunk(array_values(array_unique(array_filter(array_map('intval', $ids)))), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $chunkRows = $this->db->fetchAll(
                "SELECT id, entity_type, entity_id FROM comments WHERE id IN ({$placeholders})",
                $chunk
            );
            foreach ($chunkRows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function resolveStatus(bool $spamNeedsModeration, ?array $currentUser, array $settings): string
    {
        if ($currentUser && !empty($settings['autopublish_authenticated'])) {
            return 'approved';
        }
        if ($spamNeedsModeration) {
            return 'pending';
        }
        return !empty($settings['premoderation']) ? 'pending' : 'approved';
    }

    private function detectSpam(string $body, array $settings, string $honeypot, int $startedAt, string $ip): array
    {
        $moderate = false;
        $blocked = false;
        $reason = '';

        if (!empty($settings['honeypot_enabled']) && $honeypot !== '') {
            $blocked = true;
            $reason = 'honeypot';
        }
        if (!$blocked && !empty($settings['time_filter_enabled']) && $startedAt > 0) {
            $minSeconds = max(0, (int)$settings['min_submit_seconds']);
            if ((time() - $startedAt) < $minSeconds) {
                $moderate = true;
                $reason = 'time_filter';
            }
        }

        $linkPattern = '/(?:https?:\/\/|www\.|(?:[a-z0-9-]+\.)+(?:com|net|org|info|biz|me|io|gg|tv|ru|ua|by|kz|de|uk|co|cc|рф)\b|t\.me\/|\[url\b|url=)/iu';
        preg_match_all($linkPattern, $body, $linkMatches);
        $linkCount = count($linkMatches[0] ?? []);
        if (!$blocked && !empty($settings['disallow_links']) && $linkCount > 0) {
            if (($settings['spam_action'] ?? 'moderate') === 'reject') {
                $blocked = true;
            } else {
                $moderate = true;
            }
            $reason = 'links';
        }
        if (!$blocked && $linkCount > (int)($settings['max_url_patterns'] ?? 0) && (int)($settings['max_url_patterns'] ?? 0) >= 0) {
            $moderate = true;
            $reason = $reason !== '' ? $reason : 'url_limit';
        }

        $stopWords = array_map(static fn($value): string => mb_strtolower(trim((string)$value)), (array)($settings['stop_words'] ?? []));
        $lower = mb_strtolower($body);
        foreach ($stopWords as $stopWord) {
            if ($stopWord !== '' && mb_strpos($lower, $stopWord) !== false) {
                $moderate = true;
                $reason = $reason !== '' ? $reason : 'stop_word';
                break;
            }
        }

        if (preg_match('/(.)\1{7,}/u', $body)) {
            $moderate = true;
            $reason = $reason !== '' ? $reason : 'repeated_chars';
        }
        if (preg_match('/\b(\p{L}{2,})\b(?:\s+\1\b){3,}/iu', $body)) {
            $moderate = true;
            $reason = $reason !== '' ? $reason : 'word_flood';
        }

        $letters = preg_replace('/[^\p{L}]+/u', '', $body);
        $upper = preg_replace('/[^\p{Lu}]+/u', '', $body);
        $letterCount = mb_strlen((string)$letters);
        if ($letterCount >= 12) {
            $ratio = mb_strlen((string)$upper) / max(1, $letterCount);
            if ($ratio > 0.75) {
                $moderate = true;
                $reason = $reason !== '' ? $reason : 'caps';
            }
        }

        $delay = max(0, (int)$settings['delay_between_comments']);
        $lastAt = (int)($_SESSION['comments_last_submit_at'] ?? 0);
        if ($delay > 0 && $lastAt > 0 && (time() - $lastAt) < $delay) {
            $blocked = true;
            $reason = $reason !== '' ? $reason : 'delay';
        }

        $hash = sha1($ip . '|' . $body);
        if (($this->submissionHash() === $hash)) {
            $blocked = true;
            $reason = $reason !== '' ? $reason : 'duplicate';
        }

        return [
            'moderate' => $moderate,
            'blocked' => $blocked,
            'reason' => $reason,
        ];
    }

    private function tooManyAttempts(string $ip, array $settings): bool
    {
        $sessionKey = session_id() ?: 'guest';
        $minuteLimiter = new RateLimiter('comments_minute_' . $ip . '_' . $sessionKey, max(1, (int)$settings['comments_per_minute']), 60, true);
        $hourLimiter = new RateLimiter('comments_hour_' . $ip . '_' . $sessionKey, max(1, (int)$settings['comments_per_hour']), 3600, true);
        return $minuteLimiter->tooManyAttempts() || $hourLimiter->tooManyAttempts();
    }

    private function markAttempts(string $ip, array $settings): void
    {
        $sessionKey = session_id() ?: 'guest';
        (new RateLimiter('comments_minute_' . $ip . '_' . $sessionKey, max(1, (int)$settings['comments_per_minute']), 60, true))->hit();
        (new RateLimiter('comments_hour_' . $ip . '_' . $sessionKey, max(1, (int)$settings['comments_per_hour']), 3600, true))->hit();
        $_SESSION['comments_last_submit_at'] = time();
    }

    private function settings(): array
    {
        return array_merge($this->defaults, $this->moduleSettings->all(self::MODULE));
    }

    private function isEntityEnabled(string $entityType): bool
    {
        $settings = $this->settings();
        if (empty($settings['enabled'])) {
            return false;
        }
        return in_array($entityType, (array)($settings['enabled_entity_types'] ?? []), true);
    }

    private function entityExists(string $entityType, int $entityId): bool
    {
        $map = $this->entityMap()[$entityType] ?? null;
        if (!$map || !$this->tableExists($map['table'])) {
            return false;
        }

        if ($entityType === 'user_profile') {
            $row = $this->db->fetch(
                "SELECT id FROM users WHERE id = ? AND status = 'active' LIMIT 1",
                [$entityId]
            );
            return (bool)$row;
        }

        $sql = "SELECT {$map['id']} AS id FROM {$map['table']} WHERE {$map['id']} = ?";
        if (!empty($map['visible']) && $this->columnExists($map['table'], $map['visible'])) {
            $sql .= " AND {$map['visible']} = 1";
        }
        $row = $this->db->fetch($sql . ' LIMIT 1', [$entityId]);
        return (bool)$row;
    }

    private function entityMap(): array
    {
        return [
            'article' => ['table' => 'articles', 'id' => 'id'],
            'gallery' => ['table' => 'gallery_items', 'id' => 'id'],
            'page' => ['table' => 'pages', 'id' => 'id', 'visible' => 'visible'],
            'video' => ['table' => 'video_items', 'id' => 'id', 'visible' => 'enabled'],
            'news' => ['table' => 'news', 'id' => 'id'],
            'user_profile' => ['table' => 'users', 'id' => 'id', 'visible' => 'status'],
        ];
    }

    private function entityLabels(): array
    {
        return [
            'article' => 'Articles',
            'gallery' => 'Gallery',
            'page' => 'Pages',
            'video' => 'Videos',
            'news' => 'News',
            'user_profile' => 'User profiles',
        ];
    }

    private function authorDisplay(array $row): string
    {
        $userName = trim((string)($row['user_display_name'] ?? $row['user_name'] ?? $row['user_username'] ?? ''));
        if ($userName !== '') {
            return $userName;
        }
        $guestName = trim((string)($row['guest_name'] ?? ''));
        return $guestName !== '' ? $guestName : __('comments.author.fallback');
    }

    private function authorUrl(array $row): ?string
    {
        $username = trim((string)($row['user_username'] ?? ''));
        if ($username === '') {
            return null;
        }
        if (($row['user_profile_visibility'] ?? 'public') === 'private') {
            return null;
        }
        return '/users/' . rawurlencode($username);
    }

    private function normalizeEntityType(string $entityType): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $entityType)));
    }

    private function normalizeBody(string $body): string
    {
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace("/\r\n?/", "\n", $body) ?? $body;
        $body = preg_replace("/[ \t]+/", ' ', $body) ?? $body;
        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
        return trim($body);
    }

    private function currentUser(): ?array
    {
        try {
            return $this->container->get(Auth::class)->user();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isAuthenticatedViewer(): bool
    {
        return $this->isAdminSession() || $this->currentUser() !== null;
    }

    private function isAdminSession(): bool
    {
        return !empty($_SESSION['admin_auth']);
    }

    private function moderatorId(): ?int
    {
        $user = $this->currentUser();
        return $user ? (int)$user['id'] : null;
    }

    private function clientIp(Request $request): string
    {
        return substr((string)($request->server['REMOTE_ADDR'] ?? ''), 0, 64);
    }

    private function currentUrl(): string
    {
        return $this->sanitizeReturnTo($this->currentPath() . ($this->currentQueryString() !== '' ? ('?' . $this->currentQueryString()) : ''));
    }

    private function currentPath(): string
    {
        return parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    }

    private function currentQueryString(): string
    {
        $query = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY) ?: '';
        parse_str($query, $params);
        unset($params['comments_page']);
        return http_build_query($params);
    }

    private function sanitizeReturnTo(string $returnTo): string
    {
        $path = parse_url($returnTo, PHP_URL_PATH) ?: '/';
        $query = parse_url($returnTo, PHP_URL_QUERY);
        return $path . ($query ? '?' . $query : '');
    }

    private function redirect(string $location): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }

    private function pushFormState(string $entityType, int $entityId, array $state): void
    {
        $_SESSION['comments_form_state'][$this->stateKey($entityType, $entityId)] = $state;
    }

    private function pullFormState(string $entityType, int $entityId): array
    {
        $key = $this->stateKey($entityType, $entityId);
        $state = $_SESSION['comments_form_state'][$key] ?? [];
        unset($_SESSION['comments_form_state'][$key]);
        return is_array($state) ? $state : [];
    }

    private function stateKey(string $entityType, int $entityId): string
    {
        return $entityType . ':' . $entityId;
    }

    private function rememberSubmissionHash(string $entityType, int $entityId, int $parentId, string $body): void
    {
        $_SESSION['comments_last_submission_hash'] = sha1(($this->clientIpFromServer()) . '|' . $body);
        $_SESSION['comments_last_submission_meta'] = [$entityType, $entityId, $parentId];
    }

    private function submissionHash(): ?string
    {
        $hash = $_SESSION['comments_last_submission_hash'] ?? null;
        return is_string($hash) ? $hash : null;
    }

    private function clientIpFromServer(): string
    {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    }

    private function clearEntityCache(string $entityType, int $entityId): void
    {
        $this->cache->delete($this->cacheKey($entityType, $entityId, 'approved_rows'));
    }

    private function cacheKey(string $entityType, int $entityId, string $suffix): string
    {
        return 'comments_' . $entityType . '_' . $entityId . '_' . $suffix;
    }

    private function logSuspicious(string $entityType, int $entityId, string $ip, string $userAgent, string $reason): void
    {
        $message = sprintf(
            'comments suspicious entity=%s:%d ip=%s reason=%s ua=%s',
            $entityType,
            $entityId,
            $ip,
            $reason,
            $userAgent
        );
        Logger::log($message);

        $file = APP_ROOT . '/storage/logs/security.log';
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0775, true);
        }
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    private static function registerPublicAssets(): void
    {
        if (self::$publicAssetsRegistered) {
            return;
        }
        self::$publicAssetsRegistered = true;
        Slot::register('head_end', static fn(): string => '<link rel="stylesheet" href="' . self::publicCssUrl() . '">' . "\n");
        Slot::register('body_end', static fn(): string => '<script src="' . self::publicJsUrl() . '"></script>' . "\n");
    }

    private static function registerAdminAssets(): void
    {
        if (self::$adminAssetsRegistered) {
            return;
        }
        self::$adminAssetsRegistered = true;
        Slot::register('head_end', static fn(): string => '<link rel="stylesheet" href="' . self::publicCssUrl() . '">' . "\n");
        Slot::register('body_end', static fn(): string => '<script src="' . self::publicJsUrl() . '"></script>' . "\n");
    }

    private static function publicCssUrl(): string
    {
        return \Core\Asset::url('/modules/Comments/assets/css/comments.css');
    }

    private static function publicJsUrl(): string
    {
        return \Core\Asset::url('/modules/Comments/assets/js/comments.js');
    }

    private function adminPrefix(): string
    {
        return $this->container->get('config')['admin_prefix'] ?? '/admin';
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExists)) {
            $this->tableExists[$table] = (bool)$this->db->fetch("SHOW TABLES LIKE ?", [$table]);
        }
        return $this->tableExists[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool)$this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
    }
}
