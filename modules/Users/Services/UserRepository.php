<?php
namespace Modules\Users\Services;

use Core\Database;

class UserRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create(string $name, string $email, string $passwordHash, string $role, string $status, ?string $avatar = null): int
    {
        $this->db->execute("
            INSERT INTO users (name, email, password, role, status, avatar, created_at, updated_at)
            VALUES (:name, :email, :password, :role, :status, :avatar, NOW(), NOW())
        ", [
            ':name' => $name,
            ':email' => $email,
            ':password' => $passwordHash,
            ':role' => $role,
            ':status' => $status,
            ':avatar' => $avatar,
        ]);
        return (int)$this->db->pdo()->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $params = [$email];
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        return (bool)$this->db->fetch($sql, $params);
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'email', 'role', 'status', 'avatar'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $data[$key];
            }
        }
        if (!$fields) {
            return;
        }
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $this->db->execute($sql, $params);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->db->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $id]);
    }

    public function list(array $filters = []): array
    {
        $conds = [];
        $params = [];
        if (!empty($filters['email'])) {
            $conds[] = "email LIKE :email";
            $params[':email'] = '%' . $filters['email'] . '%';
        }
        if (!empty($filters['role'])) {
            $conds[] = "role = :role";
            $params[':role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $conds[] = "status = :status";
            $params[':status'] = $filters['status'];
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        return $this->db->fetchAll("
            SELECT u.*, (SELECT MAX(created_at) FROM login_logs l WHERE l.user_id = u.id) AS last_login
            FROM users u
            {$where}
            ORDER BY u.created_at DESC
            LIMIT 200
        ", $params);
    }

    public function logLogin(?int $userId, string $ip, string $ua): void
    {
        $this->db->execute("
            INSERT INTO login_logs (user_id, ip, ua, created_at)
            VALUES (:user_id, :ip, :ua, NOW())
        ", [
            ':user_id' => $userId,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
    }

    public function lastLogin(int $userId): ?string
    {
        $row = $this->db->fetch("SELECT created_at FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1", [$userId]);
        return $row['created_at'] ?? null;
    }
}
