<?php
/**
 * Remove ON UPDATE CURRENT_TIMESTAMP from `updated_at` on content tables.
 *
 * Problem: `updated_at` was defined with ON UPDATE CURRENT_TIMESTAMP, so any
 * UPDATE statement (including views counter increments) silently set updated_at
 * to NOW(). This produced false lastmod dates in sitemap.xml.
 *
 * All admin controllers already write `updated_at = NOW()` explicitly on real
 * edits, so the auto-update trigger is not needed and causes harm.
 */
return new class {
    private array $tables = [
        // table          => MODIFY clause (preserves nullability)
        'articles'      => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'gallery_items' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'news'          => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'video_items'   => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    public function up(\Core\Database $db): void
    {
        foreach ($this->tables as $table => $def) {
            try {
                $db->execute("ALTER TABLE `{$table}` MODIFY COLUMN `updated_at` {$def}");
            } catch (\Throwable $e) {
                // table may not exist in all environments — skip gracefully
            }
        }
    }

    public function down(\Core\Database $db): void
    {
        foreach ($this->tables as $table => $def) {
            try {
                $db->execute("ALTER TABLE `{$table}` MODIFY COLUMN `updated_at` {$def} ON UPDATE CURRENT_TIMESTAMP");
            } catch (\Throwable $e) {}
        }
    }
};
