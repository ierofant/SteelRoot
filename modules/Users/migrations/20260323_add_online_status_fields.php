<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $col = $db->fetch("SHOW COLUMNS FROM users LIKE 'last_seen_at'");
        if (!$col) {
            $db->execute("ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER updated_at");
        }

        $col2 = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE 'hide_online_status'");
        if (!$col2) {
            $db->execute("ALTER TABLE user_profiles ADD COLUMN hide_online_status TINYINT(1) NOT NULL DEFAULT 0 AFTER show_works");
        }
    }

    public function down(\Core\Database $db): void
    {
        $col = $db->fetch("SHOW COLUMNS FROM users LIKE 'last_seen_at'");
        if ($col) {
            $db->execute("ALTER TABLE users DROP COLUMN last_seen_at");
        }

        $col2 = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE 'hide_online_status'");
        if ($col2) {
            $db->execute("ALTER TABLE user_profiles DROP COLUMN hide_online_status");
        }
    }
};
