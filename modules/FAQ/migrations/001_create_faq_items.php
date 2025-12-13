<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS faq_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                question VARCHAR(255) DEFAULT NULL,
                answer TEXT NULL,
                status ENUM('draft','published') DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS faq_items");
    }
};