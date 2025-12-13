<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS search_index (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                slug VARCHAR(255) DEFAULT '',
                title_en VARCHAR(255) DEFAULT '',
                title_ru VARCHAR(255) DEFAULT '',
                snippet_en TEXT NULL,
                snippet_ru TEXT NULL,
                url VARCHAR(255) DEFAULT '',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_entity (entity_type, entity_id),
                FULLTEXT KEY ft_title (title_en, title_ru, snippet_en, snippet_ru)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS search_index");
    }
};
