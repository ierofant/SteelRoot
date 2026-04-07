<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS video_categories (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                slug        VARCHAR(255) NOT NULL UNIQUE,
                name_en     VARCHAR(255) NOT NULL DEFAULT '',
                name_ru     VARCHAR(255) NOT NULL DEFAULT '',
                image_url   VARCHAR(1000) DEFAULT NULL,
                sort_order  INT NOT NULL DEFAULT 0,
                enabled     TINYINT(1) NOT NULL DEFAULT 1,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS video_categories");
    }
};
