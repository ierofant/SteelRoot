<?php
return new class {
    public function up(\Core\Database $db): void
    {
        $sample = array (
  'question' => 'Sample question',
  'answer' => 'Sample answer text',
  'status' => 'draft',
);
        $exists = $db->fetch("SELECT COUNT(*) AS c FROM faq_items");
        if ((int)($exists['c'] ?? 0) === 0 && !empty($sample)) {
            $columns = array_keys($sample);
            $colSql = implode(', ', $columns) . ', created_at, updated_at';
            $placeholderSql = ':' . implode(', :', $columns) . ', NOW(), NOW()';
            $params = [];
            foreach ($sample as $k => $v) {
                $params[':' . $k] = $v;
            }
            $db->execute("INSERT INTO faq_items ({$colSql}) VALUES ({$placeholderSql})", $params);
        }
    }

    public function down(\Core\Database $db): void
    {
        $db->execute("DELETE FROM faq_items");
    }
};