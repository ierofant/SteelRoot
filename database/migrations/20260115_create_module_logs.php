<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS migrations_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module VARCHAR(191) NOT NULL,
                migration VARCHAR(191) NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY module_migration (module, migration),
                INDEX module_idx (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS migrations_log");
    }
};
