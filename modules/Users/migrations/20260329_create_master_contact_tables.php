<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS master_contact_settings (
                master_id INT NOT NULL PRIMARY KEY,
                accept_requests TINYINT(1) NOT NULL DEFAULT 1,
                show_contact_cta TINYINT(1) NOT NULL DEFAULT 1,
                notification_email VARCHAR(255) NULL,
                telegram_notifications_enabled TINYINT(1) NOT NULL DEFAULT 0,
                telegram_chat_id BIGINT NULL,
                telegram_user_id BIGINT NULL,
                telegram_username VARCHAR(191) NULL,
                telegram_bound_at DATETIME NULL,
                telegram_bind_token VARCHAR(64) NULL,
                telegram_bind_expires_at DATETIME NULL,
                auto_reply_text VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_master_contact_settings_master
                    FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS contact_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                master_id INT NOT NULL,
                client_name VARCHAR(160) NOT NULL,
                client_contact VARCHAR(255) NOT NULL,
                preferred_contact_method VARCHAR(80) NULL,
                city VARCHAR(120) NULL,
                body_placement VARCHAR(120) NULL,
                approx_size VARCHAR(120) NULL,
                request_summary VARCHAR(255) NOT NULL,
                description TEXT NULL,
                budget VARCHAR(120) NULL,
                target_date VARCHAR(120) NULL,
                coverup_flag TINYINT(1) NOT NULL DEFAULT 0,
                extra_notes TEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'new',
                ip_hash CHAR(64) NOT NULL,
                user_agent_hash CHAR(64) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                viewed_at DATETIME NULL,
                KEY idx_contact_requests_master_status (master_id, status, created_at),
                CONSTRAINT fk_contact_requests_master
                    FOREIGN KEY (master_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS contact_request_files (
                id INT AUTO_INCREMENT PRIMARY KEY,
                request_id INT NOT NULL,
                path VARCHAR(500) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                file_size INT NOT NULL DEFAULT 0,
                image_width INT NOT NULL DEFAULT 0,
                image_height INT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_contact_request_files_request (request_id, sort_order, id),
                CONSTRAINT fk_contact_request_files_request
                    FOREIGN KEY (request_id) REFERENCES contact_requests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS contact_request_files");
        $db->execute("DROP TABLE IF EXISTS contact_requests");
        $db->execute("DROP TABLE IF EXISTS master_contact_settings");
    }
};
