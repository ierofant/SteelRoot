<?php
declare(strict_types=1);

namespace Modules\Users\Services;

use Core\Database;

class ProfilePanelService
{
    private const FEED_TTL_DAYS = 14;

    private Database $db;
    private array $tableExists = [];
    private array $columnExists = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function sectionsForUser(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $cutoff = $this->feedCutoffAt($userId);
        $sections = [];

        $showWorks = !array_key_exists('show_personal_feed_works', $user) || !empty($user['show_personal_feed_works']);
        $showMasters = !array_key_exists('show_personal_feed_masters', $user) || !empty($user['show_personal_feed_masters']);

        $newWorks = $showWorks ? $this->newWorksSince($cutoff) : [];
        if ($newWorks !== []) {
            $sections[] = [
                'title_key' => 'users.panel.section.new_works',
                'items' => $newWorks,
            ];
        }

        $masters = $showMasters ? $this->mastersForUser($user, $cutoff) : [];
        if ($masters !== []) {
            $sections[] = [
                'title_key' => 'users.panel.section.masters',
                'items' => $masters,
            ];
        }

        return $sections;
    }

    private function feedCutoffAt(int $userId): string
    {
        $ttlCutoff = date('Y-m-d H:i:s', strtotime('-' . self::FEED_TTL_DAYS . ' days'));
        if (!$this->tableExists('login_logs')) {
            return $ttlCutoff;
        }

        $row = $this->db->fetch(
            "SELECT created_at
             FROM login_logs
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 1 OFFSET 1",
            [$userId]
        );

        $previousVisit = (string)($row['created_at'] ?? '');
        if ($previousVisit === '' || strtotime($previousVisit) < strtotime($ttlCutoff)) {
            return $ttlCutoff;
        }

        return $previousVisit;
    }

    private function mastersForUser(array $user, string $cutoff): array
    {
        $merged = [];

        foreach ([$this->cityMasters((string)($user['city'] ?? ''), $cutoff), $this->newVerifiedMastersSince($cutoff)] as $batch) {
            foreach ($batch as $item) {
                $url = (string)($item['url'] ?? '');
                if ($url === '' || isset($merged[$url])) {
                    continue;
                }
                $merged[$url] = $item;
                if (count($merged) >= 4) {
                    break 2;
                }
            }
        }

        return array_values($merged);
    }

    private function newWorksSince(string $cutoff): array
    {
        if (!$this->tableExists('gallery_items')) {
            return [];
        }

        $statusSql = $this->columnExists('gallery_items', 'status') ? " AND gi.status = 'approved'" : '';
        $authorJoin = $this->columnExists('gallery_items', 'author_id') ? 'LEFT JOIN users u ON u.id = gi.author_id' : '';
        $authorSelect = $this->columnExists('gallery_items', 'author_id')
            ? ", COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), '') AS meta_label"
            : ", '' AS meta_label";

        $rows = $this->db->fetchAll(
            "SELECT gi.slug,
                    COALESCE(NULLIF(gi.title_ru, ''), NULLIF(gi.title_en, ''), CONCAT('#', gi.id)) AS title
                    {$authorSelect}
             FROM gallery_items gi
             {$authorJoin}
             WHERE gi.created_at >= ?{$statusSql}
             ORDER BY gi.created_at DESC, gi.id DESC
             LIMIT 4",
            [$cutoff]
        );

        return array_values(array_filter(array_map(static function (array $row): ?array {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                return null;
            }

            return [
                'url' => '/gallery/photo/' . rawurlencode($slug),
                'title' => (string)($row['title'] ?? ''),
                'meta' => trim((string)($row['meta_label'] ?? '')),
            ];
        }, $rows)));
    }

    private function newVerifiedMastersSince(string $cutoff): array
    {
        $rows = [];

        if ($this->tableExists('top_tattoo_master_summary')) {
            $rows = $this->db->fetchAll(
                "SELECT username,
                        display_name,
                        city
                 FROM top_tattoo_master_summary
                 WHERE is_verified = 1
                   AND profile_public = 1
                   AND updated_at >= ?
                 ORDER BY updated_at DESC, user_id DESC
                 LIMIT 4",
                [$cutoff]
            );
        } elseif ($this->tableExists('users') && $this->tableExists('user_profiles')) {
            $rows = $this->db->fetchAll(
                "SELECT u.username,
                        COALESCE(NULLIF(up.display_name, ''), NULLIF(u.name, ''), NULLIF(u.username, ''), CONCAT('User #', u.id)) AS display_name,
                        up.city
                 FROM users u
                 JOIN user_profiles up ON up.user_id = u.id
                 WHERE u.status = 'active'
                   AND COALESCE(NULLIF(up.visibility_mode, ''), NULLIF(u.profile_visibility, ''), 'public') = 'public'
                   AND COALESCE(up.is_verified, 0) = 1
                   AND u.created_at >= ?
                 ORDER BY u.created_at DESC, u.id DESC
                 LIMIT 4",
                [$cutoff]
            );
        }

        return array_values(array_filter(array_map(static function (array $row): ?array {
            $username = trim((string)($row['username'] ?? ''));
            if ($username === '') {
                return null;
            }

            return [
                'url' => '/users/' . rawurlencode($username),
                'title' => (string)($row['display_name'] ?: $username),
                'meta' => trim((string)($row['city'] ?? '')),
            ];
        }, $rows)));
    }

    private function cityMasters(string $city, string $cutoff): array
    {
        $city = trim($city);
        if ($city === '') {
            return [];
        }

        $normalized = mb_strtolower($city);
        $rows = [];

        if ($this->tableExists('top_tattoo_master_summary')) {
            $rows = $this->db->fetchAll(
                "SELECT username,
                        display_name,
                        city,
                        approved_works_count
                 FROM top_tattoo_master_summary
                 WHERE profile_public = 1
                   AND city IS NOT NULL
                   AND LOWER(city) = ?
                   AND updated_at >= ?
                 ORDER BY is_verified DESC, approved_works_count DESC, user_id DESC
                 LIMIT 4",
                [$normalized, $cutoff]
            );
        } elseif ($this->tableExists('users') && $this->tableExists('user_profiles')) {
            $worksCountSql = $this->tableExists('gallery_items') && $this->columnExists('gallery_items', 'author_id')
                ? "(SELECT COUNT(*) FROM gallery_items gi WHERE gi.author_id = u.id" .
                    ($this->columnExists('gallery_items', 'status') ? " AND gi.status = 'approved'" : '') .
                  ')'
                : '0';
            $rows = $this->db->fetchAll(
                "SELECT u.username,
                        COALESCE(NULLIF(up.display_name, ''), NULLIF(u.name, ''), NULLIF(u.username, ''), CONCAT('User #', u.id)) AS display_name,
                        up.city,
                        {$worksCountSql} AS approved_works_count
                 FROM users u
                 JOIN user_profiles up ON up.user_id = u.id
                 WHERE u.status = 'active'
                   AND COALESCE(NULLIF(up.visibility_mode, ''), NULLIF(u.profile_visibility, ''), 'public') = 'public'
                   AND up.city IS NOT NULL
                   AND LOWER(up.city) = ?
                   AND u.created_at >= ?
                 ORDER BY COALESCE(up.is_verified, 0) DESC, approved_works_count DESC, u.id DESC
                 LIMIT 4",
                [$normalized, $cutoff]
            );
        }

        return array_values(array_filter(array_map(static function (array $row) use ($city): ?array {
            $username = trim((string)($row['username'] ?? ''));
            if ($username === '') {
                return null;
            }

            return [
                'url' => '/users/' . rawurlencode($username),
                'title' => (string)($row['display_name'] ?: $username),
                'meta' => $city,
            ];
        }, $rows)));
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExists)) {
            try {
                $this->tableExists[$table] = (bool)$this->db->fetch("SHOW TABLES LIKE ?", [$table]);
            } catch (\Throwable $e) {
                $this->tableExists[$table] = false;
            }
        }

        return $this->tableExists[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columnExists)) {
            try {
                $this->columnExists[$key] = (bool)$this->db->fetch(
                    "SHOW COLUMNS FROM {$table} LIKE ?",
                    [$column]
                );
            } catch (\Throwable $e) {
                $this->columnExists[$key] = false;
            }
        }

        return $this->columnExists[$key];
    }
}
