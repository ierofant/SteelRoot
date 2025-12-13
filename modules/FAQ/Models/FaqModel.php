<?php
namespace Modules\FAQ\Models;

use Core\Database;

class FaqModel
{
    private Database $db;
    private array $fields = array (
  0 => 'question',
  1 => 'answer',
  2 => 'status',
);
    private string $table = 'faq_items';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY updated_at DESC");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function create(array $data): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($this->fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $columns[] = $field;
            $placeholders[] = ':' . $field;
            $params[':' . $field] = $data[$field];
        }
        if (empty($columns)) {
            return 0;
        }
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $this->db->execute($sql, $params);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = [];
        $params = [':id' => $id];
        foreach ($this->fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = $field . ' = :' . $field;
            $params[':' . $field] = $data[$field];
        }
        if (empty($sets)) {
            return;
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id";
        $this->db->execute($sql, $params);
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }
}