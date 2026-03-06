<?php
use Core\Database;

return new class {
    public function up(Database $db): void
    {
        $db->execute("CREATE TABLE IF NOT EXISTS article_categories (
            id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug      VARCHAR(255) NOT NULL UNIQUE,
            name_en   VARCHAR(255) NOT NULL DEFAULT '',
            name_ru   VARCHAR(255) NOT NULL DEFAULT '',
            image_url VARCHAR(255) NOT NULL DEFAULT '',
            position  INT NOT NULL DEFAULT 0,
            enabled   TINYINT(1) NOT NULL DEFAULT 1
        )");
        if (!$db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND COLUMN_NAME = 'category_id'")) {
            $db->execute("ALTER TABLE articles ADD COLUMN category_id INT UNSIGNED NULL AFTER category");
        }
        if (!$db->fetch("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'articles' AND CONSTRAINT_NAME = 'fk_articles_category'")) {
            $db->execute("ALTER TABLE articles ADD CONSTRAINT fk_articles_category
                FOREIGN KEY (category_id) REFERENCES article_categories(id) ON DELETE SET NULL");
        }
        // Migrate existing string categories
        $rows = $db->fetchAll("SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != ''");
        foreach ($rows as $row) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $row['category']), '-'));
            if (!$slug) {
                continue;
            }
            $db->execute(
                "INSERT IGNORE INTO article_categories (slug, name_en, name_ru) VALUES (?,?,?)",
                [$slug, $row['category'], $row['category']]
            );
            $db->execute(
                "UPDATE articles a
                 JOIN article_categories c ON c.slug = ?
                 SET a.category_id = c.id
                 WHERE a.category = ?",
                [$slug, $row['category']]
            );
        }
    }

    public function down(Database $db): void
    {
        $db->execute("ALTER TABLE articles DROP FOREIGN KEY fk_articles_category");
        $db->execute("ALTER TABLE articles DROP COLUMN category_id");
        $db->execute("DROP TABLE IF EXISTS article_categories");
    }
};
