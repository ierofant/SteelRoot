<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $this->addColumn($db, 'canonical_url', "VARCHAR(1024) NULL AFTER description_en");
        $this->addColumn($db, 'image_url', "VARCHAR(1024) NULL AFTER canonical_url");
    }

    public function down(\Core\Database $db): void
    {
        $this->dropColumn($db, 'image_url');
        $this->dropColumn($db, 'canonical_url');
    }

    private function addColumn(\Core\Database $db, string $name, string $definition): void
    {
        $exists = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = ?",
            [$name]
        );
        if (!$exists) {
            $db->execute("ALTER TABLE settings_menu ADD COLUMN {$name} {$definition}");
        }
    }

    private function dropColumn(\Core\Database $db, string $name): void
    {
        $exists = $db->fetch(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'settings_menu' AND COLUMN_NAME = ?",
            [$name]
        );
        if ($exists) {
            $db->execute("ALTER TABLE settings_menu DROP COLUMN {$name}");
        }
    }
};
