<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS gallery_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                path VARCHAR(255) NOT NULL,
                title_en VARCHAR(255) DEFAULT '',
                title_ru VARCHAR(255) DEFAULT '',
                description_en TEXT,
                description_ru TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS gallery_items");
    }
};
