<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS embed_forms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                fields MEDIUMTEXT NOT NULL,
                recipient_email VARCHAR(255) DEFAULT NULL,
                success_en VARCHAR(255) DEFAULT NULL,
                success_ru VARCHAR(255) DEFAULT NULL,
                enabled TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS embed_forms");
    }
};
