<?php
namespace Core;

/**
 * Generates CRUD scaffolding for declarative modules with schema.json.
 */
class ModuleSchemaScaffolder
{
    private string $modulePath;
    private array $definition;
    private array $schema;
    private string $entity;
    private string $table;
    private string $classBase;
    private string $slug;
    private string $namespace;

    public function __construct(string $modulePath, array $definition, array $schema)
    {
        $this->modulePath = rtrim($modulePath, '/');
        $this->definition = $definition;
        $this->schema = $schema;
        $this->entity = $schema['entity'] ?? strtolower(basename($modulePath));
        $this->table = $schema['table'] ?? ($this->entity . '_items');
        $this->classBase = $this->studly($this->entity);
        $this->slug = $definition['slug'] ?? strtolower($this->entity);
        $this->namespace = basename($modulePath);
    }

    public function generate(): void
    {
        $this->ensureDirs();
        $this->generateMigration();
        $this->generateModel();
        $this->generateController();
        $this->generateRoutes();
        $this->generateViews();
        $this->generateLang();
        $this->generateAssets();
        $this->generateSeed();
    }

    private function ensureDirs(): void
    {
        $paths = [
            $this->modulePath . '/Controllers',
            $this->modulePath . '/Models',
            $this->modulePath . '/views/admin',
            $this->modulePath . '/lang/en',
            $this->modulePath . '/lang/ru',
            $this->modulePath . '/assets',
            $this->modulePath . '/migrations',
        ];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

    private function generateMigration(): void
    {
        $pattern = $this->modulePath . '/migrations/*create_' . $this->table . '.php';
        $existing = glob($pattern) ?: [];
        if (!empty($existing)) {
            return;
        }
        $fields = $this->schema['fields'] ?? [];
        $columns = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'string';
            if ($name === '') {
                continue;
            }
            $columns[] = $this->columnSql($name, $type, $field);
        }
        $columns[] = "created_at DATETIME DEFAULT CURRENT_TIMESTAMP";
        $columns[] = "updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        $colsSql = implode(",\n                ", $columns);
        $class = <<<PHP
<?php
return new class {
    public function up(\\Core\\Database \$db): void
    {
        \$db->execute("
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                {$colsSql}
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\\Core\\Database \$db): void
    {
        \$db->execute("DROP TABLE IF EXISTS {$this->table}");
    }
};
PHP;
        $file = $this->modulePath . '/migrations/001_create_' . $this->table . '.php';
        $this->writeIfMissing($file, $class);
    }

    private function generateModel(): void
    {
        $file = $this->modulePath . '/Models/' . $this->classBase . 'Model.php';
        if (file_exists($file)) {
            return;
        }
        $fields = $this->schema['fields'] ?? [];
        $fieldNames = array_map(fn($f) => $f['name'] ?? '', $fields);
        $fieldArray = var_export(array_values(array_filter($fieldNames)), true);
        $content = <<<PHP
<?php
namespace Modules\\{$this->namespace}\\Models;

use Core\\Database;

class {$this->classBase}Model
{
    private Database \$db;
    private array \$fields = {$fieldArray};
    private string \$table = '{$this->table}';

    public function __construct(Database \$db)
    {
        \$this->db = \$db;
    }

    public function all(): array
    {
        return \$this->db->fetchAll("SELECT * FROM {\$this->table} ORDER BY updated_at DESC");
    }

    public function find(int \$id): ?array
    {
        return \$this->db->fetch("SELECT * FROM {\$this->table} WHERE id = ?", [\$id]);
    }

    public function create(array \$data): int
    {
        \$columns = [];
        \$placeholders = [];
        \$params = [];
        foreach (\$this->fields as \$field) {
            if (!array_key_exists(\$field, \$data)) {
                continue;
            }
            \$columns[] = \$field;
            \$placeholders[] = ':' . \$field;
            \$params[':' . \$field] = \$data[\$field];
        }
        if (empty(\$columns)) {
            return 0;
        }
        \$sql = "INSERT INTO {\$this->table} (" . implode(', ', \$columns) . ") VALUES (" . implode(', ', \$placeholders) . ")";
        \$this->db->execute(\$sql, \$params);
        return (int)\$this->db->pdo()->lastInsertId();
    }

    public function update(int \$id, array \$data): void
    {
        \$sets = [];
        \$params = [':id' => \$id];
        foreach (\$this->fields as \$field) {
            if (!array_key_exists(\$field, \$data)) {
                continue;
            }
            \$sets[] = \$field . ' = :' . \$field;
            \$params[':' . \$field] = \$data[\$field];
        }
        if (empty(\$sets)) {
            return;
        }
        \$sql = "UPDATE {\$this->table} SET " . implode(', ', \$sets) . ", updated_at = NOW() WHERE id = :id";
        \$this->db->execute(\$sql, \$params);
    }

    public function delete(int \$id): void
    {
        \$this->db->execute("DELETE FROM {\$this->table} WHERE id = ?", [\$id]);
    }
}
PHP;
        $this->writeIfMissing($file, $content);
    }

    private function generateController(): void
    {
        $file = $this->modulePath . '/Controllers/' . $this->classBase . 'AdminController.php';
        if (file_exists($file)) {
            return;
        }
        $fields = $this->schema['fields'] ?? [];
        $list = $this->schema['admin']['list'] ?? [];
        $form = $this->schema['admin']['edit_form'] ?? [];
        $entitySlug = $this->schema['entity'] ?? $this->slug;
        $fieldsExport = var_export($fields, true);
        $listExport = var_export($list, true);
        $formExport = var_export($form, true);
        $content = <<<PHP
<?php
namespace Modules\\{$this->namespace}\\Controllers;

use Core\\Container;
use Core\\Csrf;
use Core\\Database;
use Core\\Request;
use Core\\Response;
use Modules\\{$this->namespace}\\Models\\{$this->classBase}Model;

class {$this->classBase}AdminController
{
    private Container \$container;
    private Database \$db;
    private {$this->classBase}Model \$model;
    private array \$fields = {$fieldsExport};
    private array \$listColumns = {$listExport};
    private array \$formFields = {$formExport};

    public function __construct(Container \$container)
    {
        \$this->container = \$container;
        \$this->db = \$container->get(Database::class);
        \$this->model = new {$this->classBase}Model(\$this->db);
    }

    public function index(Request \$request): Response
    {
        \$items = \$this->model->all();
        \$html = \$this->container->get('renderer')->render('@' . strtoupper('{$this->slug}') . '/admin/index', [
            'title' => '{$this->classBase}',
            'items' => \$items,
            'listColumns' => \$this->listColumns,
            'schemaFields' => \$this->fields,
            'csrf' => Csrf::token('{$this->slug}_admin'),
        ]);
        return new Response(\$html);
    }

    public function create(Request \$request): Response
    {
        \$html = \$this->container->get('renderer')->render('@' . strtoupper('{$this->slug}') . '/admin/edit', [
            'title' => 'Create {$this->classBase}',
            'schemaFields' => \$this->fields,
            'formFields' => \$this->formFields,
            'csrf' => Csrf::token('{$this->slug}_admin'),
            'item' => null,
        ]);
        return new Response(\$html);
    }

    public function store(Request \$request): Response
    {
        if (!Csrf::check('{$this->slug}_admin', \$request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        \$data = \$this->sanitize(\$request->body);
        \$error = \$this->validate(\$data);
        if (\$error) {
            return new Response(\$error, 422);
        }
        \$this->model->create(\$data);
        return \$this->redirect();
    }

    public function edit(Request \$request): Response
    {
        \$id = (int)(\$request->params['id'] ?? 0);
        \$item = \$this->model->find(\$id);
        \$html = \$this->container->get('renderer')->render('@' . strtoupper('{$this->slug}') . '/admin/edit', [
            'title' => 'Edit {$this->classBase}',
            'schemaFields' => \$this->fields,
            'formFields' => \$this->formFields,
            'csrf' => Csrf::token('{$this->slug}_admin'),
            'item' => \$item,
        ]);
        return new Response(\$html);
    }

    public function update(Request \$request): Response
    {
        if (!Csrf::check('{$this->slug}_admin', \$request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        \$id = (int)(\$request->params['id'] ?? 0);
        \$data = \$this->sanitize(\$request->body);
        \$error = \$this->validate(\$data);
        if (\$error) {
            return new Response(\$error, 422);
        }
        \$this->model->update(\$id, \$data);
        return \$this->redirect();
    }

    public function delete(Request \$request): Response
    {
        if (!Csrf::check('{$this->slug}_admin', \$request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        \$id = (int)(\$request->params['id'] ?? 0);
        \$this->model->delete(\$id);
        return \$this->redirect();
    }

    private function redirect(): Response
    {
        \$prefix = \$this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => \$prefix . '/{$entitySlug}']);
    }

    private function sanitize(array \$input): array
    {
        \$data = [];
        foreach (\$this->fields as \$field) {
            \$name = \$field['name'] ?? null;
            if (!\$name) {
                continue;
            }
            \$data[\$name] = trim((string)(\$input[\$name] ?? ''));
        }
        return \$data;
    }

    private function validate(array \$data): ?string
    {
        foreach (\$this->fields as \$field) {
            \$name = \$field['name'] ?? '';
            if (\$name === '') {
                continue;
            }
            \$required = !empty(\$field['required']);
            if (\$required && (\$data[\$name] ?? '') === '') {
                return "Field {\$name} is required";
            }
            if ((\$field['type'] ?? '') === 'enum' && !empty(\$field['values'])) {
                if (\$data[\$name] !== '' && !in_array(\$data[\$name], \$field['values'], true)) {
                    return "Field {\$name} has invalid value";
                }
            }
        }
        return null;
    }
}
PHP;
        $this->writeIfMissing($file, $content);
    }

    private function generateRoutes(): void
    {
        $file = $this->modulePath . '/routes.php';
        if (file_exists($file)) {
            return;
        }
        $slug = $this->schema['entity'] ?? $this->slug;
        $controller = "Modules\\\\{$this->namespace}\\\\Controllers\\\\{$this->classBase}AdminController";
        $content = <<<PHP
<?php
use Core\\Router;

return function (Router \$router, ?\\Core\\Container \$container = null) {
    \$guard = function (\$req, \$next) {
        if (empty(\$_SESSION['admin_auth'])) {
            header('Location: /admin/login');
            exit;
        }
        return \$next(\$req);
    };
    \$prefix = '/admin';
    if (\$container) {
        \$cfg = \$container->get('config');
        \$prefix = \$cfg['admin_prefix'] ?? '/admin';
    }
    \$router->group(\$prefix . '/{$slug}', [\$guard], function (Router \$r) {
        \$r->get('/', [{$controller}::class, 'index']);
        \$r->get('/create', [{$controller}::class, 'create']);
        \$r->post('/create', [{$controller}::class, 'store']);
        \$r->get('/edit/{id}', [{$controller}::class, 'edit']);
        \$r->post('/edit/{id}', [{$controller}::class, 'update']);
        \$r->post('/delete/{id}', [{$controller}::class, 'delete']);
    });
};
PHP;
        $this->writeIfMissing($file, $content);
    }

    private function generateViews(): void
    {
        $entitySlug = $this->schema['entity'] ?? $this->slug;
        $indexFile = $this->modulePath . '/views/admin/index.php';
        if (!file_exists($indexFile)) {
            $content = <<<PHP
<?php ob_start(); ?>
<div class="card">
    <div class="toolbar">
        <h3><?= htmlspecialchars(\$title ?? '{$this->classBase}') ?></h3>
        <a class="btn" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/{$entitySlug}/create') ?>">Add</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <?php foreach (\$listColumns as \$col): ?>
                <th><?= htmlspecialchars(ucfirst(\$col)) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach (\$items as \$item): ?>
            <tr>
                <?php foreach (\$listColumns as \$col): ?>
                    <td><?= htmlspecialchars(\$item[\$col] ?? '') ?></td>
                <?php endforeach; ?>
                <td class="actions">
                    <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/{$entitySlug}/edit/' . (int)(\$item['id'] ?? 0)) ?>">Edit</a>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/{$entitySlug}/delete/' . (int)(\$item['id'] ?? 0)) ?>" onsubmit="return confirm('Delete item?')" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\$csrf ?? '') ?>">
                        <button type="submit" class="btn danger ghost">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty(\$items)): ?>
        <p class="muted">No items yet.</p>
    <?php endif; ?>
</div>
<?php
\$content = ob_get_clean();
\$title = \$title ?? '{$this->classBase}';
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
PHP;
            $this->writeIfMissing($indexFile, $content);
        }

        $editFile = $this->modulePath . '/views/admin/edit.php';
        if (!file_exists($editFile)) {
            $content = <<<PHP
<?php ob_start(); ?>
<form method="post" class="card stack">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\$csrf ?? '') ?>">
    <?php foreach (\$schemaFields as \$field): ?>
        <?php if (!in_array(\$field['name'], \$formFields, true)) { continue; } ?>
        <label class="field">
            <span><?= htmlspecialchars(ucfirst(\$field['name'])) ?></span>
            <?php if ((\$field['type'] ?? '') === 'enum' && !empty(\$field['values'])): ?>
                <select name="<?= htmlspecialchars(\$field['name']) ?>">
                    <?php foreach (\$field['values'] as \$val): ?>
                        <option value="<?= htmlspecialchars(\$val) ?>" <?= ((\$item[\$field['name']] ?? '') === \$val) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(\$val)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ((\$field['type'] ?? '') === 'text'): ?>
                <textarea name="<?= htmlspecialchars(\$field['name']) ?>" rows="4"><?= htmlspecialchars(\$item[\$field['name']] ?? '') ?></textarea>
            <?php else: ?>
                <input type="text" name="<?= htmlspecialchars(\$field['name']) ?>" value="<?= htmlspecialchars(\$item[\$field['name']] ?? '') ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <div class="toolbar">
        <button type="submit" class="btn primary">Save</button>
        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/{$entitySlug}') ?>">Cancel</a>
    </div>
</form>
<?php
\$content = ob_get_clean();
\$title = \$title ?? 'Edit';
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
PHP;
            $this->writeIfMissing($editFile, $content);
        }
    }

    private function generateLang(): void
    {
        $en = [
            "{$this->slug}.title" => $this->classBase,
            "{$this->slug}.create" => "Create {$this->classBase}",
            "{$this->slug}.edit" => "Edit {$this->classBase}",
        ];
        $ru = [
            "{$this->slug}.title" => $this->classBase,
            "{$this->slug}.create" => "Создать {$this->classBase}",
            "{$this->slug}.edit" => "Редактировать {$this->classBase}",
        ];
        $this->writeIfMissing($this->modulePath . '/lang/en.php', "<?php\nreturn " . var_export($en, true) . ";");
        $this->writeIfMissing($this->modulePath . '/lang/ru.php', "<?php\nreturn " . var_export($ru, true) . ";");
    }

    private function generateAssets(): void
    {
        $file = $this->modulePath . '/assets/admin.css';
        if (file_exists($file)) {
            return;
        }
        $css = <<<CSS
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 10px; border-bottom: 1px solid #e0e0e0; text-align: left; }
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.muted { color: #7a7a7a; font-size: 14px; }
CSS;
        $this->writeIfMissing($file, $css);
    }

    private function generateSeed(): void
    {
        $seedFile = $this->modulePath . '/migrations/002_seed_' . $this->table . '.php';
        if (file_exists($seedFile)) {
            return;
        }
        $sampleFields = [];
        foreach ($this->schema['fields'] ?? [] as $field) {
            $sampleFields[$field['name']] = $this->sampleValue($field);
        }
        $values = var_export($sampleFields, true);
        $content = <<<PHP
<?php
return new class {
    public function up(\\Core\\Database \$db): void
    {
        \$sample = {$values};
        \$exists = \$db->fetch("SELECT COUNT(*) AS c FROM {$this->table}");
        if ((int)(\$exists['c'] ?? 0) === 0 && !empty(\$sample)) {
            \$columns = array_keys(\$sample);
            \$colSql = implode(', ', \$columns) . ', created_at, updated_at';
            \$placeholderSql = ':' . implode(', :', \$columns) . ', NOW(), NOW()';
            \$params = [];
            foreach (\$sample as \$k => \$v) {
                \$params[':' . \$k] = \$v;
            }
            \$db->execute("INSERT INTO {$this->table} ({\$colSql}) VALUES ({\$placeholderSql})", \$params);
        }
    }

    public function down(\\Core\\Database \$db): void
    {
        \$db->execute("DELETE FROM {$this->table}");
    }
};
PHP;
        $this->writeIfMissing($seedFile, $content);
    }

    private function columnSql(string $name, string $type, array $field): string
    {
        switch ($type) {
            case 'text':
                return "{$name} TEXT NULL";
            case 'enum':
                $values = array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $field['values'] ?? []);
                $valuesSql = implode(',', $values) ?: "'value'";
                return "{$name} ENUM({$valuesSql}) DEFAULT NULL";
            default:
                return "{$name} VARCHAR(255) DEFAULT NULL";
        }
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        return str_replace(' ', '', ucwords($value));
    }

    private function writeIfMissing(string $file, string $content): void
    {
        if (file_exists($file)) {
            return;
        }
        @file_put_contents($file, $content);
    }

    private function sampleValue(array $field)
    {
        $name = $field['name'] ?? '';
        $type = $field['type'] ?? 'string';
        if ($type === 'enum' && !empty($field['values'])) {
            return $field['values'][0];
        }
        if ($type === 'text') {
            return 'Sample ' . $name . ' text';
        }
        return 'Sample ' . $name;
    }
}
