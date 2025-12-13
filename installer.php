<?php
// Standalone installer for SteelRoot framework (shared hosting friendly).
declare(strict_types=1);

session_start();

const INSTALL_LOG = __DIR__ . '/storage/logs/install.log';
const APP_ROOT = __DIR__;

// ---------------------------------------------------------------------
// Utilities
// ---------------------------------------------------------------------
function log_step(string $message): void
{
    if (!is_dir(dirname(INSTALL_LOG))) {
        @mkdir(dirname(INSTALL_LOG), 0775, true);
    }
    $ts = date('Y-m-d H:i:s');
    @file_put_contents(INSTALL_LOG, "[{$ts}] {$message}\n", FILE_APPEND);
}

function esc(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['installer_csrf'])) {
        $_SESSION['installer_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['installer_csrf'];
}

function status_icon(bool $ok): string
{
    return $ok ? '<span style="color:green;">&#10003;</span>' : '<span style="color:red;">&#10007;</span>';
}

function mask(string $value): string
{
    return str_repeat('*', max(4, strlen($value)));
}

// Handle deletion request early
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        die('Invalid CSRF token');
    }
    @unlink(__FILE__);
    exit('installer.php удалён.');
}

// ---------------------------------------------------------------------
// Checks
// ---------------------------------------------------------------------
$requirements = [
    'PHP >= 8.0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'extension: PDO' => extension_loaded('pdo'),
    'extension: pdo_mysql' => extension_loaded('pdo_mysql'),
    'extension: mbstring' => extension_loaded('mbstring'),
    'extension: json' => extension_loaded('json'),
    'extension: openssl' => extension_loaded('openssl'),
    'extension: fileinfo' => extension_loaded('fileinfo'),
    'extension: gd' => extension_loaded('gd'),
];

// Ensure storage subdirs exist before checking writability
@mkdir(APP_ROOT . '/storage', 0775, true);
@mkdir(APP_ROOT . '/storage/cache', 0775, true);
@mkdir(APP_ROOT . '/storage/logs', 0775, true);
@mkdir(APP_ROOT . '/storage/uploads', 0775, true);
@mkdir(APP_ROOT . '/storage/uploads/gallery', 0775, true);
@mkdir(APP_ROOT . '/storage/uploads/articles', 0775, true);
@mkdir(APP_ROOT . '/storage/uploads/users', 0775, true);
@mkdir(APP_ROOT . '/storage/tmp', 0775, true);
@mkdir(APP_ROOT . '/storage/tmp/user_tokens', 0775, true);

$paths = [
    'storage/' => is_writable(APP_ROOT . '/storage'),
    'storage/cache/' => is_writable(APP_ROOT . '/storage/cache'),
    'storage/logs/' => is_writable(APP_ROOT . '/storage/logs'),
    'storage/uploads/' => is_writable(APP_ROOT . '/storage/uploads'),
    'storage/uploads/gallery/' => is_writable(APP_ROOT . '/storage/uploads/gallery'),
    'storage/uploads/articles/' => is_writable(APP_ROOT . '/storage/uploads/articles'),
    'storage/uploads/users/' => is_writable(APP_ROOT . '/storage/uploads/users'),
    'storage/tmp/' => is_writable(APP_ROOT . '/storage/tmp'),
    'storage/tmp/user_tokens/' => is_writable(APP_ROOT . '/storage/tmp/user_tokens'),
];

$envOk = !in_array(false, $requirements, true) && !in_array(false, $paths, true);

$errors = [];
$success = false;
$runLog = [];
$testStatus = null;

// ---------------------------------------------------------------------
// Handle POST (installation)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['install','test'], true)) {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        $errors[] = 'Неверный CSRF токен.';
    }

    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $siteName = trim($_POST['site_name'] ?? 'SteelRoot');
    $siteUrl = trim($_POST['site_url'] ?? 'http://localhost');
    $adminSecret = trim($_POST['admin_secret'] ?? '');
    $lang = trim($_POST['lang'] ?? 'en');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if ($dbHost === '' || $dbUser === '' || $dbName === '') {
        $errors[] = 'Заполните хост, пользователя и имя базы данных.';
    }
    if ($_POST['action'] === 'install') {
        if ($adminUser === '' || $adminPass === '') {
            $errors[] = 'Задайте учётные данные администратора.';
        }
    }
    if (!in_array($lang, ['en', 'ru'], true)) {
        $errors[] = 'Неверный язык (en|ru).';
    }
    if (!$envOk) {
        $errors[] = 'Исправьте ошибки окружения перед установкой.';
    }

    // DB connection test
    if (empty($errors)) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
            ]);
            $testStatus = 'Подключение к БД: успешно';
            $runLog[] = 'Подключение к БД: успешно';
            log_step("DB connection OK host={$dbHost} db={$dbName} user={$dbUser}");
        } catch (Throwable $e) {
            $msg = 'Не удалось подключиться к базе данных. Проверьте параметры.';
            $errors[] = $msg;
            $testStatus = $msg;
            log_step('DB connection failed: ' . $e->getMessage());
        }
    }

    if (($_POST['action'] ?? '') === 'test') {
        // Only testing connection, do not proceed further
    }

    // Write configs
    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        $dbConfig = "<?php\nreturn [\n    'driver' => 'mysql',\n    'host' => '" . addslashes($dbHost) . "',\n    'port' => {$dbPort},\n    'user' => '" . addslashes($dbUser) . "',\n    'pass' => '" . addslashes($dbPass) . "',\n    'name' => '" . addslashes($dbName) . "',\n    'charset' => 'utf8mb4',\n];\n";
        $prefix = $adminSecret !== '' ? '/admin-' . addslashes($adminSecret) : '/admin';
        $appConfig = "<?php\nreturn [\n    'name' => '" . addslashes($siteName) . "',\n    'env' => 'production',\n    'debug' => false,\n    'default_language' => '" . addslashes($lang) . "',\n    'timezone' => 'UTC',\n    'url' => '" . addslashes($siteUrl) . "',\n    'locale' => '" . addslashes($lang) . "',\n    'fallback_locale' => '" . addslashes($lang) . "',\n    'admin_secret' => '" . addslashes($adminSecret) . "',\n    'admin_prefix' => '" . $prefix . "',\n];\n";
        if (!is_dir(APP_ROOT . '/app/config')) {
            @mkdir(APP_ROOT . '/app/config', 0775, true);
        }
        if (@file_put_contents(APP_ROOT . '/app/config/database.php', $dbConfig) === false) {
            $errors[] = 'Не удалось записать app/config/database.php';
        }
        if (@file_put_contents(APP_ROOT . '/app/config/app.php', $appConfig) === false) {
            $errors[] = 'Не удалось записать app/config/app.php';
        }
        if (empty($errors)) {
            $runLog[] = 'Конфигурация сохранена';
            log_step('Config files written');
        }
    }

    // Stop further actions if this was only a test
    if (($_POST['action'] ?? '') === 'test') {
        // nothing more
    }

    // Load Database wrapper (standalone use)
    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        try {
            if (file_exists(APP_ROOT . '/core/Database.php')) {
                require_once APP_ROOT . '/core/Database.php';
            }
            if (!class_exists('\\Core\\Database')) {
                class InstallerDatabase
                {
                    private PDO $pdo;
                    public function __construct(array $config)
                    {
                        $dsn = sprintf(
                            '%s:host=%s;port=%d;dbname=%s;charset=%s',
                            $config['driver'] ?? 'mysql',
                            $config['host'] ?? 'localhost',
                            $config['port'] ?? 3306,
                            $config['name'] ?? '',
                            $config['charset'] ?? 'utf8mb4'
                        );
                        $this->pdo = new PDO($dsn, $config['user'] ?? '', $config['pass'] ?? '', [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_PERSISTENT => false,
                        ]);
                    }
                    public function pdo(): PDO { return $this->pdo; }
                    public function query(string $sql, array $params = []) { $s = $this->pdo->prepare($sql); $s->execute($params); return $s; }
                    public function fetch(string $sql, array $params = []): ?array { $r = $this->query($sql, $params)->fetch(); return $r === false ? null : $r; }
                    public function fetchAll(string $sql, array $params = []): array { return $this->query($sql, $params)->fetchAll(); }
                    public function execute(string $sql, array $params = []): int { return $this->query($sql, $params)->rowCount(); }
                    public function transaction(callable $fn) { $this->pdo->beginTransaction(); try { $res = $fn($this); $this->pdo->commit(); return $res; } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; } }
                }
                class_alias(InstallerDatabase::class, 'Core\\Database');
            }
            $db = new \Core\Database([
                'driver' => 'mysql',
                'host' => $dbHost,
                'port' => $dbPort,
                'user' => $dbUser,
                'pass' => $dbPass,
                'name' => $dbName,
                'charset' => 'utf8mb4',
            ]);
        } catch (Throwable $e) {
            $errors[] = 'Не удалось инициализировать слой БД.';
            log_step('Database wrapper init failed: ' . $e->getMessage());
        }
    }

    // Run migrations
    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        $migrationDir = APP_ROOT . '/database/migrations';
        $files = glob($migrationDir . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            try {
                $migration = include $file;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($db);
                    $runLog[] = "Миграция {$name}: OK";
                    log_step("Migration {$name} applied");
                }
            } catch (Throwable $e) {
                $errors[] = "Ошибка миграции {$name}.";
                log_step("Migration {$name} failed: " . $e->getMessage());
                break;
            }
        }
        // Run module migrations for built-in modules that need base tables
        $moduleMigrations = [
            APP_ROOT . '/modules/Pages/migrations/create_pages_table.php',
        ];
        foreach ($moduleMigrations as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $name = basename($file);
            try {
                $migration = include $file;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up($db);
                    $runLog[] = "Миграция {$name}: OK";
                    log_step("Module migration {$name} applied");
                }
            } catch (Throwable $e) {
                $errors[] = "Ошибка миграции {$name}.";
                log_step("Module migration {$name} failed: " . $e->getMessage());
                break;
            }
        }
    }

    // Create admin user
    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        try {
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $db->execute("
                INSERT INTO admin_users (username, password, created_at)
                VALUES (:u, :p, NOW())
                ON DUPLICATE KEY UPDATE password = VALUES(password)
            ", [':u' => $adminUser, ':p' => $hash]);
            $runLog[] = 'Админ-пользователь создан/обновлён';
            log_step("Admin user created: {$adminUser}");
        } catch (Throwable $e) {
            $errors[] = 'Не удалось создать администратора (проверьте таблицу admin_users).';
            log_step('Admin user creation failed: ' . $e->getMessage());
        }
    }

    // Ensure .htaccess
    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        $ht = APP_ROOT . '/.htaccess';
        if (!file_exists($ht)) {
            $content = "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ prefilter.php [L]\n\n# Secure internal dirs\nRewriteRule ^core/ - [F,L]\nRewriteRule ^app/ - [F,L]\nRewriteRule ^modules/ - [F,L]\nRewriteRule ^database/ - [F,L]\nRewriteRule ^storage/ - [F,L]\nRewriteRule ^vendor/ - [F,L]\nOptions -Indexes\n";
            if (@file_put_contents($ht, $content) === false) {
                $errors[] = 'Не удалось создать .htaccess.';
            } else {
                $runLog[] = '.htaccess создан';
                log_step('.htaccess created');
            }
        }
    }

    if (empty($errors) && ($_POST['action'] ?? '') === 'install') {
        $success = true;
        $runLog[] = 'Установка завершена';
        log_step('Installation completed successfully');
    }

}

// ---------------------------------------------------------------------
// HTML Output
// ---------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>SteelRoot Installer</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f6f8fb; color:#222; margin:0; padding:20px; }
        .card { max-width: 820px; margin: 0 auto 20px; background:#fff; border:1px solid #e3e7ee; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.05); padding:20px; }
        h1 { margin-top:0; }
        .status { display:flex; gap:12px; flex-wrap:wrap; }
        .status div { padding:6px 10px; border-radius:6px; background:#f0f3f8; }
        .error { color:#b00020; }
        .success { color:green; }
        .log { background:#0f172a; color:#e5e7eb; padding:10px; border-radius:6px; font-family: Menlo, monospace; font-size: 13px; max-height:200px; overflow:auto; }
        label { display:block; margin:8px 0 4px; }
        input[type=text], input[type=password], input[type=number] { width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; }
        button { padding:10px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; }
        button:disabled { background:#9ca3af; }
        .row { display:flex; gap:16px; }
        .row .col { flex:1; }
    </style>
</head>
<body>
<div class="card">
    <div style="text-align:center;">
        <svg width="180" height="190" viewBox="0 0 240 260" xmlns="http://www.w3.org/2000/svg">
          <style>
            .steel { stroke:#8FA3AD; stroke-width:10; fill:none; stroke-linecap:round; stroke-linejoin:round; }
            .root  { stroke:#5F6F78; stroke-width:8;  fill:none; stroke-linecap:round; stroke-linejoin:round; }
            .text  { fill:#8FA3AD; font-family: 'JetBrains Mono', monospace; font-size:24px; letter-spacing:4px; }
          </style>
          <text x="120" y="40" text-anchor="middle" class="text">STEELROOT</text>
          <path class="steel" d="M60 90 L60 200 M60 90 H135 Q170 90 170 125 Q170 160 135 160 H60 M135 160 L175 200" />
          <path class="root" d="M100 200 V240 M100 215 H70 M100 225 H130  M140 200 V240 M140 215 H165 M140 225 H115" />
        </svg>
    </div>
    <h1>Установка SteelRoot</h1>
    <p>Пожалуйста, заполните параметры базы данных и администратора. Скрипт проверит окружение, выполнит миграции и создаст конфигурацию.</p>

    <h3>Проверка окружения</h3>
    <div class="status">
        <?php foreach ($requirements as $label => $ok): ?>
            <div><?= status_icon($ok) . ' ' . esc($label) ?></div>
        <?php endforeach; ?>
    </div>
    <h3>Права на запись</h3>
    <div class="status">
        <?php foreach ($paths as $label => $ok): ?>
            <div><?= status_icon($ok) . ' ' . esc($label) ?></div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?= esc($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if ($testStatus): ?>
        <div class="<?= empty($errors) ? 'success' : 'error' ?>"><?= esc($testStatus) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <p>Установка успешно завершена.</p>
            <p>Настоятельно рекомендуется удалить installer.php.</p>
            <p>Пожалуйста, смените пароль администратора после входа.</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($runLog)): ?>
        <h3>Шаги</h3>
        <div class="log">
            <?php foreach ($runLog as $line): ?>
                <?= esc($line) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
        <div class="row">
            <div class="col">
                <h3>База данных</h3>
                <label>Хост</label>
                <input type="text" name="db_host" value="<?= esc($_POST['db_host'] ?? 'localhost') ?>" required>
                <label>Порт</label>
                <input type="number" name="db_port" value="<?= esc($_POST['db_port'] ?? '3306') ?>" required>
                <label>Пользователь</label>
                <input type="text" name="db_user" value="<?= esc($_POST['db_user'] ?? '') ?>" required>
                <label>Пароль</label>
                <input type="password" name="db_pass" value="" required>
                <label>Имя базы</label>
                <input type="text" name="db_name" value="<?= esc($_POST['db_name'] ?? '') ?>" required>
            </div>
            <div class="col">
                <h3>Сайт и админ</h3>
                <label>Название сайта</label>
                <input type="text" name="site_name" value="<?= esc($_POST['site_name'] ?? 'SteelRoot') ?>" required>
                <label>URL сайта</label>
                <input type="text" name="site_url" value="<?= esc($_POST['site_url'] ?? 'http://localhost') ?>" required>
                <label>Секрет админки (опционально, добавится к /admin-...)</label>
                <input type="text" name="admin_secret" value="<?= esc($_POST['admin_secret'] ?? '') ?>">
                <label>Язык (en|ru)</label>
                <input type="text" name="lang" value="<?= esc($_POST['lang'] ?? 'en') ?>" required>
                <label>Админ логин</label>
                <input type="text" name="admin_user" value="<?= esc($_POST['admin_user'] ?? 'admin') ?>" required>
                <label>Админ пароль</label>
                <input type="password" name="admin_pass" value="" required>
            </div>
        </div>
        <p>
            <button type="submit" name="action" value="test" <?= $envOk ? '' : 'disabled' ?>>Проверить подключение</button>
            <button type="submit" name="action" value="install" <?= $envOk ? '' : 'disabled' ?>>Установить</button>
        </p>
    </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit">Удалить installer.php</button>
        </form>
    <?php endif; ?>

    <p style="font-size:12px;color:#555;">Лог: storage/logs/install.log (пароли маскируются).</p>
</div>
</body>
</html>
