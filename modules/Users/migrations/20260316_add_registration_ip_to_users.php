<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $row = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'registration_ip' LIMIT 1"
        );
        if ($row) {
            return;
        }

        $db->execute("ALTER TABLE users ADD COLUMN registration_ip VARCHAR(45) NULL AFTER signature");
    }

    public function down(\Core\Database $db): void
    {
        $row = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'registration_ip' LIMIT 1"
        );
        if (!$row) {
            return;
        }

        $db->execute("ALTER TABLE users DROP COLUMN registration_ip");
    }
};
