<?php
namespace Core;

class ModuleMigrationRunner
{
    private string $path;
    private Database $db;
    private string $moduleSlug;

    public function __construct(string $path, Database $db, string $moduleSlug)
    {
        $this->path = rtrim($path, '/');
        $this->db = $db;
        $this->moduleSlug = $moduleSlug;
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }

    public function up(): array
    {
        $this->ensureLogTable();
        $applied = $this->applied();
        $logs = [];
        $files = $this->migrationFiles();
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = include $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up($this->db);
                $this->db->execute(
                    "INSERT INTO migrations_log (module, migration, applied_at) VALUES (:module, :migration, NOW())",
                    [':module' => $this->moduleSlug, ':migration' => $name]
                );
                $logs[] = "Applied {$name}";
            }
        }
        return $logs ?: ['No new migrations'];
    }

    public function down(int $steps = 1): array
    {
        $this->ensureLogTable();
        $applied = $this->db->fetchAll(
            "SELECT migration FROM migrations_log WHERE module = :module ORDER BY applied_at DESC",
            [':module' => $this->moduleSlug]
        );
        $logs = [];
        $toRollback = array_slice(array_column($applied, 'migration'), 0, $steps);
        foreach ($toRollback as $name) {
            $file = $this->path . '/' . $name . '.php';
            if (!file_exists($file)) {
                $this->db->execute("DELETE FROM migrations_log WHERE module = :module AND migration = :migration", [
                    ':module' => $this->moduleSlug,
                    ':migration' => $name,
                ]);
                continue;
            }
            $migration = include $file;
            if (is_object($migration) && method_exists($migration, 'down')) {
                $migration->down($this->db);
                $this->db->execute("DELETE FROM migrations_log WHERE module = :module AND migration = :migration", [
                    ':module' => $this->moduleSlug,
                    ':migration' => $name,
                ]);
                $logs[] = "Rolled back {$name}";
            }
        }
        return $logs ?: ['Nothing to roll back'];
    }

    public function reset(): array
    {
        $logs = [];
        while (true) {
            $step = $this->down(1);
            if ($step === ['Nothing to roll back']) {
                break;
            }
            $logs = array_merge($logs, $step);
        }
        return $logs;
    }

    public function status(): array
    {
        $this->ensureLogTable();
        $rows = $this->db->fetchAll(
            "SELECT migration, applied_at FROM migrations_log WHERE module = :module ORDER BY applied_at ASC",
            [':module' => $this->moduleSlug]
        );
        if (!$rows) {
            return ['No migrations applied'];
        }
        return array_map(function ($row) {
            return ($row['migration'] ?? 'unknown') . ' @ ' . ($row['applied_at'] ?? '');
        }, $rows);
    }

    private function migrationFiles(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function applied(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT migration FROM migrations_log WHERE module = :module",
            [':module' => $this->moduleSlug]
        );
        return array_column($rows, 'migration');
    }

    private function ensureLogTable(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS migrations_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module VARCHAR(191) NOT NULL,
                migration VARCHAR(191) NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY module_migration_unique (module, migration),
                INDEX module_idx (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
