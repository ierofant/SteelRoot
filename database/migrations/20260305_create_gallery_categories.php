<?php
use Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->execute("CREATE TABLE gallery_categories (
            id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug      VARCHAR(255) NOT NULL UNIQUE,
            name_en   VARCHAR(255) NOT NULL DEFAULT '',
            name_ru   VARCHAR(255) NOT NULL DEFAULT '',
            image_url VARCHAR(255) NOT NULL DEFAULT '',
            position  INT NOT NULL DEFAULT 0,
            enabled   TINYINT(1) NOT NULL DEFAULT 1
        )");
        $db->execute("ALTER TABLE gallery_items ADD COLUMN category_id INT UNSIGNED NULL AFTER category");
        $db->execute("ALTER TABLE gallery_items ADD CONSTRAINT fk_gallery_items_category
            FOREIGN KEY (category_id) REFERENCES gallery_categories(id) ON DELETE SET NULL");
        // Migrate existing string categories
        try {
            $rows = $db->fetchAll("SELECT DISTINCT category FROM gallery_items WHERE category IS NOT NULL AND category != ''");
        } catch (\Throwable $e) {
            $rows = [];
        }
        foreach ($rows as $row) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $row['category']), '-'));
            if (!$slug) {
                continue;
            }
            $db->execute(
                "INSERT IGNORE INTO gallery_categories (slug, name_en, name_ru) VALUES (?,?,?)",
                [$slug, $row['category'], $row['category']]
            );
            $db->execute(
                "UPDATE gallery_items gi
                 JOIN gallery_categories gc ON gc.slug = ?
                 SET gi.category_id = gc.id
                 WHERE gi.category = ?",
                [$slug, $row['category']]
            );
        }
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items DROP FOREIGN KEY fk_gallery_items_category");
        $db->execute("ALTER TABLE gallery_items DROP COLUMN category_id");
        $db->execute("DROP TABLE IF EXISTS gallery_categories");
    }
};
