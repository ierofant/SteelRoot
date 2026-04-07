<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $rows = $db->fetchAll("
            SELECT user_id, external_links_json
            FROM user_profiles
            WHERE external_links_json IS NOT NULL AND external_links_json <> ''
        ");

        foreach ($rows as $row) {
            $links = json_decode((string)($row['external_links_json'] ?? ''), true);
            if (!is_array($links) || !array_key_exists('website', $links)) {
                continue;
            }

            unset($links['website']);
            $payload = $links === [] ? null : json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $db->execute(
                "UPDATE user_profiles SET external_links_json = ? WHERE user_id = ?",
                [$payload, (int)$row['user_id']]
            );
        }
    }

    public function down(\Core\Database $db): void
    {
        // Removed website links are intentionally not restored.
    }
};
