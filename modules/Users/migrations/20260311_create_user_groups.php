<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS user_groups (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL UNIQUE,
                description TEXT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_enabled (enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS user_group_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                permission_key VARCHAR(120) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_group_permission (group_id, permission_key),
                CONSTRAINT fk_user_group_permissions_group
                    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->execute("
            CREATE TABLE IF NOT EXISTS user_group_user_map (
                user_id INT NOT NULL,
                group_id INT NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, group_id),
                INDEX idx_user_group_primary (user_id, is_primary),
                CONSTRAINT fk_user_group_user_map_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_user_group_user_map_group
                    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $groups = [
            'user' => ['User', 'Default site member', 1, ['profile.extended', 'favorites.manage', 'comments.profile']],
            'master' => ['Master', 'Tattoo master profile', 0, ['profile.extended', 'gallery.submit', 'favorites.manage', 'comments.profile', 'profile.contacts', 'profile.links']],
            'moderator' => ['Moderator', 'Moderation staff', 1, ['comments.moderate', 'profile.moderate']],
            'editor' => ['Editor', 'Editorial team', 1, ['gallery.publish', 'profile.extended']],
            'vip' => ['VIP', 'Featured member profile', 0, ['profile.extended', 'favorites.manage', 'profile.badges']],
            'verified_master' => ['Verified master', 'Approved tattoo master', 0, ['profile.extended', 'gallery.submit', 'gallery.publish', 'favorites.manage', 'comments.profile', 'profile.contacts', 'profile.links', 'profile.verified']],
            'curator' => ['Curator', 'Curated content staff', 0, ['comments.moderate', 'profile.moderate', 'gallery.publish']],
        ];

        foreach ($groups as $slug => [$name, $description, $system, $permissions]) {
            $db->execute("
                INSERT INTO user_groups (name, slug, description, enabled, is_system, created_at, updated_at)
                VALUES (:name, :slug, :description, 1, :is_system, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    is_system = VALUES(is_system),
                    enabled = 1,
                    updated_at = NOW()
            ", [
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':is_system' => $system,
            ]);

            $group = $db->fetch("SELECT id FROM user_groups WHERE slug = ? LIMIT 1", [$slug]);
            if (!$group) {
                continue;
            }
            foreach ($permissions as $permission) {
                $db->execute("
                    INSERT IGNORE INTO user_group_permissions (group_id, permission_key, created_at)
                    VALUES (?, ?, NOW())
                ", [(int)$group['id'], $permission]);
            }
        }
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DROP TABLE IF EXISTS user_group_user_map");
        $db->execute("DROP TABLE IF EXISTS user_group_permissions");
        $db->execute("DROP TABLE IF EXISTS user_groups");
    }
};
