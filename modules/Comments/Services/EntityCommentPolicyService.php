<?php
declare(strict_types=1);

namespace Modules\Comments\Services;

use Core\Database;

class EntityCommentPolicyService
{
    private Database $db;
    private array $tableExists = [];
    private array $columnExists = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function load(string $entityType, int $entityId): array
    {
        $policy = [
            'mode' => 'default',
            'group_ids' => [],
            'group_names' => [],
        ];

        $entity = $this->entityConfig($entityType);
        if ($entity === null || $entityId < 1) {
            return $policy;
        }

        if ($this->columnExists($entity['table'], 'comments_mode')) {
            $row = $this->db->fetch(
                "SELECT comments_mode FROM {$entity['table']} WHERE {$entity['id']} = ? LIMIT 1",
                [$entityId]
            );
            $policy['mode'] = $this->normalizeMode((string)($row['comments_mode'] ?? 'default'));
        }

        if ($this->tableExists('comment_entity_group_map') && $this->tableExists('user_groups')) {
            $rows = $this->db->fetchAll(
                "SELECT cegm.group_id, ug.name
                 FROM comment_entity_group_map cegm
                 JOIN user_groups ug ON ug.id = cegm.group_id
                 WHERE cegm.entity_type = ? AND cegm.entity_id = ?
                 ORDER BY ug.name ASC",
                [$entityType, $entityId]
            );

            $policy['group_ids'] = array_values(array_map(static fn(array $row): int => (int)$row['group_id'], $rows));
            $policy['group_names'] = array_values(array_map(static fn(array $row): string => (string)$row['name'], $rows));
        }

        return $policy;
    }

    public function save(string $entityType, int $entityId, string $mode, array $groupIds): void
    {
        $entity = $this->entityConfig($entityType);
        if ($entity === null || $entityId < 1) {
            return;
        }

        $mode = $this->normalizeMode($mode);
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn(int $id): bool => $id > 0)));

        if ($this->columnExists($entity['table'], 'comments_mode')) {
            $this->db->execute(
                "UPDATE {$entity['table']} SET comments_mode = ? WHERE {$entity['id']} = ?",
                [$mode, $entityId]
            );
        }

        if ($this->tableExists('comment_entity_group_map')) {
            $this->db->execute(
                "DELETE FROM comment_entity_group_map WHERE entity_type = ? AND entity_id = ?",
                [$entityType, $entityId]
            );
            foreach ($groupIds as $groupId) {
                $this->db->execute(
                    "INSERT INTO comment_entity_group_map (entity_type, entity_id, group_id, created_at)
                     VALUES (?, ?, ?, NOW())",
                    [$entityType, $entityId, $groupId]
                );
            }
        }
    }

    public function groups(): array
    {
        if (!$this->tableExists('user_groups')) {
            return [];
        }

        $enabledColumn = $this->columnExists('user_groups', 'enabled');
        return $this->db->fetchAll(
            "SELECT id, name, slug
             FROM user_groups" . ($enabledColumn ? ' WHERE enabled = 1' : '') . '
             ORDER BY is_system DESC, name ASC'
        );
    }

    public function evaluate(string $entityType, int $entityId, ?array $currentUser, bool $allowGuests, bool $isAdminSession = false): array
    {
        $policy = $this->load($entityType, $entityId);
        $groupNames = $policy['group_names'];

        if ($policy['mode'] === 'disabled') {
            return $policy + [
                'can_post' => false,
                'message' => (string)__('comments.policy.disabled'),
                'show_login_link' => false,
            ];
        }

        if ($isAdminSession || strtolower((string)($currentUser['role'] ?? '')) === 'admin') {
            return $policy + [
                'can_post' => true,
                'message' => '',
                'show_login_link' => false,
            ];
        }

        if ($policy['group_ids'] !== []) {
            if (!$currentUser) {
                return $policy + [
                    'can_post' => false,
                    'message' => (string)__('comments.policy.login_required_group'),
                    'show_login_link' => true,
                ];
            }

            $userGroupIds = $this->userGroupIds((int)($currentUser['id'] ?? 0));
            if (array_intersect($policy['group_ids'], $userGroupIds) === []) {
                $suffix = $groupNames !== []
                    ? ': ' . implode(', ', $groupNames)
                    : '';
                return $policy + [
                    'can_post' => false,
                    'message' => (string)__('comments.policy.group_restricted') . $suffix,
                    'show_login_link' => false,
                ];
            }
        }

        if (!$currentUser && !$allowGuests) {
            return $policy + [
                'can_post' => false,
                'message' => (string)__('comments.form.login_prompt'),
                'show_login_link' => true,
            ];
        }

        return $policy + [
            'can_post' => true,
            'message' => '',
            'show_login_link' => false,
        ];
    }

    private function entityConfig(string $entityType): ?array
    {
        return match ($entityType) {
            'article' => ['table' => 'articles', 'id' => 'id'],
            'news' => ['table' => 'news', 'id' => 'id'],
            'page' => ['table' => 'pages', 'id' => 'id'],
            default => null,
        };
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['default', 'enabled', 'disabled'], true) ? $mode : 'default';
    }

    private function userGroupIds(int $userId): array
    {
        if ($userId < 1 || !$this->tableExists('user_group_user_map')) {
            return [];
        }

        return array_values(array_map(
            static fn(array $row): int => (int)$row['group_id'],
            $this->db->fetchAll(
                "SELECT group_id FROM user_group_user_map WHERE user_id = ?",
                [$userId]
            )
        ));
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
            $this->columnExists[$key] = (bool)$this->db->fetch(
                "SHOW COLUMNS FROM {$table} LIKE ?",
                [$column]
            );
        }

        return $this->columnExists[$key];
    }
}
