<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items ADD COLUMN slug VARCHAR(191) NULL DEFAULT NULL AFTER id");
        $rows = $db->fetchAll("SELECT id, title_en, title_ru FROM gallery_items WHERE slug IS NULL OR slug = ''");
        $used = [];
        foreach ($rows as $row) {
            $slug = $this->slugify($row['title_ru'] ?: $row['title_en'] ?: ('photo-' . $row['id']));
            if ($slug === '') {
                $slug = 'photo-' . $row['id'];
            }
            if (isset($used[$slug])) {
                $slug .= '-' . $row['id'];
            }
            $used[$slug] = true;
            $db->execute("UPDATE gallery_items SET slug = ? WHERE id = ?", [$slug, $row['id']]);
        }
        $db->execute("ALTER TABLE gallery_items ADD UNIQUE KEY uniq_gallery_slug (slug)");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE gallery_items DROP INDEX uniq_gallery_slug");
        $db->execute("ALTER TABLE gallery_items DROP COLUMN slug");
    }

    private function slugify(string $value): string
    {
        $orig = trim($value);
        $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $orig);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$slug), '-'));
        if ($slug === '' && $orig !== '') {
            $slug = mb_strtolower(trim(preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $orig), '-'));
        }
        return $slug;
    }
};
