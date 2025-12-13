<?php
namespace Database;

use Core\Database;

class MigrationRunner
{
    private string $path;
    private string $statusFile;
    private Database $db;

    public function __construct(string $path, Database $db)
    {
        $this->path = rtrim($path, '/');
        $this->db = $db;
        $this->statusFile = $this->path . '/.migrations.log';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
        if (!file_exists($this->statusFile)) {
            file_put_contents($this->statusFile, json_encode([]));
        }
    }

    public function up(): string
    {
        $log = $this->readLog();
        $output = [];
        foreach (glob($this->path . '/*.php') as $file) {
            $name = basename($file, '.php');
            if (!empty($log[$name])) {
                continue;
            }
            $migration = include $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up($this->db);
                $log[$name] = 'up';
                $output[] = "Applied {$name}";
            }
        }
        $this->writeLog($log);
        return implode("\n", $output) ?: 'No new migrations';
    }

    public function down(): string
    {
        $log = $this->readLog();
        $output = [];
        foreach (array_reverse(array_keys($log)) as $name) {
            $file = $this->path . '/' . $name . '.php';
            if (file_exists($file)) {
            $migration = include $file;
            if (is_object($migration) && method_exists($migration, 'down')) {
                $migration->down($this->db);
                unset($log[$name]);
                $output[] = "Rolled back {$name}";
                break;
            }
        }
        }
        $this->writeLog($log);
        return implode("\n", $output) ?: 'Nothing to roll back';
    }

    public function status(): string
    {
        $log = $this->readLog();
        if (empty($log)) {
            return 'No migrations applied';
        }
        $lines = [];
        foreach ($log as $name => $state) {
            $lines[] = "{$name}: {$state}";
        }
        return implode("\n", $lines);
    }

    private function readLog(): array
    {
        $json = file_get_contents($this->statusFile);
        return $json ? json_decode($json, true) : [];
    }

    private function writeLog(array $log): void
    {
        file_put_contents($this->statusFile, json_encode($log, JSON_PRETTY_PRINT));
    }
}
