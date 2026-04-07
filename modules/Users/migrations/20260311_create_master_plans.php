<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS master_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                description TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                price DECIMAL(10,2) NULL,
                currency VARCHAR(12) NOT NULL DEFAULT 'USD',
                period_label VARCHAR(64) NULL,
                featured TINYINT(1) NOT NULL DEFAULT 0,
                duration_days INT NULL,
                gallery_limit INT NOT NULL DEFAULT 0,
                pinned_works_limit INT NOT NULL DEFAULT 0,
                allow_cover TINYINT(1) NOT NULL DEFAULT 0,
                allow_contacts TINYINT(1) NOT NULL DEFAULT 0,
                allow_social_links TINYINT(1) NOT NULL DEFAULT 0,
                allow_ratings TINYINT(1) NOT NULL DEFAULT 0,
                priority_boost INT NOT NULL DEFAULT 0,
                capabilities_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_master_plans_active_sort (active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS user_master_plan_map (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                plan_id INT NOT NULL,
                assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                assigned_by INT NULL,
                admin_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_plan_lookup (user_id, status, expires_at),
                CONSTRAINT fk_user_master_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_master_plan_plan FOREIGN KEY (plan_id) REFERENCES master_plans(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_master_plan_map");
        $db->execute("DROP TABLE IF EXISTS master_plans");
    }
};
