<?php
declare(strict_types=1);

namespace Modules\Users\Services;

use Core\Database;

class CollectionService
{
    private Database $db;
    private array $tableExists = [];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function available(): bool
    {
        return $this->tableExists('user_collections') && $this->tableExists('user_collection_items');
    }

    public function listForUser(int $userId): array
    {
        if ($userId <= 0 || !$this->available()) {
            return [];
        }

        return $this->db->fetchAll("
            SELECT c.*,
                   (SELECT COUNT(*) FROM user_collection_items i WHERE i.collection_id = c.id) AS items_count
            FROM user_collections c
            WHERE c.user_id = ?
            ORDER BY c.updated_at DESC, c.id DESC
        ", [$userId]);
    }

    public function create(int $userId, string $title, ?string $description = null): int
    {
        if ($userId <= 0 || !$this->available()) {
            return 0;
        }

        $title = $this->normalizeTitle($title);
        if ($title === '') {
            return 0;
        }

        $this->db->execute("
            INSERT INTO user_collections (user_id, title, description, visibility, created_at, updated_at)
            VALUES (?, ?, ?, 'private', NOW(), NOW())
        ", [$userId, $title, $this->normalizeDescription($description)]);

        return (int)$this->db->pdo()->lastInsertId();
    }

    public function delete(int $userId, int $collectionId): void
    {
        if ($userId <= 0 || $collectionId <= 0 || !$this->available()) {
            return;
        }

        $this->db->execute("DELETE FROM user_collections WHERE id = ? AND user_id = ?", [$collectionId, $userId]);
    }

    public function findOwned(int $userId, int $collectionId): ?array
    {
        if ($userId <= 0 || $collectionId <= 0 || !$this->available()) {
            return null;
        }

        $row = $this->db->fetch("
            SELECT c.*,
                   (SELECT COUNT(*) FROM user_collection_items i WHERE i.collection_id = c.id) AS items_count
            FROM user_collections c
            WHERE c.id = ? AND c.user_id = ?
            LIMIT 1
        ", [$collectionId, $userId]);

        return $row ?: null;
    }

    public function itemsForCollection(int $userId, int $collectionId): array
    {
        if ($this->findOwned($userId, $collectionId) === null) {
            return [];
        }

        $rows = $this->db->fetchAll("
            SELECT *
            FROM user_collection_items
            WHERE collection_id = ?
            ORDER BY position ASC, id ASC
        ", [$collectionId]);

        $items = [];
        foreach ($rows as $row) {
            $resolved = $this->resolveItem($row);
            if ($resolved !== null) {
                $items[] = $resolved;
            }
        }

        return $items;
    }

    public function addItem(int $userId, int $collectionId, string $entityType, int $entityId, ?string $note = null): bool
    {
        if ($this->findOwned($userId, $collectionId) === null) {
            return false;
        }

        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0 || !$this->targetExists($entityType, $entityId)) {
            return false;
        }

        $nextPosition = (int)(($this->db->fetch("SELECT COALESCE(MAX(position), -1) AS max_pos FROM user_collection_items WHERE collection_id = ?", [$collectionId])['max_pos'] ?? -1)) + 1;
        try {
            $this->db->execute("
                INSERT INTO user_collection_items (collection_id, entity_type, entity_id, note, position, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$collectionId, $entityType, $entityId, $this->normalizeNote($note), $nextPosition]);
        } catch (\Throwable $e) {
            return false;
        }

        $this->touchCollection($collectionId, $entityType, $entityId);

        return true;
    }

    public function isSaved(int $userId, string $entityType, int $entityId): bool
    {
        if ($userId <= 0 || $entityId <= 0 || !$this->available()) {
            return false;
        }

        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '') {
            return false;
        }

        $row = $this->db->fetch("
            SELECT 1
            FROM user_collection_items uci
            JOIN user_collections uc ON uc.id = uci.collection_id
            WHERE uc.user_id = ? AND uci.entity_type = ? AND uci.entity_id = ?
            LIMIT 1
        ", [$userId, $entityType, $entityId]);

        return (bool)$row;
    }

    public function quickSave(int $userId, string $entityType, int $entityId): ?int
    {
        if ($userId <= 0 || !$this->available()) {
            return null;
        }

        $entityType = $this->normalizeEntityType($entityType);
        if ($entityType === '' || $entityId <= 0 || !$this->targetExists($entityType, $entityId)) {
            return null;
        }

        $collection = $this->db->fetch("
            SELECT id
            FROM user_collections
            WHERE user_id = ? AND title = ?
            ORDER BY id ASC
            LIMIT 1
        ", [$userId, 'Saved']);
        $collectionId = (int)($collection['id'] ?? 0);
        if ($collectionId <= 0) {
            $collectionId = $this->create($userId, 'Saved', 'Quick saved works and masters.');
        }
        if ($collectionId <= 0) {
            return null;
        }

        $this->addItem($userId, $collectionId, $entityType, $entityId);

        return $collectionId;
    }

    public function removeItem(int $userId, int $collectionId, int $itemId): void
    {
        if ($this->findOwned($userId, $collectionId) === null) {
            return;
        }

        $this->db->execute("DELETE FROM user_collection_items WHERE id = ? AND collection_id = ?", [$itemId, $collectionId]);
        $this->db->execute("UPDATE user_collections SET updated_at = NOW() WHERE id = ?", [$collectionId]);
    }

    private function resolveItem(array $row): ?array
    {
        $entityType = (string)($row['entity_type'] ?? '');
        $entityId = (int)($row['entity_id'] ?? 0);
        if ($entityId <= 0) {
            return null;
        }

        if ($entityType === 'gallery' && $this->tableExists('gallery_items')) {
            $item = $this->db->fetch("SELECT id, slug, title_en, title_ru, path_thumb, path_medium FROM gallery_items WHERE id = ? LIMIT 1", [$entityId]);
            if (!$item) {
                return null;
            }

            return [
                'id' => (int)$row['id'],
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => (string)($item['title_ru'] ?: ($item['title_en'] ?: ('Work #' . $entityId))),
                'url' => !empty($item['slug']) ? '/gallery/photo/' . rawurlencode((string)$item['slug']) : '/gallery/view?id=' . $entityId,
                'thumb' => (string)($item['path_thumb'] ?? ($item['path_medium'] ?? '')),
            ];
        }

        if ($entityType === 'user_profile' && $this->tableExists('users')) {
            $item = $this->db->fetch("
                SELECT u.id, u.username, u.name, u.avatar, up.display_name, up.city, up.specialization
                FROM users u
                LEFT JOIN user_profiles up ON up.user_id = u.id
                WHERE u.id = ?
                LIMIT 1
            ", [$entityId]);
            if (!$item) {
                return null;
            }

            $subtitle = trim(implode(' · ', array_filter([
                (string)($item['city'] ?? ''),
                (string)($item['specialization'] ?? ''),
            ], static fn(string $value): bool => $value !== '')));

            return [
                'id' => (int)$row['id'],
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => (string)($item['display_name'] ?: ($item['name'] ?: ('User #' . $entityId))),
                'url' => '/users/' . rawurlencode((string)($item['username'] ?: $item['id'])),
                'thumb' => (string)($item['avatar'] ?? ''),
                'subtitle' => $subtitle,
            ];
        }

        return null;
    }

    private function touchCollection(int $collectionId, string $entityType, int $entityId): void
    {
        $this->db->execute("
            UPDATE user_collections
            SET updated_at = NOW(),
                cover_entity_type = COALESCE(cover_entity_type, ?),
                cover_entity_id = COALESCE(cover_entity_id, ?)
            WHERE id = ?
        ", [$entityType, $entityId, $collectionId]);
    }

    private function targetExists(string $entityType, int $entityId): bool
    {
        if ($entityType === 'gallery' && $this->tableExists('gallery_items')) {
            return (bool)$this->db->fetch("SELECT id FROM gallery_items WHERE id = ? LIMIT 1", [$entityId]);
        }
        if ($entityType === 'user_profile' && $this->tableExists('users')) {
            return (bool)$this->db->fetch("SELECT id FROM users WHERE id = ? LIMIT 1", [$entityId]);
        }

        return false;
    }

    private function normalizeEntityType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        $allowed = ['gallery', 'user_profile'];

        return in_array($entityType, $allowed, true) ? $entityType : '';
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, 160);
        } else {
            $title = substr($title, 0, 160);
        }

        return $title;
    }

    private function normalizeDescription(?string $description): ?string
    {
        $description = trim((string)$description);
        if ($description === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $description = mb_substr($description, 0, 2000);
        } else {
            $description = substr($description, 0, 2000);
        }

        return $description;
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string)$note);
        if ($note === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 500);
        } else {
            $note = substr($note, 0, 500);
        }

        return $note;
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
}
