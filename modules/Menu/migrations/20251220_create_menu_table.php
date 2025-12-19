<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS settings_menu (
                id INT AUTO_INCREMENT PRIMARY KEY,
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
                INDEX idx_position (position),
                INDEX idx_enabled (enabled),
                INDEX idx_admin_only (admin_only),
                INDEX idx_url (url)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS settings_menu");
    }
};
