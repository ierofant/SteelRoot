<?php
$meta = $meta ?? [];
$settings = $GLOBALS['settingsAll'] ?? [];
$title = $meta['title'] ?? ($title ?? ($settings['og_title'] ?? 'SteelRoot'));
$description = $meta['description'] ?? ($settings['og_description'] ?? '');
$keywords = $meta['keywords'] ?? '';
$canonical = $meta['canonical'] ?? null;
$og = $meta['og'] ?? [];
if (!isset($og['title']) && !empty($settings['og_title'])) {
    $og['title'] = $settings['og_title'];
}
if (!isset($og['description']) && !empty($settings['og_description'])) {
    $og['description'] = $settings['og_description'];
}
if (!isset($og['image']) && !empty($settings['og_image'])) {
    $og['image'] = $settings['og_image'];
}
$tw = $meta['twitter'] ?? [];
if (!isset($tw['card'])) {
    $tw['card'] = 'summary_large_image';
}
if (!isset($tw['title'])) {
    $tw['title'] = $og['title'] ?? $title;
}
if (!isset($tw['description'])) {
    $tw['description'] = $og['description'] ?? $description;
}
if (!isset($tw['image']) && !empty($og['image'])) {
    $tw['image'] = $og['image'];
}
$jsonld = $meta['jsonld'] ?? null;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <?php if ($description): ?><meta name="description" content="<?= htmlspecialchars($description) ?>"><?php endif; ?>
    <?php if ($keywords): ?><meta name="keywords" content="<?= htmlspecialchars($keywords) ?>"><?php endif; ?>
    <?php if ($canonical): ?><link rel="canonical" href="<?= htmlspecialchars($canonical) ?>"><?php endif; ?>
    <?php if (!empty($og['title'])): ?><meta property="og:title" content="<?= htmlspecialchars($og['title']) ?>"><?php endif; ?>
    <?php if (!empty($og['description'])): ?><meta property="og:description" content="<?= htmlspecialchars($og['description']) ?>"><?php endif; ?>
    <?php if (!empty($og['url'])): ?><meta property="og:url" content="<?= htmlspecialchars($og['url']) ?>"><?php endif; ?>
    <?php if (!empty($og['image'])): ?><meta property="og:image" content="<?= htmlspecialchars($og['image']) ?>"><?php endif; ?>
<?php if (!empty($tw)): ?>
        <meta name="twitter:card" content="<?= htmlspecialchars($tw['card'] ?? 'summary') ?>">
        <?php if (!empty($tw['title'])): ?><meta name="twitter:title" content="<?= htmlspecialchars($tw['title']) ?>"><?php endif; ?>
        <?php if (!empty($tw['description'])): ?><meta name="twitter:description" content="<?= htmlspecialchars($tw['description']) ?>"><?php endif; ?>
        <?php if (!empty($tw['image'])): ?><meta name="twitter:image" content="<?= htmlspecialchars($tw['image']) ?>"><?php endif; ?>
    <?php endif; ?>
    <?php if ($jsonld): ?><script type="application/ld+json"><?= $jsonld ?></script><?php endif; ?>
    <?php if (!empty($settings['theme_favicon'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['theme_favicon']) ?>">
    <?php endif; ?>
    <?php $v = '?v=20260122'; ?>
    <link rel="stylesheet" href="/assets/css/app.css<?= $v ?>">
    <?php if ($themeHref): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($themeHref) ?><?= str_contains($themeHref, '?') ? '' : $v ?>">
    <?php endif; ?>
    <?php if (!empty($themeVars)): ?>
        <style>:root {<?php foreach ($themeVars as $k=>$v): ?> <?= $k ?>: <?= htmlspecialchars($v) ?>;<?php endforeach; ?> }</style>
    <?php endif; ?>
</head>
<body data-theme="<?= htmlspecialchars($theme) ?>">
<?php include APP_ROOT . '/app/views/partials/header.php'; ?>
<?php
    $sAll = $GLOBALS['settingsAll'] ?? [];
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
    <?= $content ?? '' ?>
</main>
<?php include APP_ROOT . '/app/views/partials/footer.php'; ?>
<?php
    $popupSettings = $GLOBALS['settingsAll'] ?? [];
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
