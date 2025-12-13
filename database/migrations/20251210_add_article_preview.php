<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $this->addColumn($db, 'preview_en', "TEXT NULL AFTER title_ru");
        $this->addColumn($db, 'preview_ru', "TEXT NULL AFTER preview_en");
    }

    public function down(\Core\Database $db): void
    {
        $this->dropColumn($db, 'preview_en');
        $this->dropColumn($db, 'preview_ru');
    }

    private function addColumn(\Core\Database $db, string $name, string $definition): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = ?", [$name]);
        if (!$exists) {
            $db->execute("ALTER TABLE articles ADD COLUMN {$name} {$definition}");
        }
    }

    private function dropColumn(\Core\Database $db, string $name): void
    {
        $exists = $db->fetch("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_NAME = 'articles' AND COLUMN_NAME = ?", [$name]);
        if ($exists) {
            $db->execute("ALTER TABLE articles DROP COLUMN {$name}");
        }
    }
};
