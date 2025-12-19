<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$pageTitle = $title ?? 'Admin';
$showSidebar = $showSidebar ?? true;
$flash = $flash ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · SteelRoot Admin</title>
    <link rel="stylesheet" href="/assets/css/admin-theme.css?v=3">
</head>
<body class="admin-shell" data-theme="<?= htmlspecialchars(($GLOBALS['settingsAll']['theme'] ?? 'dark')) ?>">
<?php if ($showSidebar): ?>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">SR</div>
            <div>
                <div class="brand-title">SteelRoot</div>
                <div class="brand-sub">Control Room</div>
            </div>
        </div>
        <nav class="nav">
            <a href="<?= htmlspecialchars($ap) ?>"><?= __('nav.dashboard') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/settings"><?= __('nav.settings') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/menu"><?= __('nav.menu') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/homepage"><?= __('nav.homepage') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/theme"><?= __('nav.template') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/template/errors"><?= __('errors.settings.title') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/users"><?= __('nav.users') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/profile"><?= __('nav.profile') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/files"><?= __('nav.files') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/modules"><?= __('nav.modules') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/forms"><?= __('nav.forms') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/redirects"><?= __('nav.redirects') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/security"><?= __('nav.security') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/docs"><?= __('docs.menu') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/attachments"><?= __('nav.attachments') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/gallery/upload"><?= __('nav.gallery_upload') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/pages"><?= __('nav.pages') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/articles"><?= __('nav.articles') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/pwa"><?= __('nav.pwa') ?></a>
            <a href="<?= htmlspecialchars($ap) ?>/cache"><?= __('nav.cache') ?></a>
        </nav>
        <div class="sidebar-footer">
            <form method="post" action="<?= htmlspecialchars($ap) ?>/logout">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('admin_logout')) ?>">
                <button type="submit" class="btn ghost">Logout</button>
            </form>
        </div>
    </aside>
<?php endif; ?>
<div class="page">
    <header class="page-header">
        <div>
            <p class="page-kicker">SteelRoot Admin</p>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <?php if ($showSidebar): ?>
            <div class="header-actions">
                <span class="pill">Online</span>
            </div>
        <?php endif; ?>
    </header>
    <main class="page-body">
        <?php if ($flash): ?>
            <div class="alert success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>
</body>
</html>
