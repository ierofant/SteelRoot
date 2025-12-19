<?php
$settings = $GLOBALS['settingsAll'] ?? [];
$meta = $meta ?? [];
$baseTitle = $meta['title'] ?? ($title ?? null);
if (!$baseTitle) {
    $baseTitle = $settings['og_title'] ?? 'SteelRoot';
}
$baseDescription = $meta['description'] ?? ($settings['og_description'] ?? '');
$meta = array_merge([
    'title' => $baseTitle,
    'description' => $baseDescription,
    'keywords' => '',
    'canonical' => null,
    'og' => [],
    'twitter' => [],
    'jsonld' => null,
], $meta);
// defaults
$primaryImage = $meta['image'] ?? null;
$defaultImage = !empty($settings['og_image'])
    ? $settings['og_image']
    : (!empty($settings['theme_logo']) ? $settings['theme_logo'] : '/assets/theme/og-default.png');
$meta['og'] = array_merge([
    'title' => $meta['title'] ?? '',
    'description' => $meta['description'] ?? '',
    'image' => $primaryImage ?: $defaultImage,
    'url' => $meta['canonical'] ?? null,
], $meta['og']);
$meta['twitter'] = array_merge([
    'card' => 'summary_large_image',
    'title' => $meta['og']['title'] ?? ($meta['title'] ?? ''),
    'description' => $meta['og']['description'] ?? ($meta['description'] ?? ''),
    'image' => $primaryImage ?: $meta['og']['image'] ?? $defaultImage,
], $meta['twitter']);
$theme = $theme ?? ($GLOBALS['viewTheme'] ?? 'light');
$customHref = $GLOBALS['customThemeUrl'] ?? null;
$themeHref = ($theme === 'custom' && $customHref) ? $customHref : null;
$themeVars = [];
if (!empty($settings['theme_primary'])) $themeVars['--color-primary'] = $settings['theme_primary'];
if (!empty($settings['theme_secondary'])) $themeVars['--color-secondary'] = $settings['theme_secondary'];
if (!empty($settings['theme_accent'])) $themeVars['--color-accent'] = $settings['theme_accent'];
if (!empty($settings['theme_bg'])) $themeVars['--color-bg'] = $settings['theme_bg'];
if (!empty($settings['theme_text'])) $themeVars['--color-text'] = $settings['theme_text'];
if (!empty($settings['theme_card'])) $themeVars['--color-card'] = $settings['theme_card'];
if (!empty($settings['theme_radius'])) $themeVars['--radius'] = $settings['theme_radius'] . 'px';
$currentLocale = $currentLocale ?? ($GLOBALS['currentLocale'] ?? 'en');
$currentPathMeta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// canonical fallback
if (empty($meta['canonical'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $meta['canonical'] = ($host ? $scheme . '://' . $host : '') . $currentPathMeta;
    $meta['og']['url'] = $meta['og']['url'] ?? $meta['canonical'];
}
// ensure images
if (empty($meta['og']['image'])) {
    if (!empty($meta['image'])) {
        $meta['og']['image'] = $meta['image'];
    } else {
        $meta['og']['image'] = $defaultImage;
    }
}
if (empty($meta['twitter']['image'])) {
    if (!empty($meta['image'])) {
        $meta['twitter']['image'] = $meta['image'];
    } else {
        $meta['twitter']['image'] = $meta['og']['image'] ?? $defaultImage;
    }
}
// enforce absolute URLs for meta images
$hostBase = null;
if (!empty($meta['canonical'])) {
    $parsed = parse_url($meta['canonical']);
    if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
        $hostBase = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    }
}
if (!$hostBase) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host) {
        $hostBase = $scheme . '://' . $host;
    }
}
if ($hostBase) {
    foreach (['og', 'twitter'] as $k) {
        if (!empty($meta[$k]['image']) && str_starts_with($meta[$k]['image'], '/')) {
            $meta[$k]['image'] = $hostBase . $meta[$k]['image'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLocale ?: 'en') ?>">
<?php include APP_ROOT . '/app/views/partials/head.php'; ?>
<body data-theme="<?= htmlspecialchars($theme) ?>">
<?php include APP_ROOT . '/app/views/partials/header.php'; ?>
<?php
    $sAll = $settings;
    $bcHome = $sAll['breadcrumb_home'] ?? 'Home';
    $customTrail = null;
    $customJson = $sAll['breadcrumbs_custom'] ?? '';
    if ($customJson) {
        $decoded = json_decode($customJson, true);
        if (is_array($decoded)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            foreach ($decoded as $entry) {
                if (!empty($entry['path']) && $entry['path'] === $path && !empty($entry['trail']) && is_array($entry['trail'])) {
                    $customTrail = $entry['trail'];
                    break;
                }
            }
        }
    }
    $bc = $customTrail ?? ($breadcrumbs ?? null);
?>
<?php if (($sAll['breadcrumbs_enabled'] ?? '1') === '1' && !empty($bc) && is_array($bc)): ?>
    <nav class="breadcrumbs">
        <a href="/"><?= htmlspecialchars($bcHome) ?></a>
        <?php foreach ($bc as $crumb): ?>
            <span>•</span>
            <?php if (!empty($crumb['url'])): ?>
                <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label'] ?? '') ?></a>
            <?php else: ?>
                <span class="current"><?= htmlspecialchars($crumb['label'] ?? '') ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<main>
    <?php
        $rendered = false;
        if (isset($this) && method_exists($this, 'hasContentTemplate') && method_exists($this, 'renderContent') && $this->hasContentTemplate()) {
            $this->renderContent();
            $rendered = true;
        }
    ?>
    <?php if (!$rendered): ?>
        <?= $content ?? '' ?>
    <?php endif; ?>
    <?php
        $sidebarContent = '';
        if (isset($this) && method_exists($this, 'getSection')) {
            $sidebarContent = $this->getSection('sidebar', '');
        } elseif (!empty($sidebar)) {
            $sidebarContent = $sidebar;
        }
    ?>
    <?php if ($sidebarContent !== ''): ?>
        <aside class="sidebar">
            <?= $sidebarContent ?>
        </aside>
    <?php endif; ?>
</main>
<?php include APP_ROOT . '/app/views/partials/footer.php'; ?>
<?php
    $popupSettings = $settings;
    $popupConfig = [
        'enabled' => !empty($popupSettings['popup_enabled']),
        'delay' => (int)($popupSettings['popup_delay'] ?? 5),
        'title' => $popupSettings['popup_title'] ?? '',
        'content' => $popupSettings['popup_content'] ?? '',
        'cta_text' => $popupSettings['popup_cta_text'] ?? '',
        'cta_url' => $popupSettings['popup_cta_url'] ?? '',
    ];
?>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">window.STEELROOT_POPUP = <?= json_encode($popupConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<div id="toast-container" class="toast-container" aria-live="polite" aria-atomic="true"></div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
window.showToast = (message, type = 'info') => {
    const wrap = document.getElementById('toast-container');
    if (!wrap) return;
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;
    wrap.appendChild(el);
    requestAnimationFrame(() => el.classList.add('visible'));
    setTimeout(() => {
        el.classList.remove('visible');
        setTimeout(() => el.remove(), 300);
    }, 3200);
};
</script>
<script src="/assets/js/popup.js"></script>
</body>
</html>
