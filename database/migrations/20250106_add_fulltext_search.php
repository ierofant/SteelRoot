<?php
return new class {
    public function up(\Core\Database $db): void
    {
        // Articles fulltext
        $db->execute("ALTER TABLE articles ADD FULLTEXT idx_articles_fulltext (title_en, title_ru, body_en, body_ru)");
        // Gallery fulltext
        $db->execute("ALTER TABLE gallery_items ADD FULLTEXT idx_gallery_fulltext (title_en, title_ru, description_en, description_ru)");
        // Tags index for autocomplete
        $db->execute("CREATE INDEX idx_tags_name ON tags(name)");
        $db->execute("CREATE INDEX idx_tags_slug ON tags(slug)");
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("ALTER TABLE articles DROP INDEX idx_articles_fulltext");
        $db->execute("ALTER TABLE gallery_items DROP INDEX idx_gallery_fulltext");
        $db->execute("DROP INDEX idx_tags_name ON tags");
    }
};
