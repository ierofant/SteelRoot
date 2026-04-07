<?php
return [
    'up' => function (\Core\Database $db): void {
        $db->execute("
            CREATE TABLE IF NOT EXISTS password_resets (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255) NOT NULL,
                token      VARCHAR(64)  NOT NULL,
                expires_at DATETIME     NOT NULL,
                created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token (token),
                INDEX      idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    },
    'down' => function (\Core\Database $db): void {
        $db->execute("DROP TABLE IF EXISTS password_resets");
    },
];
