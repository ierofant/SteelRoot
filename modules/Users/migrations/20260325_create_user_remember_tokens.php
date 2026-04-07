<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_remember_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                selector CHAR(18) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_user_remember_selector (selector),
                KEY idx_user_remember_user (user_id),
                KEY idx_user_remember_expires (expires_at),
                CONSTRAINT fk_user_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_remember_tokens");
    }
};
