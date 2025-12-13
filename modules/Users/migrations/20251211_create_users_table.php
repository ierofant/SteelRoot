<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'user',
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                avatar VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_role (role),
                INDEX idx_status (status),
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $count = $db->fetch("SELECT COUNT(*) as c FROM users");
        if ((int)($count['c'] ?? 0) === 0) {
            $db->execute("
                INSERT INTO users (name, email, password, role, status, created_at, updated_at)
                VALUES ('Admin', 'admin@example.com', :pass, 'admin', 'active', NOW(), NOW())
            ", [':pass' => password_hash('admin123', PASSWORD_DEFAULT)]);
        }
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS users");
    }
};
