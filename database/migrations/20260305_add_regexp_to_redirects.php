<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $row = $db->fetch("SHOW COLUMNS FROM redirects LIKE 'is_regexp'");
        if (!$row) {
            $db->execute("ALTER TABLE redirects ADD COLUMN is_regexp TINYINT(1) NOT NULL DEFAULT 0 AFTER active");
        }
        // Drop UNIQUE on from_path so identical regexp patterns can coexist with exact paths
        // (the unique index was fine for exact paths but prevents storing the same regexp variant)
        // We keep uniqueness enforcement in the service layer instead.
        try {
            $db->execute("ALTER TABLE redirects DROP INDEX from_path");
        } catch (\Throwable $e) {
            // index may not exist or already dropped
        }
    }

    public function down(\Core\Database $db): void
    {
        $row = $db->fetch("SHOW COLUMNS FROM redirects LIKE 'is_regexp'");
        if ($row) {
            $db->execute("ALTER TABLE redirects DROP COLUMN is_regexp");
        }
        try {
            $db->execute("ALTER TABLE redirects ADD UNIQUE INDEX from_path (from_path(512))");
        } catch (\Throwable $e) {}
    }
};
