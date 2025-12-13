<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(255) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->execute("
            CREATE TABLE IF NOT EXISTS taggables (
                tag_id INT NOT NULL,
                entity_type VARCHAR(64) NOT NULL,
                entity_id INT NOT NULL,
                PRIMARY KEY (tag_id, entity_type, entity_id),
                INDEX idx_taggables_entity (entity_type, entity_id),
                CONSTRAINT fk_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS taggables");
        $db->execute("DROP TABLE IF EXISTS tags");
    }
};
