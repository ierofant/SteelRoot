<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS admin_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) UNIQUE NOT NULL,
                value TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create users table (required by other modules)
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

        // Create login_logs table (used by Admin module)
        $db->execute("
            CREATE TABLE IF NOT EXISTS login_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                ip VARCHAR(64) NOT NULL DEFAULT '',
                ua VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $exists = $db->fetch("SELECT id FROM admin_users WHERE username = ?", ['admin']);
        if (!$exists) {
            $hash = password_hash('admin', PASSWORD_DEFAULT);
            $db->execute("INSERT INTO admin_users (username, password) VALUES (?, ?)", ['admin', $hash]);
        }
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS login_logs");
        $db->execute("DROP TABLE IF EXISTS users");
        $db->execute("DROP TABLE IF EXISTS settings");
        $db->execute("DROP TABLE IF EXISTS admin_users");
    }
};
