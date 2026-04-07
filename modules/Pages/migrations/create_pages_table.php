<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(255) UNIQUE NOT NULL,
                title_en VARCHAR(255) NOT NULL,
                title_ru VARCHAR(255) NOT NULL,
                content_en MEDIUMTEXT,
                content_ru MEDIUMTEXT,
                meta_title_en VARCHAR(255) DEFAULT NULL,
                meta_title_ru VARCHAR(255) DEFAULT NULL,
                meta_description_en VARCHAR(500) DEFAULT NULL,
                meta_description_ru VARCHAR(500) DEFAULT NULL,
                visible TINYINT(1) DEFAULT 1,
                show_in_menu TINYINT(1) DEFAULT 0,
                menu_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS pages");
    }
};
