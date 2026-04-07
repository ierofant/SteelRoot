<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $row = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profiles' AND COLUMN_NAME = 'artist_note' LIMIT 1"
        );
        if ($row) {
            return;
        }

        $db->execute("ALTER TABLE user_profiles ADD COLUMN artist_note VARCHAR(280) NULL AFTER bio");
    }

    public function down(\Core\Database $db): void
    {
        $row = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profiles' AND COLUMN_NAME = 'artist_note' LIMIT 1"
        );
        if (!$row) {
            return;
        }

        $db->execute("ALTER TABLE user_profiles DROP COLUMN artist_note");
    }
};
