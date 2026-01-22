<?php
declare(strict_types=1);
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                scopes TEXT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                last_used_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY api_keys_token_hash_unique (token_hash),
                INDEX api_keys_enabled_idx (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS api_keys");
    }
};
