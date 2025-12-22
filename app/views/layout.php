<?php
$settings = $settings ?? [];
$meta = $meta ?? [];
$theme = $theme ?? 'light';
$customHref = $themeCustomUrl ?? null;
$themeHref = ($theme === 'custom' && $customHref) ? $customHref : null;
$themeVars = $themeVars ?? [];
if (empty($themeVars)) {
    if (!empty($settings['theme_primary'])) $themeVars['--color-primary'] = $settings['theme_primary'];
    if (!empty($settings['theme_secondary'])) $themeVars['--color-secondary'] = $settings['theme_secondary'];
    if (!empty($settings['theme_accent'])) $themeVars['--color-accent'] = $settings['theme_accent'];
    if (!empty($settings['theme_bg'])) $themeVars['--color-bg'] = $settings['theme_bg'];
    if (!empty($settings['theme_text'])) $themeVars['--color-text'] = $settings['theme_text'];
    if (!empty($settings['theme_card'])) $themeVars['--color-card'] = $settings['theme_card'];
    if (!empty($settings['theme_radius'])) $themeVars['--radius'] = $settings['theme_radius'] . 'px';
}
$currentLocale = $currentLocale ?? 'en';
$requestPath = $requestPath ?? '/';
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
            foreach ($decoded as $entry) {
                if (!empty($entry['path']) && $entry['path'] === $requestPath && !empty($entry['trail']) && is_array($entry['trail'])) {
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
    <?php if (isset($this) && method_exists($this, 'hasContentTemplate') && method_exists($this, 'renderContent') && $this->hasContentTemplate()): ?>
        <?php $this->renderContent(); ?>
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
