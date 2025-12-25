<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $cols = [
            'parent_id' => "ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id",
            'depth' => "ADD COLUMN depth TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_id",
        ];
        foreach ($cols as $name => $ddl) {
            $exists = $db->fetch(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = ? AND TABLE_SCHEMA = DATABASE()",
                [$name]
            );
            if (!$exists) {
                $db->execute("ALTER TABLE settings_menu {$ddl}");
            }
        }
        $idx = $db->fetch(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_NAME = 'settings_menu' AND INDEX_NAME = 'idx_parent_id' AND TABLE_SCHEMA = DATABASE()"
        );
        if (!$idx) {
            $db->execute("ALTER TABLE settings_menu ADD INDEX idx_parent_id (parent_id)");
        }
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE settings_menu DROP INDEX idx_parent_id");
        $db->execute("ALTER TABLE settings_menu DROP COLUMN depth");
        $db->execute("ALTER TABLE settings_menu DROP COLUMN parent_id");
    }
};
