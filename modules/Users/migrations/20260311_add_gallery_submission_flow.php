<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $this->addColumnIfMissing($db, 'status', "VARCHAR(32) NOT NULL DEFAULT 'approved'");
        $this->addColumnIfMissing($db, 'status_note', "TEXT NULL");
        $this->addColumnIfMissing($db, 'reviewed_at', "DATETIME NULL");
        $this->addColumnIfMissing($db, 'approved_at', "DATETIME NULL");
        $this->addColumnIfMissing($db, 'submitted_by_master', "TINYINT(1) NOT NULL DEFAULT 0");
        $this->addColumnIfMissing($db, 'storage_folder', "VARCHAR(190) NULL");

        $db->execute("UPDATE gallery_items SET status = 'approved' WHERE status IS NULL OR status = ''");
        $db->execute("UPDATE gallery_items SET approved_at = created_at WHERE approved_at IS NULL AND status = 'approved'");
    }

    public function down(\Core\Database $db): void
    {
        foreach (['storage_folder', 'submitted_by_master', 'approved_at', 'reviewed_at', 'status_note', 'status'] as $column) {
            $exists = $db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$column]);
            if ($exists) {
                $db->execute("ALTER TABLE gallery_items DROP COLUMN {$column}");
            }
        }
    }

    private function addColumnIfMissing(\Core\Database $db, string $column, string $definition): void
    {
        $exists = $db->fetch("SHOW COLUMNS FROM gallery_items LIKE ?", [$column]);
        if ($exists) {
            return;
        }

        $db->execute("ALTER TABLE gallery_items ADD COLUMN {$column} {$definition}");
    }
};
