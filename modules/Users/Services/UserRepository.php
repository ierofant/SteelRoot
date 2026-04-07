<?php
namespace Modules\Users\Services;

use Core\Database;

class UserRepository
{
    private Database $db;
    private array $tableExists = [];
    private array $columnExists = [];
    private bool $systemGroupsEnsured = false;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $name, string $email, string $passwordHash, string $role, string $status, ?string $avatar = null, ?string $username = null, string $profileVisibility = 'public', ?string $signature = null, ?string $registrationIp = null): int
    {
        $hasRegistrationIp = $this->columnExists('users', 'registration_ip');
        $columns = 'name, email, username, password, role, status, profile_visibility, avatar, signature';
        $values = ':name, :email, :username, :password, :role, :status, :profile_visibility, :avatar, :signature';
        if ($hasRegistrationIp) {
            $columns .= ', registration_ip';
            $values .= ', :registration_ip';
        }

        $params = [
            ':name' => $name,
            ':email' => $email,
            ':username' => $username,
            ':password' => $passwordHash,
            ':role' => $role,
            ':status' => $status,
            ':avatar' => $avatar,
            ':profile_visibility' => $profileVisibility,
            ':signature' => $signature,
        ];
        if ($hasRegistrationIp) {
            $params[':registration_ip'] = $registrationIp;
        }

        $this->db->execute("
            INSERT INTO users ({$columns}, created_at, updated_at)
            VALUES ({$values}, NOW(), NOW())
        ", $params);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function findFull(int $id): ?array
    {
        $user = $this->find($id);
        return $user ? $this->attachProfileData($user) : null;
    }

    public function findFullByUsername(string $username): ?array
    {
        $user = $this->findByUsername($username);
        return $user ? $this->attachProfileData($user) : null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $params = [$email];
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        return (bool)$this->db->fetch($sql, $params);
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'email', 'role', 'status', 'avatar', 'username', 'profile_visibility', 'signature'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $data[$key];
            }
        }
        if (!$fields) {
            return;
        }
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $this->db->execute($sql, $params);
    }

    public function upsertProfile(int $userId, array $data): void
    {
        if (!$this->tableExists('user_profiles')) {
            return;
        }

        $this->ensureProfile($userId);
        $profile = $this->db->fetch("SELECT is_verified FROM user_profiles WHERE user_id = ? LIMIT 1", [$userId]) ?: [];
        $allowed = [
            'display_name',
            'bio',
            'artist_note',
            'specialization',
            'styles',
            'city',
            'studio_name',
            'experience_years',
            'price_from',
            'booking_status',
            'contacts_text',
            'external_links_json',
            'cover_image',
            'photo_copyright_enabled',
            'photo_copyright_text',
            'photo_copyright_font',
            'photo_copyright_color',
            'visibility_mode',
            'show_contacts',
            'show_favorites',
            'show_comments',
            'show_ratings',
            'show_works',
            'show_personal_feed',
            'show_personal_feed_works',
            'show_personal_feed_masters',
            'comments_moderation',
            'is_master',
            'is_verified',
            'is_featured',
            'plan_slug',
        ];
        $fields = [];
        $params = [':user_id' => $userId];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = $key . ' = :' . $key;
                $params[':' . $key] = $data[$key];
            }
        }
        if (!$fields) {
            return;
        }

        if ($this->columnExists('user_profiles', 'verified_at') && array_key_exists('is_verified', $data)) {
            $wasVerified = !empty($profile['is_verified']);
            $isVerified = !empty($data['is_verified']);
            if (!$wasVerified && $isVerified) {
                $fields[] = 'verified_at = NOW()';
            } elseif ($wasVerified && !$isVerified) {
                $fields[] = 'verified_at = NULL';
            }
        }

        $fields[] = 'updated_at = NOW()';
        $this->db->execute(
            'UPDATE user_profiles SET ' . implode(', ', $fields) . ' WHERE user_id = :user_id',
            $params
        );
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $params = [$username];
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        return (bool)$this->db->fetch($sql, $params);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->db->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $id]);
    }

    public function deleteUser(int $id): void
    {
        $user = $this->find($id);
        if (!$user) {
            return;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            $this->nullAuthorLinks($id);
            $this->clearLooseUserLinks($id, (string)($user['email'] ?? ''));
            $this->db->execute("DELETE FROM users WHERE id = ?", [$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $row = $this->db->fetch("SELECT COUNT(*) as cnt FROM users u {$where}", $params);
        return (int)($row['cnt'] ?? 0);
    }

    public function list(array $filters = [], int $limit = 20, int $offset = 0, string $sort = 'created_at', string $dir = 'desc'): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $joinProfile = $this->tableExists('user_profiles') ? 'LEFT JOIN user_profiles up ON up.user_id = u.id' : '';
        $hasGroupMap = $this->tableExists('user_group_user_map');
        $hasGroups = $this->tableExists('user_groups');
        $joinGroupMap = $hasGroupMap ? 'LEFT JOIN user_group_user_map ugm ON ugm.user_id = u.id AND ugm.is_primary = 1' : '';
        $joinGroups = ($hasGroupMap && $hasGroups) ? 'LEFT JOIN user_groups ug ON ug.id = ugm.group_id' : '';
        $orderBy = $this->resolveAdminListOrder($sort, $dir);
        $registrationIpSelect = $this->columnExists('users', 'registration_ip') ? 'u.registration_ip,' : 'NULL AS registration_ip,';
        return $this->db->fetchAll("
            SELECT u.*,
                   {$registrationIpSelect}
                   up.is_master,
                   up.is_verified,
                   up.is_featured,
                   up.plan_slug,
                   ug.name AS primary_group_name,
                   ug.slug AS primary_group_slug,
                   (SELECT MAX(created_at) FROM login_logs l WHERE l.user_id = u.id) AS last_login
            FROM users u
            {$joinProfile}
            {$joinGroupMap}
            {$joinGroups}
            {$where}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ", $params);
    }

    public function export(array $filters = [], string $sort = 'created_at', string $dir = 'desc'): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $joinProfile = $this->tableExists('user_profiles') ? 'LEFT JOIN user_profiles up ON up.user_id = u.id' : '';
        $hasGroupMap = $this->tableExists('user_group_user_map');
        $hasGroups = $this->tableExists('user_groups');
        $joinGroupMap = $hasGroupMap ? 'LEFT JOIN user_group_user_map ugm ON ugm.user_id = u.id AND ugm.is_primary = 1' : '';
        $joinGroups = ($hasGroupMap && $hasGroups) ? 'LEFT JOIN user_groups ug ON ug.id = ugm.group_id' : '';
        $orderBy = $this->resolveAdminListOrder($sort, $dir);

        return $this->db->fetchAll("
            SELECT u.email, u.name
            FROM users u
            {$joinProfile}
            {$joinGroupMap}
            {$joinGroups}
            {$where}
            ORDER BY {$orderBy}
        ", $params);
    }

    private function filterWhere(array $filters): array
    {
        $conds = [];
        $params = [];
        if (!empty($filters['email'])) {
            $conds[] = "email LIKE :email";
            $params[':email'] = '%' . $filters['email'] . '%';
        }
        if (!empty($filters['username'])) {
            $conds[] = "username LIKE :username";
            $params[':username'] = '%' . $filters['username'] . '%';
        }
        if (!empty($filters['role'])) {
            $conds[] = "role = :role";
            $params[':role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $conds[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['group']) && $this->tableExists('user_group_user_map') && $this->tableExists('user_groups')) {
            $conds[] = "EXISTS (
                SELECT 1
                FROM user_group_user_map ugm2
                JOIN user_groups ug2 ON ug2.id = ugm2.group_id
                WHERE ugm2.user_id = u.id AND ug2.slug = :group_slug
            )";
            $params[':group_slug'] = $filters['group'];
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$where, $params];
    }

    private function resolveAdminListOrder(string $sort, string $dir): string
    {
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $map = [
            'id' => 'u.id',
            'name' => 'u.name',
            'email' => 'u.email',
            'username' => 'u.username',
            'role' => 'u.role',
            'group' => 'primary_group_name',
            'status' => 'u.status',
            'last_login' => 'last_login',
            'created_at' => 'u.created_at',
        ];

        $column = $map[$sort] ?? $map['created_at'];
        if (in_array($sort, ['name', 'email', 'username', 'role', 'group', 'status'], true)) {
            return "{$column} {$direction}, u.id DESC";
        }

        return "{$column} {$direction}";
    }

    public function touchLastSeen(int $userId): void
    {
        try {
            $this->db->execute(
                "UPDATE users SET last_seen_at = NOW() WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))",
                [$userId]
            );
        } catch (\Throwable) {}
    }

    public function logLogin(?int $userId, string $ip, string $ua): void
    {
        $this->db->execute("
            INSERT INTO login_logs (user_id, ip, ua, created_at)
            VALUES (:user_id, :ip, :ua, NOW())
        ", [
            ':user_id' => $userId,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
    }

    public function lastLogin(int $userId): ?string
    {
        $row = $this->db->fetch("SELECT created_at FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$userId]);
        return $row['created_at'] ?? null;
    }

    public function storeRememberToken(int $userId, string $selector, string $tokenHash, int $ttlSeconds, string $ip = '', string $userAgent = ''): void
    {
        if (!$this->tableExists('user_remember_tokens')) {
            return;
        }

        $this->db->execute("DELETE FROM user_remember_tokens WHERE user_id = ? OR expires_at < NOW()", [$userId]);
        $this->db->execute(
            "INSERT INTO user_remember_tokens (user_id, selector, token_hash, ip, user_agent, expires_at, last_used_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), NOW(), NOW())",
            [$userId, $selector, $tokenHash, $ip !== '' ? $ip : null, $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null, $ttlSeconds]
        );
    }

    public function findRememberToken(string $selector): ?array
    {
        if (!$this->tableExists('user_remember_tokens') || $selector === '') {
            return null;
        }

        return $this->db->fetch(
            "SELECT * FROM user_remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1",
            [$selector]
        );
    }

    public function rotateRememberToken(string $selector, string $tokenHash, int $ttlSeconds, string $ip = '', string $userAgent = ''): void
    {
        if (!$this->tableExists('user_remember_tokens') || $selector === '') {
            return;
        }

        $this->db->execute(
            "UPDATE user_remember_tokens
                SET token_hash = ?,
                    ip = ?,
                    user_agent = ?,
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                    last_used_at = NOW(),
                    updated_at = NOW()
              WHERE selector = ?",
            [$tokenHash, $ip !== '' ? $ip : null, $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null, $ttlSeconds, $selector]
        );
    }

    public function deleteRememberToken(string $selector): void
    {
        if (!$this->tableExists('user_remember_tokens') || $selector === '') {
            return;
        }

        $this->db->execute("DELETE FROM user_remember_tokens WHERE selector = ?", [$selector]);
    }

    // ── Password reset tokens ─────────────────────────────────

    public function createResetToken(string $email, string $token, int $ttlSeconds = 3600): void
    {
        $this->ensurePasswordResetsTable();
        $this->db->execute("DELETE FROM password_resets WHERE email = ?", [$email]);
        $this->db->execute(
            "INSERT INTO password_resets (email, token, expires_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
            [$email, $token, $ttlSeconds]
        );
    }

    public function findValidResetToken(string $token): ?array
    {
        if (!$this->ensurePasswordResetsTable()) {
            return null;
        }
        return $this->db->fetch(
            "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()",
            [$token]
        );
    }

    public function deleteResetToken(string $token): void
    {
        if (!$this->ensurePasswordResetsTable()) {
            return;
        }
        $this->db->execute("DELETE FROM password_resets WHERE token = ?", [$token]);
    }

    public function listGroups(): array
    {
        if (!$this->tableExists('user_groups')) {
            return [];
        }
        $this->ensureCoreUserGroups();

        $usersCountSql = $this->tableExists('user_group_user_map')
            ? "(SELECT COUNT(*) FROM user_group_user_map ugm WHERE ugm.group_id = g.id)"
            : "0";
        $permissionsCountSql = $this->tableExists('user_group_permissions')
            ? "(SELECT COUNT(*) FROM user_group_permissions ugp WHERE ugp.group_id = g.id)"
            : "0";

        return $this->db->fetchAll("
            SELECT g.*,
                   {$usersCountSql} AS users_count,
                   {$permissionsCountSql} AS permissions_count
            FROM user_groups g
            ORDER BY g.is_system DESC, g.name ASC
        ");
    }

    public function groupOptions(): array
    {
        if (!$this->tableExists('user_groups')) {
            return [];
        }
        $this->ensureCoreUserGroups();

        return $this->db->fetchAll("SELECT id, name, slug FROM user_groups WHERE enabled = 1 ORDER BY name ASC");
    }

    public function findGroup(int $id): ?array
    {
        if (!$this->tableExists('user_groups')) {
            return null;
        }
        $this->ensureCoreUserGroups();

        return $this->db->fetch("SELECT * FROM user_groups WHERE id = ? LIMIT 1", [$id]);
    }

    public function saveGroup(int $id, array $data, array $permissions = []): int
    {
        if (!$this->tableExists('user_groups')) {
            return 0;
        }

        if ($id > 0) {
            $this->db->execute("
                UPDATE user_groups
                SET name = :name,
                    slug = :slug,
                    description = :description,
                    enabled = :enabled,
                    updated_at = NOW()
                WHERE id = :id
            ", [
                ':id' => $id,
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] !== '' ? $data['description'] : null,
                ':enabled' => (int)$data['enabled'],
            ]);
        } else {
            $this->db->execute("
                INSERT INTO user_groups (name, slug, description, enabled, is_system, created_at, updated_at)
                VALUES (:name, :slug, :description, :enabled, :is_system, NOW(), NOW())
            ", [
                ':name' => $data['name'],
                ':slug' => $data['slug'],
                ':description' => $data['description'] !== '' ? $data['description'] : null,
                ':enabled' => (int)$data['enabled'],
                ':is_system' => (int)($data['is_system'] ?? 0),
            ]);
            $id = (int)$this->db->pdo()->lastInsertId();
        }

        if ($this->tableExists('user_group_permissions')) {
            $this->db->execute("DELETE FROM user_group_permissions WHERE group_id = ?", [$id]);
            foreach ($permissions as $permission) {
                $this->db->execute("
                    INSERT INTO user_group_permissions (group_id, permission_key, created_at)
                    VALUES (?, ?, NOW())
                ", [$id, $permission]);
            }
        }

        return $id;
    }

    public function permissionsForGroup(int $groupId): array
    {
        if (!$this->tableExists('user_group_permissions')) {
            return [];
        }

        return array_map(static function (array $row): string {
            return (string)$row['permission_key'];
        }, $this->db->fetchAll(
            "SELECT permission_key FROM user_group_permissions WHERE group_id = ? ORDER BY permission_key ASC",
            [$groupId]
        ));
    }

    public function assignGroups(int $userId, array $groupIds, ?int $primaryGroupId = null): void
    {
        if (!$this->tableExists('user_group_user_map')) {
            return;
        }

        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static function (int $id): bool {
            return $id > 0;
        })));

        $this->db->execute("DELETE FROM user_group_user_map WHERE user_id = ?", [$userId]);
        foreach ($groupIds as $groupId) {
            $this->db->execute("
                INSERT INTO user_group_user_map (user_id, group_id, is_primary, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$userId, $groupId, $primaryGroupId !== null && $primaryGroupId === $groupId ? 1 : 0]);
        }
    }

    public function groupsForUser(int $userId): array
    {
        if (!$this->tableExists('user_group_user_map') || !$this->tableExists('user_groups')) {
            return [];
        }
        $this->ensureCoreUserGroups();

        return $this->db->fetchAll("
            SELECT g.*, ugm.is_primary
            FROM user_group_user_map ugm
            JOIN user_groups g ON g.id = ugm.group_id
            WHERE ugm.user_id = ?
            ORDER BY ugm.is_primary DESC, g.name ASC
        ", [$userId]);
    }

    public function primaryGroupForUser(int $userId): ?array
    {
        foreach ($this->groupsForUser($userId) as $group) {
            if (!empty($group['is_primary'])) {
                return $group;
            }
        }

        return null;
    }

    public function permissionsForUser(int $userId): array
    {
        if (!$this->tableExists('user_group_permissions') || !$this->tableExists('user_group_user_map')) {
            return [];
        }
        $this->ensureCoreUserGroups();

        $rows = $this->db->fetchAll("
            SELECT DISTINCT ugp.permission_key
            FROM user_group_user_map ugm
            JOIN user_group_permissions ugp ON ugp.group_id = ugm.group_id
            JOIN user_groups ug ON ug.id = ugm.group_id AND ug.enabled = 1
            WHERE ugm.user_id = ?
        ", [$userId]);

        return array_map(static function (array $row): string {
            return (string)$row['permission_key'];
        }, $rows);
    }

    private function ensureCoreUserGroups(): void
    {
        if ($this->systemGroupsEnsured || !$this->tableExists('user_groups')) {
            return;
        }

        $groups = [
            'master' => ['Tattoo Master', 'Tattoo master profile', 0, ['profile.extended', 'gallery.submit', 'favorites.manage', 'comments.profile', 'profile.contacts', 'profile.links']],
            'verified_master' => ['Verified Tattoo Master', 'Approved tattoo master', 0, ['profile.extended', 'gallery.submit', 'gallery.publish', 'favorites.manage', 'comments.profile', 'profile.contacts', 'profile.links', 'profile.verified']],
        ];

        foreach ($groups as $slug => [$name, $description, $system, $permissions]) {
            $this->db->execute("
                INSERT INTO user_groups (name, slug, description, enabled, is_system, created_at, updated_at)
                VALUES (:name, :slug, :description, 1, :is_system, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    is_system = VALUES(is_system),
                    enabled = 1,
                    updated_at = NOW()
            ", [
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':is_system' => $system,
            ]);

            if (!$this->tableExists('user_group_permissions')) {
                continue;
            }

            $group = $this->db->fetch("SELECT id FROM user_groups WHERE slug = ? LIMIT 1", [$slug]);
            if (!$group) {
                continue;
            }
            foreach ($permissions as $permission) {
                $this->db->execute("
                    INSERT IGNORE INTO user_group_permissions (group_id, permission_key, created_at)
                    VALUES (?, ?, NOW())
                ", [(int)$group['id'], $permission]);
            }
        }

        $this->systemGroupsEnsured = true;
    }

    public function toggleFavorite(int $userId, string $entityType, int $entityId): bool
    {
        if (!$this->tableExists('user_favorites')) {
            return false;
        }

        $entityType = $this->normalizeFavoriteEntityType($entityType);
        if ($entityType === '' || !$this->favoriteTargetExists($entityType, $entityId)) {
            return false;
        }

        if ($this->isFavorite($userId, $entityType, $entityId)) {
            $this->db->execute(
                "DELETE FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id = ?",
                [$userId, $entityType, $entityId]
            );
            return false;
        }

        $this->db->execute("
            INSERT INTO user_favorites (user_id, entity_type, entity_id, created_at)
            VALUES (?, ?, ?, NOW())
        ", [$userId, $entityType, $entityId]);
        return true;
    }

    public function isFavorite(int $userId, string $entityType, int $entityId): bool
    {
        if (!$this->tableExists('user_favorites')) {
            return false;
        }

        return (bool)$this->db->fetch(
            "SELECT id FROM user_favorites WHERE user_id = ? AND entity_type = ? AND entity_id = ? LIMIT 1",
            [$userId, $this->normalizeFavoriteEntityType($entityType), $entityId]
        );
    }

    public function favoritesForUser(int $userId, int $limit = 12): array
    {
        if (!$this->tableExists('user_favorites')) {
            return [];
        }

        $rows = $this->db->fetchAll("
            SELECT *
            FROM user_favorites
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT " . max(1, (int)$limit),
            [$userId]
        );

        $items = [];
        foreach ($rows as $row) {
            $resolved = $this->resolveFavorite($row);
            if ($resolved) {
                $items[] = $resolved;
            }
        }

        return $items;
    }

    public function commentsForUser(int $userId, int $limit = 10): array
    {
        if (!$this->tableExists('comments')) {
            return [];
        }

        $rows = $this->db->fetchAll("
            SELECT entity_type, entity_id, body, status, created_at
            FROM comments
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT " . max(1, (int)$limit),
            [$userId]
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = $row + $this->resolveCommentTarget($row);
        }

        return $items;
    }

    public function recentWorksForUser(int $userId, int $limit = 8): array
    {
        if (!$this->tableExists('gallery_items') || !$this->columnExists('gallery_items', 'author_id')) {
            return [];
        }
        $statusSql = $this->columnExists('gallery_items', 'status')
            ? " AND status = 'approved'"
            : '';
        return $this->db->fetchAll("
            SELECT id, slug, title_en, title_ru, path_thumb, created_at
            FROM gallery_items
            WHERE author_id = ?{$statusSql}
            ORDER BY created_at DESC
            LIMIT " . max(1, (int)$limit),
            [$userId]
        );
    }

    public function publicWorksForUser(int $userId, int $limit = 24, int $offset = 0): array
    {
        if (!$this->tableExists('gallery_items') || !$this->columnExists('gallery_items', 'author_id')) {
            return [];
        }
        $statusSql = $this->columnExists('gallery_items', 'status')
            ? " AND status = 'approved'"
            : '';
        return $this->db->fetchAll("
            SELECT id, slug, title_en, title_ru, description_en, description_ru, path, path_medium, path_thumb, created_at
            FROM gallery_items
            WHERE author_id = ?{$statusSql}
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, (int)$limit) . " OFFSET " . max(0, (int)$offset),
            [$userId]
        );
    }

    public function publicWorksCountForUser(int $userId): int
    {
        if (!$this->tableExists('gallery_items') || !$this->columnExists('gallery_items', 'author_id')) {
            return 0;
        }
        $statusSql = $this->columnExists('gallery_items', 'status')
            ? " AND status = 'approved'"
            : '';
        $row = $this->db->fetch("
            SELECT COUNT(*) AS cnt
            FROM gallery_items
            WHERE author_id = ?{$statusSql}
        ", [$userId]);
        return (int)($row['cnt'] ?? 0);
    }

    public function publicDirectoryCount(): int
    {
        $visibilityExpr = $this->tableExists('user_profiles')
            ? "COALESCE(NULLIF(up.visibility_mode, ''), NULLIF(u.profile_visibility, ''), 'public')"
            : "COALESCE(NULLIF(u.profile_visibility, ''), 'public')";
        $joinProfile = $this->tableExists('user_profiles') ? 'LEFT JOIN user_profiles up ON up.user_id = u.id' : '';

        $row = $this->db->fetch("
            SELECT COUNT(*) AS cnt
            FROM users u
            {$joinProfile}
            WHERE u.status = 'active'
              AND {$visibilityExpr} = 'public'
        ");

        return (int)($row['cnt'] ?? 0);
    }

    public function publicDirectory(int $limit = 18, int $offset = 0): array
    {
        $joinProfile = $this->tableExists('user_profiles') ? 'LEFT JOIN user_profiles up ON up.user_id = u.id' : '';
        $visibilityExpr = $this->tableExists('user_profiles')
            ? "COALESCE(NULLIF(up.visibility_mode, ''), NULLIF(u.profile_visibility, ''), 'public')"
            : "COALESCE(NULLIF(u.profile_visibility, ''), 'public')";
        $displayNameExpr = $this->tableExists('user_profiles')
            ? "COALESCE(NULLIF(up.display_name, ''), u.name)"
            : 'u.name';
        $cityExpr = $this->tableExists('user_profiles') ? 'up.city' : 'NULL';
        $specializationExpr = $this->tableExists('user_profiles') ? 'up.specialization' : 'NULL';
        $stylesExpr = $this->tableExists('user_profiles') ? 'up.styles' : 'NULL';
        $bioExpr = $this->tableExists('user_profiles') ? 'up.bio' : 'NULL';
        $artistNoteExpr = $this->tableExists('user_profiles') ? 'up.artist_note' : 'NULL';
        $coverExpr = $this->tableExists('user_profiles') ? 'up.cover_image' : 'NULL';
        $isMasterExpr = $this->tableExists('user_profiles') ? 'COALESCE(up.is_master, 0)' : '0';
        $isVerifiedExpr = $this->tableExists('user_profiles') ? 'COALESCE(up.is_verified, 0)' : '0';
        $isFeaturedExpr = $this->tableExists('user_profiles') ? 'COALESCE(up.is_featured, 0)' : '0';
        $worksCountSql = ($this->tableExists('gallery_items') && $this->columnExists('gallery_items', 'author_id'))
            ? "(SELECT COUNT(*) FROM gallery_items gi WHERE gi.author_id = u.id" . ($this->columnExists('gallery_items', 'status') ? " AND gi.status = 'approved'" : '') . ')'
            : '0';

        return $this->db->fetchAll("
            SELECT
                u.id,
                u.name,
                u.username,
                u.avatar,
                u.signature,
                {$displayNameExpr} AS display_name,
                {$cityExpr} AS city,
                {$specializationExpr} AS specialization,
                {$stylesExpr} AS styles,
                {$bioExpr} AS bio,
                {$artistNoteExpr} AS artist_note,
                {$coverExpr} AS cover_image,
                {$isMasterExpr} AS is_master,
                {$isVerifiedExpr} AS is_verified,
                {$isFeaturedExpr} AS is_featured,
                {$worksCountSql} AS works_count
            FROM users u
            {$joinProfile}
            WHERE u.status = 'active'
              AND {$visibilityExpr} = 'public'
            ORDER BY
                is_featured DESC,
                is_verified DESC,
                is_master DESC,
                works_count DESC,
                display_name ASC,
                u.id DESC
            LIMIT " . max(1, (int)$limit) . " OFFSET " . max(0, (int)$offset)
        );
    }

    public function submissionsForUser(int $userId, int $limit = 20): array
    {
        if (!$this->tableExists('gallery_items') || !$this->columnExists('gallery_items', 'author_id')) {
            return [];
        }

        $statusSelect = $this->columnExists('gallery_items', 'status') ? ', status, status_note, reviewed_at' : '';
        return $this->db->fetchAll("
            SELECT id, slug, title_en, title_ru, path_thumb, created_at{$statusSelect}
            FROM gallery_items
            WHERE author_id = ?
            ORDER BY created_at DESC
            LIMIT " . max(1, (int)$limit),
            [$userId]
        );
    }

    public function ratingsSummaryForUser(int $userId): array
    {
        if (!$this->tableExists('user_ratings')) {
            return ['avg' => null, 'count' => 0, 'latest' => []];
        }

        $summary = $this->db->fetch("
            SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS rating_count
            FROM user_ratings
            WHERE target_user_id = ? AND status = 'approved'
        ", [$userId]) ?: [];

        $latest = $this->db->fetchAll("
            SELECT ur.*, u.name AS author_name, u.username AS author_username
            FROM user_ratings ur
            LEFT JOIN users u ON u.id = ur.author_user_id
            WHERE ur.target_user_id = ? AND ur.status = 'approved' AND ur.review IS NOT NULL AND ur.review != ''
            ORDER BY ur.created_at DESC
            LIMIT 5
        ", [$userId]);

        return [
            'avg' => isset($summary['avg_rating']) ? (float)$summary['avg_rating'] : null,
            'count' => (int)($summary['rating_count'] ?? 0),
            'latest' => $latest,
        ];
    }

    public function allProfileSettings(): array
    {
        return [
            'show_contacts',
            'show_favorites',
            'show_comments',
            'show_ratings',
            'show_works',
            'show_personal_feed',
            'show_personal_feed_works',
            'show_personal_feed_masters',
            'comments_moderation',
        ];
    }

    private function attachProfileData(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId < 1) {
            return $user;
        }

        if ($this->tableExists('user_profiles')) {
            $this->ensureProfile($userId);
            $profile = $this->db->fetch("SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1", [$userId]) ?: [];
            if ($profile) {
                $user = array_merge($user, $profile);
            }
        }

        $user['groups'] = $this->groupsForUser($userId);
        $user['primary_group'] = $this->primaryGroupForUser($userId);
        $user['permissions'] = $this->permissionsForUser($userId);
        $user['favorites_count'] = $this->tableExists('user_favorites')
            ? (int)(($this->db->fetch("SELECT COUNT(*) AS cnt FROM user_favorites WHERE user_id = ?", [$userId])['cnt'] ?? 0))
            : 0;
        $user['current_plan'] = $this->currentPlanForUser($userId);
        $ratings = $this->ratingsSummaryForUser($userId);
        $user['rating_avg'] = $ratings['avg'];
        $user['rating_count'] = $ratings['count'];
        return $user;
    }

    private function ensureProfile(int $userId): void
    {
        if (!$this->tableExists('user_profiles')) {
            return;
        }

        $this->db->execute("
            INSERT INTO user_profiles (user_id, display_name, visibility_mode, created_at, updated_at)
            SELECT u.id, u.name, IFNULL(u.profile_visibility, 'public'), NOW(), NOW()
            FROM users u
            WHERE u.id = ?
              AND NOT EXISTS (SELECT 1 FROM user_profiles up WHERE up.user_id = u.id)
        ", [$userId]);
    }

    private function ensurePasswordResetsTable(): bool
    {
        if ($this->tableExists('password_resets')) {
            return true;
        }

        $this->db->execute("
            CREATE TABLE IF NOT EXISTS password_resets (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255) NOT NULL,
                token      VARCHAR(64)  NOT NULL,
                expires_at DATETIME     NOT NULL,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token),
                INDEX      idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->tableExists['password_resets'] = true;
        return true;
    }

    private function favoriteTargetExists(string $entityType, int $entityId): bool
    {
        if ($entityId < 1) {
            return false;
        }

        if ($entityType === 'article' && $this->tableExists('articles')) {
            return (bool)$this->db->fetch("SELECT id FROM articles WHERE id = ? LIMIT 1", [$entityId]);
        }
        if ($entityType === 'gallery' && $this->tableExists('gallery_items')) {
            return (bool)$this->db->fetch("SELECT id FROM gallery_items WHERE id = ? LIMIT 1", [$entityId]);
        }
        if ($entityType === 'user_profile' && $this->tableExists('users')) {
            return (bool)$this->db->fetch("SELECT id FROM users WHERE id = ? LIMIT 1", [$entityId]);
        }

        return false;
    }

    private function resolveFavorite(array $row): ?array
    {
        $entityType = (string)($row['entity_type'] ?? '');
        $entityId = (int)($row['entity_id'] ?? 0);

        if ($entityType === 'article' && $this->tableExists('articles')) {
            $item = $this->db->fetch("SELECT id, slug, title_en, title_ru FROM articles WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return null;
            }
            return [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $item['title_ru'] ?: ($item['title_en'] ?: ('Article #' . $entityId)),
                'url' => '/articles/' . rawurlencode((string)$item['slug']),
            ];
        }

        if ($entityType === 'gallery' && $this->tableExists('gallery_items')) {
            $item = $this->db->fetch("SELECT id, slug, title_en, title_ru, path_thumb FROM gallery_items WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return null;
            }
            return [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $item['title_ru'] ?: ($item['title_en'] ?: ('Work #' . $entityId)),
                'url' => '/gallery/' . rawurlencode((string)$item['slug']),
                'thumb' => $item['path_thumb'] ?? null,
            ];
        }

        if ($entityType === 'user_profile' && $this->tableExists('users')) {
            $item = $this->findFull($entityId);
            if (!$item) {
                return null;
            }
            return [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $item['display_name'] ?: ($item['name'] ?: ('User #' . $entityId)),
                'url' => '/users/' . rawurlencode((string)($item['username'] ?: $item['id'])),
                'thumb' => $item['avatar'] ?? null,
            ];
        }

        return null;
    }

    private function resolveCommentTarget(array $row): array
    {
        $entityType = (string)($row['entity_type'] ?? '');
        $entityId = (int)($row['entity_id'] ?? 0);
        $fallback = [
            'entity_label' => ucfirst(str_replace('_', ' ', $entityType ?: 'comment')),
            'entity_title' => '#' . $entityId,
            'entity_url' => null,
        ];

        if ($entityId < 1) {
            return $fallback;
        }

        if ($entityType === 'article' && $this->tableExists('articles')) {
            $item = $this->db->fetch("SELECT slug, title_en, title_ru FROM articles WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return $fallback;
            }
            return [
                'entity_label' => 'Article',
                'entity_title' => $item['title_ru'] ?: ($item['title_en'] ?: ('#' . $entityId)),
                'entity_url' => !empty($item['slug']) ? '/articles/' . rawurlencode((string)$item['slug']) : null,
            ];
        }

        if ($entityType === 'news' && $this->tableExists('news')) {
            $item = $this->db->fetch("SELECT slug, title_en, title_ru FROM news WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return $fallback;
            }
            return [
                'entity_label' => 'News',
                'entity_title' => $item['title_ru'] ?: ($item['title_en'] ?: ('#' . $entityId)),
                'entity_url' => !empty($item['slug']) ? '/news/' . rawurlencode((string)$item['slug']) : null,
            ];
        }

        if ($entityType === 'gallery' && $this->tableExists('gallery_items')) {
            $item = $this->db->fetch("SELECT slug, title_en, title_ru FROM gallery_items WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return $fallback;
            }
            $url = !empty($item['slug'])
                ? '/gallery/photo/' . rawurlencode((string)$item['slug'])
                : '/gallery/view?id=' . $entityId;
            return [
                'entity_label' => 'Gallery',
                'entity_title' => $item['title_ru'] ?: ($item['title_en'] ?: ('#' . $entityId)),
                'entity_url' => $url,
            ];
        }

        if ($entityType === 'page' && $this->tableExists('pages')) {
            $item = $this->db->fetch("SELECT slug, title_en, title_ru FROM pages WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return $fallback;
            }
            return [
                'entity_label' => 'Page',
                'entity_title' => $item['title_ru'] ?: ($item['title_en'] ?: ('#' . $entityId)),
                'entity_url' => !empty($item['slug']) ? '/page/' . rawurlencode((string)$item['slug']) : null,
            ];
        }

        if ($entityType === 'video' && $this->tableExists('video_items')) {
            $item = $this->db->fetch("SELECT slug, title_en, title_ru FROM video_items WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return $fallback;
            }
            return [
                'entity_label' => 'Video',
                'entity_title' => $item['title_ru'] ?: ($item['title_en'] ?: ('#' . $entityId)),
                'entity_url' => !empty($item['slug']) ? '/video/' . rawurlencode((string)$item['slug']) : null,
            ];
        }

        if ($entityType === 'user_profile' && $this->tableExists('users')) {
            $item = $this->findFull($entityId);
            if (!$item) {
                return $fallback;
            }
            return [
                'entity_label' => 'Profile',
                'entity_title' => $item['display_name'] ?: ($item['name'] ?: ('User #' . $entityId)),
                'entity_url' => '/users/' . rawurlencode((string)($item['username'] ?: $item['id'])),
            ];
        }

        return $fallback;
    }

    private function normalizeFavoriteEntityType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        $allowed = ['article', 'gallery', 'user_profile'];
        return in_array($entityType, $allowed, true) ? $entityType : '';
    }

    private function nullAuthorLinks(int $userId): void
    {
        foreach ([
            'articles',
            'gallery_items',
            'news',
            'body_modifications',
            'piercing',
        ] as $table) {
            if ($this->tableExists($table) && $this->columnExists($table, 'author_id')) {
                $this->db->execute("UPDATE {$table} SET author_id = NULL WHERE author_id = ?", [$userId]);
            }
        }

        if ($this->tableExists('comments') && $this->columnExists('comments', 'user_id')) {
            $this->db->execute("UPDATE comments SET user_id = NULL WHERE user_id = ?", [$userId]);
        }
    }

    private function clearLooseUserLinks(int $userId, string $email): void
    {
        if ($this->tableExists('login_logs') && $this->columnExists('login_logs', 'user_id')) {
            $this->db->execute("DELETE FROM login_logs WHERE user_id = ?", [$userId]);
        }

        if ($email !== '' && $this->tableExists('password_resets') && $this->columnExists('password_resets', 'email')) {
            $this->db->execute("DELETE FROM password_resets WHERE email = ?", [$email]);
        }
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExists)) {
            return $this->tableExists[$table];
        }

        $row = $this->db->fetch(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$table]
        );
        return $this->tableExists[$table] = (bool)$row;
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExists)) {
            return $this->columnExists[$key];
        }

        $row = $this->db->fetch("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
        return $this->columnExists[$key] = (bool)$row;
    }

    public function listMasterPlans(bool $activeOnly = true): array
    {
        if (!$this->tableExists('master_plans')) {
            return [];
        }

        $where = $activeOnly ? 'WHERE active = 1' : '';
        return $this->db->fetchAll("
            SELECT *
            FROM master_plans
            {$where}
            ORDER BY sort_order ASC, id ASC
        ");
    }

    public function findMasterPlan(int $id): ?array
    {
        if (!$this->tableExists('master_plans')) {
            return null;
        }

        return $this->db->fetch("SELECT * FROM master_plans WHERE id = ? LIMIT 1", [$id]);
    }

    public function saveMasterPlan(int $id, array $data): int
    {
        if (!$this->tableExists('master_plans')) {
            return 0;
        }

        $params = [
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => $data['description'] !== '' ? $data['description'] : null,
            ':active' => (int)$data['active'],
            ':sort_order' => (int)$data['sort_order'],
            ':price' => $data['price'] !== '' ? $data['price'] : null,
            ':currency' => $data['currency'] ?: 'USD',
            ':period_label' => $data['period_label'] !== '' ? $data['period_label'] : null,
            ':featured' => (int)$data['featured'],
            ':duration_days' => !empty($data['duration_days']) ? (int)$data['duration_days'] : null,
            ':gallery_limit' => max(0, (int)$data['gallery_limit']),
            ':pinned_works_limit' => max(0, (int)$data['pinned_works_limit']),
            ':allow_cover' => (int)$data['allow_cover'],
            ':allow_contacts' => (int)$data['allow_contacts'],
            ':allow_social_links' => (int)$data['allow_social_links'],
            ':allow_ratings' => (int)$data['allow_ratings'],
            ':priority_boost' => (int)$data['priority_boost'],
            ':capabilities_json' => $data['capabilities_json'],
        ];

        if ($id > 0) {
            $params[':id'] = $id;
            $this->db->execute("
                UPDATE master_plans
                SET name = :name,
                    slug = :slug,
                    description = :description,
                    active = :active,
                    sort_order = :sort_order,
                    price = :price,
                    currency = :currency,
                    period_label = :period_label,
                    featured = :featured,
                    duration_days = :duration_days,
                    gallery_limit = :gallery_limit,
                    pinned_works_limit = :pinned_works_limit,
                    allow_cover = :allow_cover,
                    allow_contacts = :allow_contacts,
                    allow_social_links = :allow_social_links,
                    allow_ratings = :allow_ratings,
                    priority_boost = :priority_boost,
                    capabilities_json = :capabilities_json,
                    updated_at = NOW()
                WHERE id = :id
            ", $params);
            return $id;
        }

        $this->db->execute("
            INSERT INTO master_plans (
                name, slug, description, active, sort_order, price, currency, period_label, featured,
                duration_days, gallery_limit, pinned_works_limit, allow_cover, allow_contacts,
                allow_social_links, allow_ratings, priority_boost, capabilities_json, created_at, updated_at
            ) VALUES (
                :name, :slug, :description, :active, :sort_order, :price, :currency, :period_label, :featured,
                :duration_days, :gallery_limit, :pinned_works_limit, :allow_cover, :allow_contacts,
                :allow_social_links, :allow_ratings, :priority_boost, :capabilities_json, NOW(), NOW()
            )
        ", $params);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function currentPlanForUser(int $userId): ?array
    {
        if (!$this->tableExists('user_master_plan_map') || !$this->tableExists('master_plans')) {
            return null;
        }

        return $this->db->fetch("
            SELECT ump.*, mp.*
            FROM user_master_plan_map ump
            JOIN master_plans mp ON mp.id = ump.plan_id
            WHERE ump.user_id = ?
              AND ump.status = 'active'
              AND (ump.expires_at IS NULL OR ump.expires_at >= NOW())
            ORDER BY ump.assigned_at DESC, ump.id DESC
            LIMIT 1
        ", [$userId]);
    }

    public function assignMasterPlan(
        int $userId,
        ?int $planId,
        ?string $expiresAt,
        string $status,
        ?int $assignedBy,
        ?string $adminNote
    ): void {
        if (!$this->tableExists('user_master_plan_map')) {
            return;
        }

        $this->db->execute("UPDATE user_master_plan_map SET status = 'inactive', updated_at = NOW() WHERE user_id = ? AND status = 'active'", [$userId]);
        if ($planId === null || $planId < 1) {
            return;
        }

        $this->db->execute("
            INSERT INTO user_master_plan_map (user_id, plan_id, assigned_at, expires_at, status, assigned_by, admin_note, created_at, updated_at)
            VALUES (?, ?, NOW(), ?, ?, ?, ?, NOW(), NOW())
        ", [
            $userId,
            $planId,
            $expiresAt ?: null,
            $status ?: 'active',
            $assignedBy,
            $adminNote !== '' ? $adminNote : null,
        ]);
    }

    public function planCapabilitiesForUser(int $userId): array
    {
        $plan = $this->currentPlanForUser($userId);
        if (!$plan) {
            return [];
        }
        $caps = json_decode((string)($plan['capabilities_json'] ?? ''), true);
        return is_array($caps) ? array_values(array_filter(array_map('strval', $caps))) : [];
    }
}
