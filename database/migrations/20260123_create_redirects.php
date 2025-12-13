<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS redirects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_path VARCHAR(512) NOT NULL UNIQUE,
                to_url VARCHAR(1024) NOT NULL,
                status_code SMALLINT NOT NULL DEFAULT 301,
                hits INT NOT NULL DEFAULT 0,
                last_hit DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS redirects");
    }
};
