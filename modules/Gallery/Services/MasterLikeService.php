<?php
declare(strict_types=1);

namespace Modules\Gallery\Services;

use App\Services\SecurityLog;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\RateLimiter;
use Core\Request;
use Modules\TopTattooMasters\Services\MasterSummaryService;
use Modules\Users\Services\Auth;
use Modules\Users\Services\UserRepository;

class MasterLikeService
{
    private Container $container;
    private Database $db;
    private Auth $auth;
    private UserRepository $users;
    private array $tableExists = [];
    private array $columnExists = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get(Database::class);
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
    }

    public function token(): string
    {
        return !empty($_SESSION['user_id']) ? Csrf::token('gallery_master_like') : '';
    }

    public function viewerState(?array $item = null): array
    {
        if (!$this->tableExists('master_gallery_likes')) {
            return $this->state(false, false, 'feature_unavailable', false);
        }

        $user = $this->auth->user();
        if (!$user) {
            return [
                'logged_in' => false,
                'is_eligible_master' => false,
                'can_like' => false,
                'reason' => 'login_required',
            ];
        }

        $user = $this->users->findFull((int)($user['id'] ?? 0)) ?? $user;

        if (($user['status'] ?? 'active') !== 'active') {
            return $this->state(false, false, 'account_inactive');
        }
        if (!$this->hasMasterProfile($user)) {
            return $this->state(true, false, 'master_only');
        }
        if (!$this->hasVerifiedMasterStatus($user)) {
            return $this->state(true, false, 'verified_only');
        }
        if ($this->isExcludedFromRanking((int)$user['id'])) {
            return $this->state(true, false, 'ranking_disabled');
        }
        if ($item && (int)($item['author_id'] ?? 0) === (int)($user['id'] ?? 0)) {
            return $this->state(true, true, 'self_like_forbidden', false);
        }
        if ($item && !$this->isRecognizableItem($item)) {
            return $this->state(true, true, 'item_not_eligible', false);
        }

        return $this->state(true, true, 'ok', true);
    }

    public function itemCanReceiveMasterLike(array $item): bool
    {
        return $this->isRecognizableItem($item);
    }

    public function create(int $itemId, string $csrfToken, Request $request): array
    {
        if (!Csrf::check('gallery_master_like', $csrfToken)) {
            $this->logSuspicious('master_like_csrf', ['gallery_item_id' => $itemId]);
            return ['status' => 400, 'error' => 'invalid_csrf', 'message' => 'Session expired. Refresh the page.'];
        }

        $state = $this->viewerState();
        if (empty($state['logged_in'])) {
            return ['status' => 401, 'error' => 'auth_required', 'message' => 'Login required.'];
        }
        if (empty($state['is_eligible_master'])) {
            $this->logSuspicious('master_like_forbidden_actor', ['gallery_item_id' => $itemId, 'reason' => $state['reason'] ?? 'forbidden']);
            return ['status' => 403, 'error' => (string)($state['reason'] ?? 'forbidden'), 'message' => $this->messageForError((string)($state['reason'] ?? 'forbidden'))];
        }

        $actor = $this->users->findFull((int)(($this->auth->user()['id'] ?? 0))) ?? $this->auth->user();
        if (!$actor) {
            return ['status' => 401, 'error' => 'auth_required', 'message' => 'Login required.'];
        }

        if ($itemId <= 0 || !$this->tableExists('master_gallery_likes')) {
            return ['status' => 400, 'error' => 'feature_unavailable', 'message' => $this->messageForError('feature_unavailable')];
        }

        $item = $this->findItem($itemId);
        if (!$item) {
            return ['status' => 404, 'error' => 'not_found', 'message' => 'Work not found.'];
        }
        if (!$this->isRecognizableItem($item)) {
            $this->logSuspicious('master_like_item_rejected', ['gallery_item_id' => $itemId, 'actor_id' => (int)$actor['id']]);
            return ['status' => 403, 'error' => 'item_not_eligible', 'message' => $this->messageForError('item_not_eligible')];
        }
        if ((int)($item['author_id'] ?? 0) === (int)($actor['id'] ?? 0)) {
            $this->logSuspicious('master_like_self_attempt', ['gallery_item_id' => $itemId, 'actor_id' => (int)$actor['id']]);
            return ['status' => 403, 'error' => 'self_like_forbidden', 'message' => $this->messageForError('self_like_forbidden')];
        }

        if ($this->tooManyAttempts((int)$actor['id'], $itemId, (string)($request->server['REMOTE_ADDR'] ?? ''))) {
            $this->logSuspicious('master_like_rate_limited', ['gallery_item_id' => $itemId, 'actor_id' => (int)$actor['id']]);
            return ['status' => 429, 'error' => 'rate_limited', 'message' => $this->messageForError('rate_limited')];
        }

        $existing = $this->db->fetch(
            "SELECT id, status FROM master_gallery_likes WHERE master_user_id = ? AND gallery_item_id = ? AND status = 'active' LIMIT 1",
            [(int)$actor['id'], $itemId]
        );
        if ($existing) {
            $this->hitRateLimiters((int)$actor['id'], $itemId, (string)($request->server['REMOTE_ADDR'] ?? ''));
            $this->logSuspicious('master_like_duplicate', [
                'gallery_item_id' => $itemId,
                'actor_id' => (int)$actor['id'],
                'existing_status' => (string)($existing['status'] ?? 'active'),
            ]);

            return [
                'status' => 200,
                'ok' => true,
                'already' => true,
                'master_likes' => $this->currentCount($itemId),
                'message' => $this->messageForError('already_liked'),
            ];
        }

        try {
            $this->db->execute(
                "INSERT INTO master_gallery_likes (gallery_item_id, target_user_id, master_user_id, status, created_at, updated_at)
                 VALUES (?, ?, ?, 'active', NOW(), NOW())",
                [$itemId, (int)$item['author_id'], (int)$actor['id']]
            );
            $this->syncItemCount($itemId);
        } catch (\Throwable $e) {
            $isDuplicate = stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), '1062') !== false;
            if ($isDuplicate) {
                return [
                    'status' => 200,
                    'ok' => true,
                    'already' => true,
                    'master_likes' => $this->currentCount($itemId),
                    'message' => $this->messageForError('already_liked'),
                ];
            }

            $this->logSuspicious('master_like_insert_failed', [
                'gallery_item_id' => $itemId,
                'actor_id' => (int)$actor['id'],
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'error' => 'insert_failed',
                'message' => 'Master like save failed on server.',
            ];
        }

        $this->hitRateLimiters((int)$actor['id'], $itemId, (string)($request->server['REMOTE_ADDR'] ?? ''));
        $this->refreshTargetSummary((int)$item['author_id']);

        return [
            'status' => 200,
            'ok' => true,
            'already' => false,
            'master_likes' => $this->currentCount($itemId),
            'message' => $this->messageForError('liked'),
        ];
    }

    public function recentForAdmin(int $limit = 25): array
    {
        if (!$this->tableExists('master_gallery_likes')) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->db->fetchAll("
            SELECT mgl.id,
                   mgl.gallery_item_id,
                   mgl.target_user_id,
                   mgl.master_user_id,
                   mgl.status,
                   mgl.created_at,
                   mgl.revoked_at,
                   mgl.revoke_reason,
                   gi.slug AS gallery_slug,
                   gi.title_en AS gallery_title_en,
                   gi.title_ru AS gallery_title_ru,
                   gi.path_thumb AS gallery_thumb,
                   source.username AS source_username,
                   source.name AS source_name,
                   target.username AS target_username,
                   target.name AS target_name
            FROM master_gallery_likes mgl
            JOIN gallery_items gi ON gi.id = mgl.gallery_item_id
            JOIN users source ON source.id = mgl.master_user_id
            JOIN users target ON target.id = mgl.target_user_id
            ORDER BY mgl.created_at DESC
            LIMIT {$limit}
        ");
    }

    public function revokeByAdmin(int $likeId, int $adminUserId = 0, string $reason = 'admin_revoke'): bool
    {
        if ($likeId <= 0 || !$this->tableExists('master_gallery_likes')) {
            return false;
        }

        $like = $this->db->fetch("SELECT * FROM master_gallery_likes WHERE id = ? LIMIT 1", [$likeId]);
        if (!$like || ($like['status'] ?? 'active') !== 'active') {
            return false;
        }

        $galleryItemId = (int)($like['gallery_item_id'] ?? 0);
        $targetUserId = (int)($like['target_user_id'] ?? 0);

        $this->db->transaction(function () use ($likeId, $adminUserId, $reason, $galleryItemId): void {
            $this->db->execute(
                "UPDATE master_gallery_likes
                 SET status = 'revoked',
                     revoked_at = NOW(),
                     revoked_by_admin_id = ?,
                     revoke_reason = ?,
                     updated_at = NOW()
                 WHERE id = ?",
                [$adminUserId > 0 ? $adminUserId : null, mb_substr(trim($reason), 0, 255), $likeId]
            );
            $this->syncItemCount($galleryItemId);
        });

        SecurityLog::log('master_like_revoked', [
            'master_like_id' => $likeId,
            'gallery_item_id' => $galleryItemId,
            'target_user_id' => $targetUserId,
            'admin_user_id' => $adminUserId,
            'reason' => $reason,
        ]);

        $this->refreshTargetSummary($targetUserId);

        return true;
    }

    private function state(bool $loggedIn, bool $eligibleMaster, string $reason, bool $canLike = false): array
    {
        return [
            'logged_in' => $loggedIn,
            'is_eligible_master' => $eligibleMaster,
            'can_like' => $canLike,
            'reason' => $reason,
        ];
    }

    private function findItem(int $itemId): ?array
    {
        if (!$this->tableExists('gallery_items')) {
            return null;
        }

        $select = "SELECT gi.id, gi.author_id";
        if ($this->columnExists('gallery_items', 'submitted_by_master')) {
            $select .= ", gi.submitted_by_master";
        }
        if ($this->columnExists('gallery_items', 'status')) {
            $select .= ", gi.status";
        }
        if ($this->columnExists('gallery_items', 'master_likes_count')) {
            $select .= ", gi.master_likes_count";
        }

        return $this->db->fetch("{$select} FROM gallery_items gi WHERE gi.id = ? LIMIT 1", [$itemId]);
    }

    private function isRecognizableItem(array $item): bool
    {
        if ((int)($item['author_id'] ?? 0) <= 0) {
            return false;
        }
        if ($this->columnExists('gallery_items', 'submitted_by_master') && empty($item['submitted_by_master'])) {
            return false;
        }
        if ($this->columnExists('gallery_items', 'status') && (string)($item['status'] ?? '') !== 'approved') {
            return false;
        }
        return true;
    }

    private function hasMasterProfile(array $user): bool
    {
        if (!empty($user['is_master'])) {
            return true;
        }

        $permissions = array_map('strval', (array)($user['permissions'] ?? []));
        if (in_array('gallery.submit', $permissions, true) || in_array('gallery.publish', $permissions, true)) {
            return true;
        }

        $primarySlug = (string)(($user['primary_group']['slug'] ?? ''));
        if (in_array($primarySlug, ['master', 'verified_master'], true)) {
            return true;
        }

        foreach ((array)($user['groups'] ?? []) as $group) {
            if (in_array((string)($group['slug'] ?? ''), ['master', 'verified_master'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasVerifiedMasterStatus(array $user): bool
    {
        if (!empty($user['is_verified'])) {
            return true;
        }

        $permissions = array_map('strval', (array)($user['permissions'] ?? []));
        if (in_array('profile.verified', $permissions, true)) {
            return true;
        }

        $primarySlug = (string)(($user['primary_group']['slug'] ?? ''));
        if ($primarySlug === 'verified_master') {
            return true;
        }

        foreach ((array)($user['groups'] ?? []) as $group) {
            if ((string)($group['slug'] ?? '') === 'verified_master') {
                return true;
            }
        }

        return false;
    }

    private function tooManyAttempts(int $actorId, int $itemId, string $ip): bool
    {
        $hash = sha1($ip !== '' ? $ip : 'no-ip');
        $checks = [
            new RateLimiter('master_like_item_' . $actorId . '_' . $itemId, 3, 600, true),
            new RateLimiter('master_like_user_' . $actorId, 40, 86400, true),
            new RateLimiter('master_like_ip_' . $hash, 120, 86400, true),
        ];

        foreach ($checks as $limiter) {
            if ($limiter->tooManyAttempts()) {
                return true;
            }
        }

        return false;
    }

    private function hitRateLimiters(int $actorId, int $itemId, string $ip): void
    {
        $hash = sha1($ip !== '' ? $ip : 'no-ip');
        foreach ([
            new RateLimiter('master_like_item_' . $actorId . '_' . $itemId, 3, 600, true),
            new RateLimiter('master_like_user_' . $actorId, 40, 86400, true),
            new RateLimiter('master_like_ip_' . $hash, 120, 86400, true),
        ] as $limiter) {
            $limiter->hit();
        }
    }

    private function currentCount(int $itemId): int
    {
        if ($itemId <= 0 || !$this->tableExists('master_gallery_likes')) {
            return 0;
        }

        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM master_gallery_likes WHERE gallery_item_id = ? AND status = 'active'",
            [$itemId]
        );

        return (int)($row['cnt'] ?? 0);
    }

    private function syncItemCount(int $itemId): void
    {
        if ($itemId <= 0 || !$this->tableExists('master_gallery_likes') || !$this->columnExists('gallery_items', 'master_likes_count')) {
            return;
        }

        $this->db->execute("
            UPDATE gallery_items
            SET master_likes_count = (
                SELECT COUNT(*)
                FROM master_gallery_likes
                WHERE gallery_item_id = ?
                  AND status = 'active'
            )
            WHERE id = ?
        ", [$itemId, $itemId]);
    }

    private function refreshTargetSummary(int $targetUserId): void
    {
        if ($targetUserId <= 0 || !class_exists(MasterSummaryService::class)) {
            return;
        }

        try {
            /** @var MasterSummaryService $summary */
            $summary = $this->container->get(MasterSummaryService::class);
            $summary->refreshUser($targetUserId);
            $summary->cacheBust();
        } catch (\Throwable $e) {
            SecurityLog::log('master_like_summary_refresh_failed', [
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isExcludedFromRanking(int $userId): bool
    {
        if ($userId <= 0 || !$this->tableExists('top_tattoo_master_summary')) {
            return false;
        }

        $row = $this->db->fetch(
            "SELECT excluded_from_top FROM top_tattoo_master_summary WHERE user_id = ? LIMIT 1",
            [$userId]
        );

        return !empty($row['excluded_from_top']);
    }

    private function logSuspicious(string $type, array $payload): void
    {
        SecurityLog::log($type, $payload);
    }

    private function messageForError(string $code): string
    {
        return match ($code) {
            'feature_unavailable' => 'Master likes are not enabled yet. Run the new migration.',
            'master_only' => 'Only master profiles can use this mark.',
            'verified_only' => 'Only verified masters can use this mark.',
            'ranking_disabled' => 'This account is excluded from ranking and cannot send master likes.',
            'self_like_forbidden' => 'You cannot mark your own work.',
            'item_not_eligible' => 'Only approved master-submitted works can receive this mark.',
            'rate_limited' => 'Too many attempts. Try later.',
            'already_liked' => 'You already marked this work.',
            'liked' => 'Master recognition recorded.',
            default => 'Request rejected.',
        };
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
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columnExists)) {
            $this->columnExists[$key] = (bool)$this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
        }

        return $this->columnExists[$key];
    }
}
