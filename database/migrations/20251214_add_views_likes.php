<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE articles ADD COLUMN views INT NOT NULL DEFAULT 0, ADD COLUMN likes INT NOT NULL DEFAULT 0");
        $db->execute("ALTER TABLE gallery_items ADD COLUMN views INT NOT NULL DEFAULT 0, ADD COLUMN likes INT NOT NULL DEFAULT 0");
        $db->execute("
            CREATE TABLE IF NOT EXISTS likes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                fingerprint VARCHAR(64) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_like (entity_type, entity_id, fingerprint)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE articles DROP COLUMN likes, DROP COLUMN views");
        $db->execute("ALTER TABLE gallery_items DROP COLUMN likes, DROP COLUMN views");
        $db->execute("DROP TABLE IF EXISTS likes");
    }
};
