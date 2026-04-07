<?php
use Core\Asset;

$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$pageTitle = $title ?? 'Admin';
$showSidebar = $showSidebar ?? true;
$flash = $flash ?? null;
$siteUrl = trim((string)($GLOBALS['settingsAll']['site_url'] ?? ''));
if ($siteUrl === '' || !preg_match('#^https?://#i', $siteUrl)) {
    $siteUrl = '/';
}
$commentsPendingCount = (int)($commentsPendingCount ?? 0);
$enabledModulesRaw = json_decode((string)($GLOBALS['settingsAll']['modules_enabled'] ?? '[]'), true);
$enabledModulesRaw = is_array($enabledModulesRaw) ? $enabledModulesRaw : [];
$enabledModules = array_values(array_unique(array_map(static function ($value): string {
    return strtolower((string)(preg_replace('/[^a-z0-9]+/i', '', (string)$value) ?? $value));
}, $enabledModulesRaw)));
$isModuleEnabled = static function (array $aliases) use ($enabledModules): bool {
    foreach ($aliases as $alias) {
        $normalized = strtolower((string)(preg_replace('/[^a-z0-9]+/i', '', (string)$alias) ?? $alias));
        if (in_array($normalized, $enabledModules, true)) {
            return true;
        }
    }
    return false;
};
$showShop = false;
if ($isModuleEnabled(['shop']) && isset($GLOBALS['db'])) {
    try {
        $showShop = (bool)$GLOBALS['db']->fetch(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'"
        );
    } catch (\Throwable $e) {
        $showShop = false;
    }
}
$currentPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$currentPath = $currentPath !== '' ? rtrim($currentPath, '/') : '';
$currentPath = $currentPath === '' ? '/' : $currentPath;
$normalizeAdminPath = static function (string $path) use ($ap): string {
    $full = rtrim($ap, '/') . '/' . ltrim($path, '/');
    $full = rtrim($full, '/');
    return $full === '' ? '/' : $full;
};
$isActivePath = static function (string $path, bool $prefix = false) use ($currentPath, $normalizeAdminPath): bool {
    $target = $normalizeAdminPath($path);
    if ($prefix) {
        return $currentPath === $target || strpos($currentPath, $target . '/') === 0;
    }
    return $currentPath === $target;
};
$navLinkClass = static function (string $path, bool $prefix = false, string $extra = '') use ($isActivePath): string {
    $classes = [];
    if ($extra !== '') {
        $classes[] = $extra;
    }
    if ($isActivePath($path, $prefix)) {
        $classes[] = 'is-active';
    }
    return implode(' ', $classes);
};
$groupIsOpen = static function (array $items) use ($isActivePath): bool {
    foreach ($items as $item) {
        $path = (string)($item['path'] ?? '');
        $prefix = (bool)($item['prefix'] ?? false);
        if ($path !== '' && $isActivePath($path, $prefix)) {
            return true;
        }
    }
    return false;
};
$hasVisibleItems = static function (array $items): bool {
    foreach ($items as $item) {
        if (($item['visible'] ?? true) === true) {
            return true;
        }
    }
    return false;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> · SteelRoot v2 Admin</title>
    <?= Asset::styleTag('/assets/css/admin-theme.css') ?>
    <?= $headHtml ?? '' ?>
</head>
<body class="admin-shell" data-theme="<?= htmlspecialchars(($GLOBALS['settingsAll']['theme'] ?? 'dark')) ?>" data-locale="<?= htmlspecialchars($GLOBALS['settingsAll']['locale_mode'] ?? 'multi') ?>">
<?php if ($showSidebar): ?>
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">SR</div>
            <div>
                <div class="brand-title">SteelRoot v2</div>
                <div class="brand-sub">Control Room</div>
            </div>
        </div>
        <?php
        $workspaceItems = [
            ['path' => '/settings', 'prefix' => false],
            ['path' => '/menu', 'prefix' => true],
            ['path' => '/homepage', 'prefix' => true],
            ['path' => '/theme', 'prefix' => true],
            ['path' => '/templates', 'prefix' => true],
            ['path' => '/template/errors', 'prefix' => true],
            ['path' => '/modules', 'prefix' => true],
        ];
        $contentItems = [
            ['path' => '/pages', 'prefix' => true, 'visible' => $isModuleEnabled(['pages'])],
            ['path' => '/articles', 'prefix' => true, 'visible' => $isModuleEnabled(['articles'])],
            ['path' => '/news', 'prefix' => true, 'visible' => $isModuleEnabled(['news'])],
            ['path' => '/gallery/upload', 'prefix' => true, 'visible' => $isModuleEnabled(['gallery'])],
            ['path' => '/gallery/tags', 'prefix' => true, 'visible' => $isModuleEnabled(['gallery'])],
            ['path' => '/gallery/categories', 'prefix' => true, 'visible' => $isModuleEnabled(['gallery'])],
            ['path' => '/comments', 'prefix' => true, 'visible' => $isModuleEnabled(['comments'])],
            ['path' => '/videos', 'prefix' => true, 'visible' => $isModuleEnabled(['video', 'videos'])],
            ['path' => '/shop', 'prefix' => true, 'visible' => $showShop],
        ];
        $communityItems = [
            ['path' => '/users', 'prefix' => true, 'visible' => $isModuleEnabled(['users'])],
            ['path' => '/newsletter', 'prefix' => true, 'visible' => $isModuleEnabled(['newsletter'])],
            ['path' => '/forum', 'prefix' => true, 'visible' => $isModuleEnabled(['forum'])],
            ['path' => '/profile', 'prefix' => true],
        ];
        $systemItems = [
            ['path' => '/files', 'prefix' => true],
            ['path' => '/attachments', 'prefix' => true],
            ['path' => '/gallery/upload', 'prefix' => true],
            ['path' => '/forms', 'prefix' => true],
            ['path' => '/redirects', 'prefix' => true],
            ['path' => '/security', 'prefix' => true],
            ['path' => '/docs', 'prefix' => true],
            ['path' => '/pwa', 'prefix' => true],
            ['path' => '/cache', 'prefix' => true],
        ];
        ?>
        <nav class="nav">
            <a class="<?= htmlspecialchars($navLinkClass('', false)) ?>" href="<?= htmlspecialchars($ap) ?>"><?= __('nav.dashboard') ?></a>

            <details class="nav-group" data-nav-group="workspace"<?= $groupIsOpen($workspaceItems) ? ' open' : '' ?>>
                <summary class="nav-group-label"><?= __('nav.group.workspace') ?></summary>
                <div class="nav-group-items">
                    <a class="<?= htmlspecialchars($navLinkClass('/settings')) ?>" href="<?= htmlspecialchars($ap) ?>/settings"><?= __('nav.settings') ?></a>
                    <?php if ($isModuleEnabled(['menu'])): ?><a class="<?= htmlspecialchars($navLinkClass('/menu', true)) ?>" href="<?= htmlspecialchars($ap) ?>/menu"><?= __('nav.menu') ?></a><?php endif; ?>
                    <a class="<?= htmlspecialchars($navLinkClass('/homepage', true)) ?>" href="<?= htmlspecialchars($ap) ?>/homepage"><?= __('nav.homepage') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/theme', true)) ?>" href="<?= htmlspecialchars($ap) ?>/theme"><?= __('nav.template') ?></a>
                    <?php if ($isModuleEnabled(['templates'])): ?><a class="<?= htmlspecialchars($navLinkClass('/templates', true)) ?>" href="<?= htmlspecialchars($ap) ?>/templates"><?= __('nav.templates') ?></a><?php endif; ?>
                    <a class="<?= htmlspecialchars($navLinkClass('/template/errors', true)) ?>" href="<?= htmlspecialchars($ap) ?>/template/errors"><?= __('errors.settings.title') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/modules', true)) ?>" href="<?= htmlspecialchars($ap) ?>/modules"><?= __('nav.modules') ?></a>
                </div>
            </details>

            <?php if ($hasVisibleItems($contentItems)): ?>
                <details class="nav-group" data-nav-group="content"<?= $groupIsOpen($contentItems) ? ' open' : '' ?>>
                    <summary class="nav-group-label"><?= __('nav.group.content') ?></summary>
                    <div class="nav-group-items">
                        <?php if ($isModuleEnabled(['pages'])): ?><a class="<?= htmlspecialchars($navLinkClass('/pages', true)) ?>" href="<?= htmlspecialchars($ap) ?>/pages"><?= __('nav.pages') ?></a><?php endif; ?>
                        <?php if ($isModuleEnabled(['articles'])): ?><a class="<?= htmlspecialchars($navLinkClass('/articles', true)) ?>" href="<?= htmlspecialchars($ap) ?>/articles"><?= __('nav.articles') ?></a><?php endif; ?>
                        <?php if ($isModuleEnabled(['news'])): ?><a class="<?= htmlspecialchars($navLinkClass('/news', true)) ?>" href="<?= htmlspecialchars($ap) ?>/news"><?= __('nav.news') ?></a><?php endif; ?>
                        <?php if ($isModuleEnabled(['gallery'])): ?>
                            <a class="<?= htmlspecialchars($navLinkClass('/gallery/upload', true)) ?>" href="<?= htmlspecialchars($ap) ?>/gallery/upload"><?= __('nav.gallery_upload') ?></a>
                            <a class="<?= htmlspecialchars($navLinkClass('/gallery/tags', true)) ?>" href="<?= htmlspecialchars($ap) ?>/gallery/tags"><?= __('nav.gallery_tags') ?></a>
                            <a class="<?= htmlspecialchars($navLinkClass('/gallery/categories', true)) ?>" href="<?= htmlspecialchars($ap) ?>/gallery/categories"><?= __('nav.gallery_categories') ?></a>
                        <?php endif; ?>
                        <?php if ($isModuleEnabled(['comments'])): ?>
                            <a class="<?= htmlspecialchars($navLinkClass('/comments', true, 'nav-link nav-link--comments')) ?>" href="<?= htmlspecialchars($ap) ?>/comments">
                                <span><?= __('nav.comments') ?></span>
                                <?php if ($commentsPendingCount > 0): ?><span class="nav-badge"><?= (int)$commentsPendingCount ?></span><?php endif; ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($isModuleEnabled(['video', 'videos'])): ?><a class="<?= htmlspecialchars($navLinkClass('/videos', true)) ?>" href="<?= htmlspecialchars($ap) ?>/videos"><?= __('nav.videos') ?></a><?php endif; ?>
                        <?php if ($showShop): ?><a class="<?= htmlspecialchars($navLinkClass('/shop', true)) ?>" href="<?= htmlspecialchars($ap) ?>/shop"><?= __('nav.shop') ?></a><?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if ($hasVisibleItems($communityItems)): ?>
                <details class="nav-group" data-nav-group="community"<?= $groupIsOpen($communityItems) ? ' open' : '' ?>>
                    <summary class="nav-group-label"><?= __('nav.group.community') ?></summary>
                    <div class="nav-group-items">
                        <?php if ($isModuleEnabled(['users'])): ?><a class="<?= htmlspecialchars($navLinkClass('/users', true)) ?>" href="<?= htmlspecialchars($ap) ?>/users"><?= __('nav.users') ?></a><?php endif; ?>
                        <?php if ($isModuleEnabled(['newsletter'])): ?><a class="<?= htmlspecialchars($navLinkClass('/newsletter', true)) ?>" href="<?= htmlspecialchars($ap) ?>/newsletter"><?= __('nav.newsletter') ?></a><?php endif; ?>
                        <?php if ($isModuleEnabled(['forum'])): ?><a class="<?= htmlspecialchars($navLinkClass('/forum', true)) ?>" href="<?= htmlspecialchars($ap) ?>/forum"><?= __('nav.forum') ?></a><?php endif; ?>
                        <a class="<?= htmlspecialchars($navLinkClass('/profile', true)) ?>" href="<?= htmlspecialchars($ap) ?>/profile"><?= __('nav.profile') ?></a>
                    </div>
                </details>
            <?php endif; ?>

            <details class="nav-group" data-nav-group="system"<?= $groupIsOpen($systemItems) ? ' open' : '' ?>>
                <summary class="nav-group-label"><?= __('nav.group.system') ?></summary>
                <div class="nav-group-items">
                    <a class="<?= htmlspecialchars($navLinkClass('/files', true)) ?>" href="<?= htmlspecialchars($ap) ?>/files"><?= __('nav.files') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/attachments', true)) ?>" href="<?= htmlspecialchars($ap) ?>/attachments"><?= __('nav.attachments') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/forms', true)) ?>" href="<?= htmlspecialchars($ap) ?>/forms"><?= __('nav.forms') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/redirects', true)) ?>" href="<?= htmlspecialchars($ap) ?>/redirects"><?= __('nav.redirects') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/security', true)) ?>" href="<?= htmlspecialchars($ap) ?>/security"><?= __('nav.security') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/docs', true)) ?>" href="<?= htmlspecialchars($ap) ?>/docs"><?= __('docs.menu') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/pwa', true)) ?>" href="<?= htmlspecialchars($ap) ?>/pwa"><?= __('nav.pwa') ?></a>
                    <a class="<?= htmlspecialchars($navLinkClass('/cache', true)) ?>" href="<?= htmlspecialchars($ap) ?>/cache"><?= __('nav.cache') ?></a>
                </div>
            </details>
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
            <p class="page-kicker">SteelRoot v2 Admin</p>
            <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <?php if ($showSidebar): ?>
            <div class="header-actions">
                <span class="pill">Online</span>
                <a class="btn ghost small" href="<?= htmlspecialchars($siteUrl) ?>" target="_blank" rel="noopener noreferrer">Open site ↗</a>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var locale = document.body.dataset.locale || 'multi';
    if (locale !== 'multi') {
        var hiddenClass = locale === 'ru' ? '.locale-en' : '.locale-ru';
        document.querySelectorAll(hiddenClass + ' [required]').forEach(function (el) {
            el.removeAttribute('required');
        });
    }

    var storageKey = 'admin.nav.groups';
    var groups = document.querySelectorAll('.nav-group[data-nav-group]');
    if (!groups.length) {
        return;
    }

    var saved = {};
    try {
        saved = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
    } catch (e) {
        saved = {};
    }

    groups.forEach(function (group) {
        var key = group.getAttribute('data-nav-group');
        if (!key) {
            return;
        }
        if (Object.prototype.hasOwnProperty.call(saved, key)) {
            group.open = !!saved[key];
        }
        group.addEventListener('toggle', function () {
            saved[key] = group.open;
            try {
                localStorage.setItem(storageKey, JSON.stringify(saved));
            } catch (e) {
            }
        });
    });
});
</script>
<?= $bodyHtml ?? '' ?>
</body>
</html>
