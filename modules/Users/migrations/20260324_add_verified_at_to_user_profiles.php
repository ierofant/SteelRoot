<?php

return new class {
    public function up(\Core\Database $db): void
    {
        $col = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE 'verified_at'");
        if (!$col) {
            $db->execute("ALTER TABLE user_profiles ADD COLUMN verified_at DATETIME NULL AFTER is_verified");
        }

        $db->execute("UPDATE user_profiles SET verified_at = COALESCE(updated_at, created_at) WHERE is_verified = 1 AND verified_at IS NULL");
    }

    public function down(\Core\Database $db): void
    {
        $col = $db->fetch("SHOW COLUMNS FROM user_profiles LIKE 'verified_at'");
        if ($col) {
            $db->execute("ALTER TABLE user_profiles DROP COLUMN verified_at");
        }
    }
};
