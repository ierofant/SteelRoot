<?php

declare(strict_types=1);

namespace App\Services;

use Core\Cache;
use Core\Database;

class UserPublicSummaryService
{
    private const CACHE_PREFIX = 'user_public_summary_';

    private Database $db;
    private Cache $cache;
    private array $tableExists = [];
    private array $columnExists = [];

    public function __construct(Database $db, Cache $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function get(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . $userId;
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return !empty($cached['_missing']) ? null : $cached;
        }

        $summary = $this->load($userId);
        $this->cache->set($cacheKey, $summary ?? ['_missing' => true], 3600);

        return $summary;
    }

    public function invalidate(int $userId): void
    {
        if ($userId > 0) {
            $this->cache->delete(self::CACHE_PREFIX . $userId);
        }
    }

    public function hydrateRows(array $rows, string $authorIdKey = 'author_id'): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index] = $this->hydrateRow($row, $authorIdKey);
        }

        return $rows;
    }

    public function hydrateRow(array $row, string $authorIdKey = 'author_id'): array
    {
        $userId = (int)($row[$authorIdKey] ?? 0);
        if ($userId < 1) {
            return $row;
        }

        $summary = $this->get($userId);
        if ($summary === null) {
            return $row;
        }

        $row['author_name'] = $summary['display_name'];
        $row['author_avatar'] = $summary['avatar'];
        $row['author_username'] = $summary['username'];
        $row['author_profile_visibility'] = $summary['profile_visibility'];
        $row['author_signature'] = $summary['signature'];
        $row['author_profile_url'] = $summary['profile_url'];

        return $row;
    }

    private function load(int $userId): ?array
    {
        $profileJoin = $this->tableExists('user_profiles')
            ? ' LEFT JOIN user_profiles up ON up.user_id = u.id'
            : '';
        $profileSelect = $this->tableExists('user_profiles') && $this->columnExists('user_profiles', 'display_name')
            ? ', up.display_name AS profile_display_name'
            : ", '' AS profile_display_name";

        $row = $this->db->fetch(
            "SELECT
                u.id,
                u.name,
                u.username,
                u.avatar,
                u.profile_visibility,
                u.signature
                {$profileSelect}
             FROM users u
             {$profileJoin}
             WHERE u.id = ?
             LIMIT 1",
            [$userId]
        );

        if (!$row) {
            return null;
        }

        $displayName = trim((string)($row['profile_display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string)($row['name'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = trim((string)($row['username'] ?? ''));
        }

        $username = trim((string)($row['username'] ?? ''));

        return [
            'id' => (int)$row['id'],
            'display_name' => $displayName,
            'username' => $username,
            'avatar' => trim((string)($row['avatar'] ?? '')),
            'profile_visibility' => trim((string)($row['profile_visibility'] ?? 'public')) ?: 'public',
            'signature' => trim((string)($row['signature'] ?? '')),
            'profile_url' => $username !== '' ? '/users/' . rawurlencode($username) : '/users/' . (int)$row['id'],
        ];
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableExists)) {
            $this->tableExists[$table] = (bool)$this->db->fetch('SHOW TABLES LIKE ?', [$table]);
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
