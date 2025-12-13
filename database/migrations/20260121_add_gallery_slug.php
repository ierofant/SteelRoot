<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items ADD COLUMN slug VARCHAR(255) NULL AFTER id");
        // Populate slugs for existing records
        $rows = $db->fetchAll("SELECT id, title_en, title_ru FROM gallery_items WHERE slug IS NULL OR slug = ''");
        foreach ($rows as $row) {
            $base = $this->slugify($row['title_ru'] ?? $row['title_en'] ?? ('photo-' . $row['id']));
            if ($base === '') {
                $base = 'photo-' . $row['id'];
            }
            $slug = $base;
            $i = 1;
            while ($db->fetch("SELECT id FROM gallery_items WHERE slug = ? AND id != ?", [$slug, $row['id']])) {
                $slug = $base . '-' . $i;
                $i++;
            }
            $db->execute("UPDATE gallery_items SET slug = ? WHERE id = ?", [$slug, $row['id']]);
        }
        $db->execute("ALTER TABLE gallery_items ADD UNIQUE KEY gallery_slug_unique (slug)");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items DROP INDEX gallery_slug_unique");
        $db->execute("ALTER TABLE gallery_items DROP COLUMN slug");
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = strtolower(trim($value, '-'));
        return $value;
    }
};
