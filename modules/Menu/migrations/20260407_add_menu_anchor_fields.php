<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $icon = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = 'icon' AND TABLE_SCHEMA = DATABASE()");
        if (!$icon) {
            $db->execute("ALTER TABLE settings_menu ADD COLUMN icon VARCHAR(64) NULL AFTER image_url");
        }

        $anchor = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = 'is_anchor' AND TABLE_SCHEMA = DATABASE()");
        if (!$anchor) {
            $db->execute("ALTER TABLE settings_menu ADD COLUMN is_anchor TINYINT(1) NOT NULL DEFAULT 0 AFTER icon");
        }
    }

    public function down(\Core\Database $db): void
    {
        $anchor = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = 'is_anchor' AND TABLE_SCHEMA = DATABASE()");
        if ($anchor) {
            $db->execute("ALTER TABLE settings_menu DROP COLUMN is_anchor");
        }

        $icon = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = 'icon' AND TABLE_SCHEMA = DATABASE()");
        if ($icon) {
            $db->execute("ALTER TABLE settings_menu DROP COLUMN icon");
        }
    }
};
