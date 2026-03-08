<?php
declare(strict_types=1);
session_start();

const INSTALL_LOG = __DIR__ . '/storage/logs/install.log';
const APP_ROOT    = __DIR__;

// Detect browser locale: default 'en', switch to 'ru' if Accept-Language starts with ru
$detectedLocale = 'en';
$acceptLang = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
if (preg_match('/\bru\b/', $acceptLang)) {
    $detectedLocale = 'ru';
}

// ─── Utilities ───────────────────────────────────────────────────────────────

function ilog(string $msg): void
{
    if (!is_dir(dirname(INSTALL_LOG))) {
        @mkdir(dirname(INSTALL_LOG), 0775, true);
    }
    @file_put_contents(INSTALL_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
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

// ─── Self-delete ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) { die('Invalid CSRF'); }
    @unlink(__FILE__);
    header('Location: ' . (trim($_POST['admin_prefix'] ?? '/admin')));
    exit;
}

// ─── Requirements ────────────────────────────────────────────────────────────

$requirements = [
    'PHP >= 8.1'          => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO'                 => extension_loaded('pdo'),
    'pdo_mysql'           => extension_loaded('pdo_mysql'),
    'mbstring'            => extension_loaded('mbstring'),
    'json'                => extension_loaded('json'),
    'openssl'             => extension_loaded('openssl'),
    'fileinfo'            => extension_loaded('fileinfo'),
    'gd'                  => extension_loaded('gd'),
];

$storageDirs = [
    'storage/cache',
    'storage/logs',
    'storage/uploads',
    'storage/uploads/gallery',
    'storage/uploads/gallery/categories',
    'storage/uploads/articles',
    'storage/uploads/articles/categories',
    'storage/uploads/videos',
    'storage/uploads/users',
    'storage/uploads/menu',
    'storage/tmp',
    'storage/tmp/user_tokens',
    'storage/tmp/sessions',
];
foreach ($storageDirs as $d) {
    @mkdir(APP_ROOT . '/' . $d, 0775, true);
}

$paths = [];
foreach ($storageDirs as $d) {
    $paths[$d] = is_writable(APP_ROOT . '/' . $d);
}

$envOk  = !in_array(false, $requirements, true) && !in_array(false, $paths, true);
$errors = [];
$success = false;
$runLog  = [];

// ─── Module catalogue ────────────────────────────────────────────────────────
// slug => [label, description, always, migrations[]]

$moduleCatalogue = [
    'Admin'     => ['Admin Panel',   'Dashboard, settings, security, file manager, redirects',  true,  []],
    'Articles'  => ['Articles',      'Blog/news with categories, tags, author, JSON-LD',         true,  []],
    'News'      => ['News',          'Dedicated news section with own categories and admin',         true,  []],
    'Gallery'   => ['Gallery',       'Image gallery with categories, subfolders, lightbox',      true,  []],
    'Pages'     => ['Pages',         'Static pages with menu integration and sitemap',            true,  [
        APP_ROOT . '/modules/Pages/migrations/create_pages_table.php',
    ]],
    'Menu'      => ['Menu',          'Navigation with RU/EN labels, SEO meta, OG images',        true,  [
        APP_ROOT . '/modules/Menu/migrations/20251220_create_menu_table.php',
        APP_ROOT . '/modules/Menu/migrations/20260115_add_menu_parent_fields.php',
    ]],
    'Search'    => ['Search',        'Full-text search across articles and gallery',              true,  []],
    'Templates' => ['Templates',     'Custom theme upload and selection',                         true,  []],
    'Popups'    => ['Popups',        'Cookie consent and adult content warnings (built into layout)', true, []],
    'Users'     => ['Users',         'Registration, profiles, avatars, roles, login logs',       false, [
        APP_ROOT . '/modules/Users/migrations/20251211_create_users_table.php',
        APP_ROOT . '/modules/Users/migrations/20251211_create_login_logs.php',
        APP_ROOT . '/modules/Users/migrations/20251211_add_author_to_articles.php',
        APP_ROOT . '/modules/Users/migrations/20251211_add_author_to_gallery_items.php',
        APP_ROOT . '/modules/Users/migrations/20260224_add_username_signature_visibility.php',
    ]],
    'FAQ'       => ['FAQ',           'FAQ section with admin CRUD',                              false, [
        APP_ROOT . '/modules/FAQ/migrations/001_create_faq_items.php',
        APP_ROOT . '/modules/FAQ/migrations/002_seed_faq_items.php',
    ]],
    'Api'       => ['API',           'REST API keys for external integrations',                  false, [
        APP_ROOT . '/modules/Api/migrations/001_create_api_keys.php',
    ]],
    'Video'     => ['Video Gallery', 'Video gallery with YouTube, Vimeo, MP4 and embed support', false, [
        APP_ROOT . '/modules/Video/migrations/001_create_video_items.php',
    ]],
];

// ─── Handle POST ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['install', 'test'], true)) {

    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    }

    $dbHost      = trim($_POST['db_host'] ?? 'localhost');
    $dbPort      = (int)($_POST['db_port'] ?? 3306);
    $dbUser      = trim($_POST['db_user'] ?? '');
    $dbPass      = (string)($_POST['db_pass'] ?? '');
    $dbName      = trim($_POST['db_name'] ?? '');
    $siteName    = trim($_POST['site_name'] ?? 'SteelRoot');
    $siteUrl     = rtrim(trim($_POST['site_url'] ?? 'http://localhost'), '/');
    $adminSecret = preg_replace('/[^a-z0-9\-_]/i', '', trim($_POST['admin_secret'] ?? ''));
    $locale      = in_array($_POST['locale'] ?? 'en', ['en', 'ru'], true) ? $_POST['locale'] : 'en';
    $localeMode  = in_array($_POST['locale_mode'] ?? 'multi', ['en', 'ru', 'multi'], true) ? $_POST['locale_mode'] : 'multi';
    $adminUser   = trim($_POST['admin_user'] ?? 'admin');
    $adminPass   = (string)($_POST['admin_pass'] ?? '');

    $selectedModules = array_keys(array_filter($moduleCatalogue, fn($m) => $m[2])); // always-on
    foreach ($moduleCatalogue as $slug => $meta) {
        if (!$meta[2] && !empty($_POST['module_' . strtolower($slug)])) {
            $selectedModules[] = $slug;
        }
    }

    if ($dbHost === '' || $dbUser === '' || $dbName === '') {
        $errors[] = 'Fill in DB host, user and database name.';
    }
    if (($_POST['action'] ?? '') === 'install' && ($adminUser === '' || $adminPass === '')) {
        $errors[] = 'Admin username and password are required.';
    }
    if (($_POST['action'] ?? '') === 'install' && strlen($adminPass) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if (!$envOk) {
        $errors[] = 'Fix environment issues before installing.';
    }

    // DB connection
    $pdo = null;
    if (empty($errors)) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false,
            ]);
            $runLog[] = ['ok', 'Database connection successful'];
            ilog("DB OK host={$dbHost} db={$dbName}");
        } catch (Throwable $e) {
            $errors[] = 'Cannot connect to database: ' . $e->getMessage();
            ilog('DB failed: ' . $e->getMessage());
        }
    }

    if (($_POST['action'] ?? '') === 'test') {
        // stop here for test
    } elseif (empty($errors)) {

        // Write configs
        $prefix    = $adminSecret !== '' ? '/admin-' . $adminSecret : '/admin';
        $dbCfg     = "<?php\nreturn [\n    'driver'  => 'mysql',\n    'host'    => '" . addslashes($dbHost) . "',\n    'port'    => {$dbPort},\n    'user'    => '" . addslashes($dbUser) . "',\n    'pass'    => '" . addslashes($dbPass) . "',\n    'name'    => '" . addslashes($dbName) . "',\n    'charset' => 'utf8mb4',\n];\n";
        $appCfg    = "<?php\nreturn [\n    'name'             => '" . addslashes($siteName) . "',\n    'env'              => 'production',\n    'debug'            => false,\n    'url'              => '" . addslashes($siteUrl) . "',\n    'locale'           => '" . $locale . "',\n    'default_language' => '" . $locale . "',\n    'fallback_locale'  => '" . $locale . "',\n    'timezone'         => 'UTC',\n    'admin_secret'     => '" . addslashes($adminSecret) . "',\n    'admin_prefix'     => '" . $prefix . "',\n];\n";

        @mkdir(APP_ROOT . '/app/config', 0775, true);
        if (@file_put_contents(APP_ROOT . '/app/config/database.php', $dbCfg) === false) {
            $errors[] = 'Cannot write app/config/database.php — check permissions.';
        }
        if (@file_put_contents(APP_ROOT . '/app/config/app.php', $appCfg) === false) {
            $errors[] = 'Cannot write app/config/app.php — check permissions.';
        }
        if (empty($errors)) {
            $runLog[] = ['ok', 'Config files written'];
            ilog('Configs written prefix=' . $prefix);
        }

        // DB wrapper shim
        if (empty($errors)) {
            try {
                if (file_exists(APP_ROOT . '/core/Database.php')) {
                    require_once APP_ROOT . '/core/Database.php';
                }
                if (!class_exists('\\Core\\Database')) {
                    class InstallerDb {
                        private PDO $pdo;
                        public function __construct(array $c) {
                            $this->pdo = new PDO(
                                sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $c['driver'] ?? 'mysql', $c['host'], $c['port'] ?? 3306, $c['name'], $c['charset'] ?? 'utf8mb4'),
                                $c['user'], $c['pass'],
                                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                            );
                        }
                        public function pdo(): PDO { return $this->pdo; }
                        public function execute(string $sql, array $p = []): int { $s = $this->pdo->prepare($sql); $s->execute($p); return $s->rowCount(); }
                        public function fetch(string $sql, array $p = []): ?array { $s = $this->pdo->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }
                        public function fetchAll(string $sql, array $p = []): array { $s = $this->pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); }
                    }
                    class_alias(InstallerDb::class, 'Core\\Database');
                }
                $db = new \Core\Database(['driver'=>'mysql','host'=>$dbHost,'port'=>$dbPort,'user'=>$dbUser,'pass'=>$dbPass,'name'=>$dbName,'charset'=>'utf8mb4']);
            } catch (Throwable $e) {
                $errors[] = 'DB wrapper init failed: ' . $e->getMessage();
            }
        }

        // Core migrations
        if (empty($errors)) {
            $migFiles = glob(APP_ROOT . '/database/migrations/*.php') ?: [];
            sort($migFiles);
            $logFile  = APP_ROOT . '/database/migrations/.migrations.log';
            $applied  = is_file($logFile) ? (json_decode(file_get_contents($logFile), true) ?? []) : [];
            foreach ($migFiles as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                try {
                    $mig = include $file;
                    if (is_object($mig) && method_exists($mig, 'up')) {
                        $mig->up($db);
                        if (!in_array($name, $applied, true)) { $applied[] = $name; }
                        $runLog[] = ['ok', 'Migration: ' . $name];
                        ilog("Core migration OK: $name");
                    }
                } catch (Throwable $e) {
                    $errors[] = "Migration failed: $name";
                    ilog("Migration failed $name: " . $e->getMessage());
                    break;
                }
            }
            @file_put_contents($logFile, json_encode($applied, JSON_PRETTY_PRINT));
        }

        // Module migrations
        if (empty($errors)) {
            // Ensure migrations_log table
            $db->execute("
                CREATE TABLE IF NOT EXISTS migrations_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    module VARCHAR(191) NOT NULL,
                    migration VARCHAR(191) NOT NULL,
                    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY module_migration_unique (module, migration),
                    INDEX module_idx (module)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            foreach ($selectedModules as $slug) {
                $migs = $moduleCatalogue[$slug][3] ?? [];
                foreach ($migs as $file) {
                    if (!file_exists($file)) { continue; }
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $already = $db->fetch("SELECT id FROM migrations_log WHERE module = ? AND migration = ?", [$slug, $name]);
                    if ($already) { continue; }
                    try {
                        $mig = include $file;
                        if (is_object($mig) && method_exists($mig, 'up')) {
                            $mig->up($db);
                            $db->execute("INSERT IGNORE INTO migrations_log (module, migration) VALUES (?, ?)", [$slug, $name]);
                            $runLog[] = ['ok', "Module migration [{$slug}]: $name"];
                            ilog("Module migration OK $slug/$name");
                        }
                    } catch (Throwable $e) {
                        $errors[] = "Module migration failed [{$slug}] $name";
                        ilog("Module migration failed $slug/$name: " . $e->getMessage());
                        break 2;
                    }
                }
            }
        }

        // Admin user
        if (empty($errors)) {
            try {
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $db->execute(
                    "INSERT INTO admin_users (username, password, created_at) VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE password = VALUES(password)",
                    [$adminUser, $hash]
                );
                $runLog[] = ['ok', "Admin user «{$adminUser}» created"];
                ilog("Admin user created: $adminUser");
            } catch (Throwable $e) {
                $errors[] = 'Failed to create admin user: ' . $e->getMessage();
                ilog('Admin user failed: ' . $e->getMessage());
            }
        }

        // Seed settings
        if (empty($errors)) {
            $enabledJson = json_encode(array_values($selectedModules), JSON_UNESCAPED_SLASHES);
            $seeds = [
                ['name' => 'site_name',        'value' => $siteName],
                ['name' => 'site_url',         'value' => $siteUrl],
                ['name' => 'locale',           'value' => $locale],
                ['name' => 'locale_mode',      'value' => $localeMode],
                ['name' => 'modules_enabled',  'value' => $enabledJson],
            ];
            foreach ($seeds as $s) {
                $db->execute(
                    "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    [$s['name'], $s['value']]
                );
            }
            $runLog[] = ['ok', 'Settings seeded (locale_mode=' . $localeMode . ')'];
            $runLog[] = ['ok', 'Modules enabled: ' . implode(', ', $selectedModules)];
            ilog('Settings seeded, modules: ' . $enabledJson);
        }

        // .htaccess
        if (empty($errors) && !file_exists(APP_ROOT . '/.htaccess')) {
            $ht = "Options -Indexes\nOptions -MultiViews\n\nRewriteRule ^core/ - [F,L]\nRewriteRule ^app/ - [F,L]\nRewriteRule ^docs/ - [F,L]\nRewriteRule ^modules/ - [F,L]\nRewriteRule ^database/ - [F,L]\nRewriteRule ^storage/ - [F,L]\nRewriteRule ^vendor/ - [F,L]\nRewriteRule ^tools/ - [F,L]\n\nRewriteEngine On\nRewriteCond %{REQUEST_URI} ^/api/\nRewriteRule ^ prefilter.php [L]\n\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ prefilter.php [L]\n";
            if (@file_put_contents(APP_ROOT . '/.htaccess', $ht) !== false) {
                $runLog[] = ['ok', '.htaccess created'];
                ilog('.htaccess created');
            }
        }

        if (empty($errors)) {
            $success     = true;
            $adminPrefix = $prefix;
            $runLog[]    = ['ok', 'Installation complete!'];
            ilog('Installation complete');
        }
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function statusBadge(bool $ok): string
{
    return $ok
        ? '<span class="badge ok">✓</span>'
        : '<span class="badge fail">✗</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SteelRoot — Installer</title>
<link rel="stylesheet" href="/assets/css/installer.css?v=1">
</head>
<body>
<div class="wrap">

  <!-- Logo -->
  <div class="logo-wrap">
    <svg width="72" height="72" viewBox="0 0 240 260" xmlns="http://www.w3.org/2000/svg">
      <path stroke="#22d3ee" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"
            d="M60 90 L60 200 M60 90 H135 Q170 90 170 125 Q170 160 135 160 H60 M135 160 L175 200"/>
      <path stroke="#0e7490" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"
            d="M100 200 V240 M100 215 H70 M100 225 H130 M140 200 V240 M140 215 H165 M140 225 H115"/>
    </svg>
    <div class="logo-tagline">SteelRoot CMS — Installer</div>
  </div>

  <?php if ($success): ?>
  <!-- ── SUCCESS ── -->
  <div class="card">
    <div class="card-title">🎉 Installation complete</div>
    <div class="alert success">
      SteelRoot installed successfully. Delete this file before going live.
    </div>
    <div class="run-log">
      <?php foreach ($runLog as [$type, $line]): ?>
        <div class="<?= $type ?>"><?= $type === 'ok' ? '✓' : '✗' ?> <?= esc($line) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="divider"></div>
    <p class="installer-admin-panel-note">
      Admin panel: <code class="installer-admin-panel-code"><?= esc($adminPrefix ?? '/admin') ?></code>
    </p>
    <form method="post" class="installer-success-actions">
      <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="admin_prefix" value="<?= esc($adminPrefix ?? '/admin') ?>">
      <button type="submit" class="btn btn-danger">Delete installer.php &amp; go to admin</button>
    </form>
  </div>

  <?php else: ?>
  <!-- ── ENV CHECK ── -->
  <div class="card">
    <div class="card-title">Environment <span>PHP <?= PHP_VERSION ?></span></div>
    <div class="check-grid">
      <?php foreach ($requirements as $label => $ok): ?>
        <div class="check-item"><?= statusBadge($ok) ?> <?= esc($label) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="divider"></div>
    <div class="card-title installer-card-title-tight">Writable paths</div>
    <div class="check-grid">
      <?php foreach ($paths as $label => $ok): ?>
        <div class="check-item"><?= statusBadge($ok) ?> <?= esc($label) ?></div>
      <?php endforeach; ?>
    </div>
    <?php if (!$envOk): ?>
      <div class="alert danger installer-alert-top">Fix the issues above before installing.</div>
    <?php endif; ?>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert danger">
      <?php foreach ($errors as $e): ?><div>✗ <?= esc($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($runLog)): ?>
    <div class="run-log installer-runlog-spaced">
      <?php foreach ($runLog as [$type, $line]): ?>
        <div class="<?= $type ?>"><?= $type === 'ok' ? '✓' : '✗' ?> <?= esc($line) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ── FORM ── -->
  <form method="post" id="installer-form">
    <input type="hidden" name="csrf" value="<?= esc(csrf_token()) ?>">

    <!-- Language -->
    <div class="card">
      <div class="card-title">Language</div>
      <div class="grid-2">
        <div class="field">
          <label>Site language</label>
          <select name="locale">
            <?php $loc = $_POST['locale'] ?? $detectedLocale; ?>
            <option value="en" <?= $loc === 'en' ? 'selected' : '' ?>>English</option>
            <option value="ru" <?= $loc === 'ru' ? 'selected' : '' ?>>Русский</option>
          </select>
          <span class="hint">Default locale for the public site.</span>
        </div>
        <div class="field">
          <label>Content mode</label>
          <select name="locale_mode">
            <?php $lm = $_POST['locale_mode'] ?? ($detectedLocale === 'ru' ? 'ru' : 'multi'); ?>
            <option value="multi" <?= $lm === 'multi' ? 'selected' : '' ?>>Bilingual (EN + RU)</option>
            <option value="en"    <?= $lm === 'en'    ? 'selected' : '' ?>>English only</option>
            <option value="ru"    <?= $lm === 'ru'    ? 'selected' : '' ?>>Только русский</option>
          </select>
          <span class="hint">Controls which language fields appear in admin forms.</span>
        </div>
      </div>
    </div>

    <!-- Database -->
    <div class="card">
      <div class="card-title">Database</div>
      <div class="grid-3">
        <div class="field">
          <label>Host</label>
          <input type="text" name="db_host" value="<?= esc($_POST['db_host'] ?? 'localhost') ?>" required>
        </div>
        <div class="field">
          <label>Port</label>
          <input type="number" name="db_port" value="<?= esc((string)($_POST['db_port'] ?? 3306)) ?>" required>
        </div>
        <div class="field">
          <label>Database name</label>
          <input type="text" name="db_name" value="<?= esc($_POST['db_name'] ?? '') ?>" required>
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>Username</label>
          <input type="text" name="db_user" value="<?= esc($_POST['db_user'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="db_pass">
        </div>
      </div>
    </div>

    <!-- Site -->
    <div class="card">
      <div class="card-title">Site</div>
      <div class="grid-2">
        <div class="field">
          <label>Site name</label>
          <input type="text" name="site_name" value="<?= esc($_POST['site_name'] ?? 'SteelRoot') ?>" required>
        </div>
        <div class="field">
          <label>Site URL</label>
          <input type="text" name="site_url" value="<?= esc($_POST['site_url'] ?? 'http://localhost') ?>" required>
        </div>
        <div class="field">
          <label>Admin URL secret <span class="installer-label-optional">(optional)</span></label>
          <input type="text" name="admin_secret" value="<?= esc($_POST['admin_secret'] ?? '') ?>" placeholder="leave empty for /admin">
          <span class="hint">If set, admin will be at /admin-{secret}</span>
        </div>
      </div>
    </div>

    <!-- Admin account -->
    <div class="card">
      <div class="card-title">Admin account</div>
      <div class="grid-2">
        <div class="field">
          <label>Username</label>
          <input type="text" name="admin_user" value="<?= esc($_POST['admin_user'] ?? 'admin') ?>" required>
        </div>
        <div class="field">
          <label>Password <span class="installer-label-hint">(min 8 chars)</span></label>
          <input type="password" name="admin_pass" required>
        </div>
      </div>
    </div>

    <!-- Modules -->
    <div class="card">
      <div class="card-title">Modules</div>
      <div class="module-grid" id="module-grid">
        <?php foreach ($moduleCatalogue as $slug => $meta): ?>
          <?php
            $always   = $meta[2];
            $checked  = $always || !empty($_POST['module_' . strtolower($slug)]);
            $postKey  = 'module_' . strtolower($slug);
          ?>
          <label class="module-card <?= $always ? 'always' : '' ?> <?= $checked ? 'selected' : '' ?>"
                 <?= $always ? '' : 'onclick="toggleModule(event, this)"' ?>>
            <input type="checkbox" name="<?= $postKey ?>" value="1"
                   <?= $checked ? 'checked' : '' ?>
                   <?= $always  ? 'disabled' : '' ?>>
            <div class="module-card-top">
              <span class="module-name"><?= esc($meta[0]) ?></span>
              <span class="module-badge <?= $always ? 'core' : 'optional' ?>"><?= $always ? 'core' : 'optional' ?></span>
            </div>
            <div class="module-desc"><?= esc($meta[1]) ?></div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="btn-row">
      <button type="submit" name="action" value="test" class="btn btn-ghost" <?= $envOk ? '' : 'disabled' ?>>
        Test DB connection
      </button>
      <button type="submit" name="action" value="install" class="btn btn-primary" <?= $envOk ? '' : 'disabled' ?>>
        Install SteelRoot
      </button>
    </div>
  </form>

  <?php endif; ?>

  <div class="installer-log-note">
    Log: storage/logs/install.log
  </div>
</div>

<script>
function toggleModule(event, card) {
    event.preventDefault();
    const cb = card.querySelector('input[type=checkbox]');
    if (!cb || cb.disabled) return;
    cb.checked = !cb.checked;
    card.classList.toggle('selected', cb.checked);
}
</script>
</body>
</html>
