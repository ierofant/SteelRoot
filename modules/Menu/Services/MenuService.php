<?php
namespace Modules\Menu\Services;

use Core\Database;

class MenuService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getPublicMenu(string $locale, bool $isAdmin): array
    {
        return $this->getTree($locale, $isAdmin);
    }

    public function getTree(string $locale, bool $isAdmin): array
    {
        if (!$this->ensureReady()) {
            return $this->seedTree($locale, $isAdmin);
        }
        $this->ensureSeeded();
        $rows = $this->db->fetchAll(
            "SELECT * FROM settings_menu WHERE enabled = 1 ORDER BY position ASC, id ASC"
        );
        $itemsById = [];
        $childrenByParent = [];
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row, $locale);
            if (!empty($mapped['admin_only']) && !$isAdmin) {
                continue;
            }
            $mapped['children'] = [];
            $itemsById[$mapped['id']] = $mapped;
            if (!empty($mapped['parent_id']) && (int)$mapped['depth'] === 1) {
                $childrenByParent[$mapped['parent_id']][] = $mapped['id'];
            }
        }
        $tree = [];
        foreach ($itemsById as $id => $item) {
            if (empty($item['parent_id']) && (int)$item['depth'] === 0) {
                $tree[$id] = $item;
            }
        }
        foreach ($childrenByParent as $parentId => $childIds) {
            if (!isset($tree[$parentId])) {
                continue;
            }
            foreach ($childIds as $childId) {
                if (!isset($itemsById[$childId])) {
                    continue;
                }
                $tree[$parentId]['children'][] = $itemsById[$childId];
            }
        }
        return array_values($tree);
    }

    public function all(): array
    {
        if (!$this->ensureReady()) {
            return [];
        }
        $this->ensureSeeded();
        return $this->db->fetchAll("SELECT * FROM settings_menu ORDER BY position ASC, id ASC");
    }

    public function find(int $id): ?array
    {
        if (!$this->ensureReady()) {
            return null;
        }
        return $this->db->fetch("SELECT * FROM settings_menu WHERE id = ?", [$id]);
    }

    public function save(array $data, ?int $id = null): int
    {
        if (!$this->ensureReady()) {
            return 0;
        }
        $payload = [
            ':position' => (int)($data['position'] ?? 0),
            ':parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':depth' => (int)($data['depth'] ?? 0),
            ':url' => trim($data['url'] ?? ''),
            ':enabled' => !empty($data['enabled']) ? 1 : 0,
            ':admin_only' => !empty($data['admin_only']) ? 1 : 0,
            ':label_ru' => trim($data['label_ru'] ?? ''),
            ':label_en' => trim($data['label_en'] ?? ''),
            ':title_ru' => trim($data['title_ru'] ?? ''),
            ':title_en' => trim($data['title_en'] ?? ''),
            ':description_ru' => trim($data['description_ru'] ?? ''),
            ':description_en' => trim($data['description_en'] ?? ''),
            ':canonical_url' => trim($data['canonical_url'] ?? ''),
            ':image_url' => trim($data['image_url'] ?? ''),
        ];
        if ($id === null) {
            $this->db->execute("
                INSERT INTO settings_menu (position, parent_id, depth, url, enabled, admin_only, label_ru, label_en, title_ru, title_en, description_ru, description_en, canonical_url, image_url)
                VALUES (:position, :parent_id, :depth, :url, :enabled, :admin_only, :label_ru, :label_en, :title_ru, :title_en, :description_ru, :description_en, :canonical_url, :image_url)
            ", $payload);
            return (int)$this->db->pdo()->lastInsertId();
        }
        $payload[':id'] = $id;
        $this->db->execute("
            UPDATE settings_menu
            SET position = :position,
                parent_id = :parent_id,
                depth = :depth,
                url = :url,
                enabled = :enabled,
                admin_only = :admin_only,
                label_ru = :label_ru,
                label_en = :label_en,
                title_ru = :title_ru,
                title_en = :title_en,
                description_ru = :description_ru,
                description_en = :description_en,
                canonical_url = :canonical_url,
                image_url = :image_url
            WHERE id = :id
        ", $payload);
        return $id;
    }

    public function delete(int $id): void
    {
        if (!$this->ensureReady()) {
            return;
        }
        $this->db->execute("DELETE FROM settings_menu WHERE id = ?", [$id]);
    }

    public function toggle(int $id): void
    {
        if (!$this->ensureReady()) {
            return;
        }
        $this->db->execute("UPDATE settings_menu SET enabled = IF(enabled=1,0,1) WHERE id = ?", [$id]);
    }

    public function hasChildren(int $id): bool
    {
        if (!$this->ensureReady()) {
            return false;
        }
        $row = $this->db->fetch("SELECT COUNT(*) AS cnt FROM settings_menu WHERE parent_id = ?", [$id]);
        return (int)($row['cnt'] ?? 0) > 0;
    }

    public function reorder(array $positions): void
    {
        if (!$this->ensureReady()) {
            return;
        }
        foreach ($positions as $id => $pos) {
            $this->db->execute("UPDATE settings_menu SET position = ? WHERE id = ?", [(int)$pos, (int)$id]);
        }
    }

    private function mapRow(array $row, string $locale): array
    {
        $isRu = $locale === 'ru';
        return [
            'id' => (int)($row['id'] ?? 0),
            'url' => $row['url'] ?? '',
            'enabled' => (int)($row['enabled'] ?? 0) === 1,
            'admin_only' => (int)($row['admin_only'] ?? 0) === 1,
            'label' => $isRu ? ($row['label_ru'] ?? '') : ($row['label_en'] ?? ''),
            'label_ru' => $row['label_ru'] ?? '',
            'label_en' => $row['label_en'] ?? '',
            'title' => $isRu ? ($row['title_ru'] ?? '') : ($row['title_en'] ?? ''),
            'title_ru' => $row['title_ru'] ?? '',
            'title_en' => $row['title_en'] ?? '',
            'description' => $isRu ? ($row['description_ru'] ?? '') : ($row['description_en'] ?? ''),
            'description_ru' => $row['description_ru'] ?? '',
            'description_en' => $row['description_en'] ?? '',
            'position' => (int)($row['position'] ?? 0),
            'canonical_url' => $row['canonical_url'] ?? '',
            'image_url' => $row['image_url'] ?? '',
            'parent_id' => !empty($row['parent_id']) ? (int)$row['parent_id'] : null,
            'depth' => (int)($row['depth'] ?? 0),
        ];
    }

    private function ensureReady(): bool
    {
        try {
            $row = $this->db->fetch("SHOW TABLES LIKE 'settings_menu'");
            if ($row) {
                $this->ensureColumns();
                return true;
            }
            $this->createTable();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureSeeded(): void
    {
        $countRow = $this->db->fetch("SELECT COUNT(*) AS cnt FROM settings_menu");
        if ($countRow && (int)$countRow['cnt'] > 0) {
            return;
        }
        $seed = $this->seedData();
        $pos = 1;
        foreach ($seed as $item) {
            $this->db->execute("
                INSERT INTO settings_menu (position, url, enabled, admin_only, label_ru, label_en, title_ru, title_en, description_ru, description_en)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $pos++,
                $item['url'],
                !empty($item['enabled']) ? 1 : 0,
                !empty($item['admin_only']) ? 1 : 0,
                $item['label_ru'] ?? '',
                $item['label_en'] ?? '',
                $item['title_ru'] ?? '',
                $item['title_en'] ?? '',
                $item['description_ru'] ?? '',
                $item['description_en'] ?? '',
            ]);
        }
    }

    private function seedData(): array
    {
        $settingsAll = $GLOBALS['settingsAll'] ?? [];
        $adminPrefix = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
        $raw = $settingsAll['menu_schema'] ?? '';
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && !empty($decoded)) {
            $norm = [];
            foreach ($decoded as $item) {
                $url = trim($item['url'] ?? '');
                if ($url === '') {
                    continue;
                }
                $norm[] = [
                    'url' => $url,
                    'enabled' => !empty($item['enabled']),
                    'admin_only' => !empty($item['requires_admin'] ?? $item['admin_only'] ?? false),
                    'label_ru' => $item['label_ru'] ?? '',
                    'label_en' => $item['label_en'] ?? '',
                    'title_ru' => $item['title_ru'] ?? '',
                    'title_en' => $item['title_en'] ?? '',
                    'description_ru' => $item['description_ru'] ?? '',
                    'description_en' => $item['description_en'] ?? '',
                    'canonical_url' => $item['canonical_url'] ?? '',
                    'image_url' => $item['image_url'] ?? '',
                ];
            }
            if (!empty($norm)) {
                return $norm;
            }
        }
        return [
            ['label_ru' => 'Главная', 'label_en' => 'Home', 'url' => '/', 'enabled' => 1, 'admin_only' => 0],
            ['label_ru' => 'Контакты', 'label_en' => 'Contact', 'url' => '/contact', 'enabled' => 1, 'admin_only' => 0],
            ['label_ru' => 'Статьи', 'label_en' => 'Articles', 'url' => '/articles', 'enabled' => 1, 'admin_only' => 0],
            ['label_ru' => 'Галерея', 'label_en' => 'Gallery', 'url' => '/gallery', 'enabled' => 1, 'admin_only' => 0],
            ['label_ru' => 'Поиск', 'label_en' => 'Search', 'url' => '/search', 'enabled' => 1, 'admin_only' => 0],
            ['label_ru' => 'Админ', 'label_en' => 'Admin', 'url' => $adminPrefix, 'enabled' => 1, 'admin_only' => 1],
        ];
    }

    private function seedMapped(string $locale, bool $isAdmin): array
    {
        $items = [];
        foreach ($this->seedData() as $row) {
            $adminOnly = !empty($row['admin_only']);
            if ($adminOnly && !$isAdmin) {
                continue;
            }
            $items[] = [
                'url' => $row['url'] ?? '',
                'enabled' => !empty($row['enabled']),
                'admin_only' => $adminOnly,
                'label' => $locale === 'ru' ? ($row['label_ru'] ?? '') : ($row['label_en'] ?? ''),
                'label_ru' => $row['label_ru'] ?? '',
                'label_en' => $row['label_en'] ?? '',
                'title' => $locale === 'ru' ? ($row['title_ru'] ?? '') : ($row['title_en'] ?? ''),
                'description' => $locale === 'ru' ? ($row['description_ru'] ?? '') : ($row['description_en'] ?? ''),
                'parent_id' => null,
                'depth' => 0,
            ];
        }
        return $items;
    }

    private function seedTree(string $locale, bool $isAdmin): array
    {
        $items = [];
        foreach ($this->seedMapped($locale, $isAdmin) as $item) {
            $item['children'] = [];
            $items[] = $item;
        }
        return $items;
    }

    private function createTable(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS settings_menu (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT NULL DEFAULT NULL,
                depth TINYINT(1) NOT NULL DEFAULT 0,
                position INT NOT NULL DEFAULT 0,
                url VARCHAR(512) NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                admin_only TINYINT(1) NOT NULL DEFAULT 0,
                label_ru VARCHAR(255) NOT NULL,
                label_en VARCHAR(255) NOT NULL,
                title_ru VARCHAR(255) NULL,
                title_en VARCHAR(255) NULL,
                description_ru TEXT NULL,
                description_en TEXT NULL,
                canonical_url VARCHAR(1024) NULL,
                image_url VARCHAR(1024) NULL,
                INDEX idx_parent_id (parent_id),
                INDEX idx_position (position),
                INDEX idx_enabled (enabled),
                INDEX idx_admin_only (admin_only),
                INDEX idx_url (url)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function ensureColumns(): void
    {
        $cols = [
            'parent_id' => "ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id",
            'depth' => "ADD COLUMN depth TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_id",
            'canonical_url' => "ADD COLUMN canonical_url VARCHAR(1024) NULL AFTER description_en",
            'image_url' => "ADD COLUMN image_url VARCHAR(1024) NULL AFTER canonical_url",
        ];
        foreach ($cols as $name => $ddl) {
            $exists = $this->db->fetch(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = ? AND TABLE_SCHEMA = DATABASE()",
                [$name]
            );
            if (!$exists) {
                $this->db->execute("ALTER TABLE settings_menu {$ddl}");
            }
        }
        $idx = $this->db->fetch(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_NAME = 'settings_menu' AND INDEX_NAME = 'idx_parent_id' AND TABLE_SCHEMA = DATABASE()"
        );
        if (!$idx) {
            $this->db->execute("ALTER TABLE settings_menu ADD INDEX idx_parent_id (parent_id)");
        }
    }
}
