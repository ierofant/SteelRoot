<?php
$meta = $meta ?? [];
$settings = $settings ?? [];
$themeVars = $themeVars ?? [];
$themeHref = $themeHref ?? null;
$theme = $theme ?? 'light';
$extraStyles = $meta['styles'] ?? [];
if (!is_array($extraStyles)) {
    $extraStyles = [];
}
$pwaEnabled = (($settings['pwa_enabled'] ?? '1') === '1');
$pwaThemeColor = trim((string)($settings['pwa_theme_color'] ?? '#1f6feb'));
$pwaIcon192 = trim((string)($settings['pwa_icon_192'] ?? ''));
$pwaIcon512 = trim((string)($settings['pwa_icon'] ?? ''));
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meta['title'] ?? 'SteelRoot') ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description'] ?? '') ?>">
    <?php if (!empty($meta['robots'])): ?><meta name="robots" content="<?= htmlspecialchars($meta['robots']) ?>"><?php endif; ?>
    <?php if (!empty($meta['keywords'])): ?><meta name="keywords" content="<?= htmlspecialchars($meta['keywords']) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($meta['canonical'] ?? '') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($meta['og']['title'] ?? $meta['title'] ?? '') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($meta['og']['description'] ?? $meta['description'] ?? '') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($meta['og']['url'] ?? ($meta['canonical'] ?? '')) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($meta['og']['image'] ?? '') ?>">
    <meta name="twitter:card" content="<?= htmlspecialchars($meta['twitter']['card'] ?? 'summary_large_image') ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($meta['twitter']['title'] ?? ($meta['title'] ?? '')) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($meta['twitter']['description'] ?? ($meta['description'] ?? '')) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($meta['twitter']['image'] ?? '') ?>">
    <?php if ($pwaEnabled): ?>
    <meta name="theme-color" content="<?= htmlspecialchars($pwaThemeColor) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)($settings['pwa_short_name'] ?? $settings['site_name'] ?? 'SteelRoot')) ?>">
    <link rel="manifest" href="/manifest.json">
    <?php if ($pwaIcon192 !== ''): ?>
    <link rel="icon" sizes="192x192" href="<?= htmlspecialchars($pwaIcon192) ?>">
    <?php endif; ?>
    <?php if ($pwaIcon512 !== ''): ?>
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($pwaIcon512) ?>">
    <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($meta['jsonld'])): ?><script type="application/ld+json"><?= $meta['jsonld'] ?></script><?php endif; ?>
    <?php if (!empty($settings['theme_favicon'])): ?><link rel="icon" href="<?= htmlspecialchars($settings['theme_favicon']) ?>"><?php endif; ?>
    <?php
        $appCssPath = APP_ROOT . '/assets/css/app.css';
        $v = '?v=' . (is_file($appCssPath) ? (string) filemtime($appCssPath) : '1');
    ?>
    <link rel="stylesheet" href="/assets/css/app.css<?= $v ?>">
    <?php if ($themeHref): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($themeHref) ?><?= str_contains($themeHref, '?') ? '' : $v ?>">
    <?php endif; ?>
    <?php foreach ($extraStyles as $styleHref): ?>
        <?php if (!is_string($styleHref) || $styleHref === '') continue; ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($styleHref) ?>">
    <?php endforeach; ?>
    <?php if (!empty($themeVars)): ?>
        <?php
            $themeVarsCss = ":root {\n";
            foreach ($themeVars as $k => $v) {
                $safeKey = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$k);
                $safeValue = preg_replace('/[^#(),.%\-\s\w]/u', '', (string)$v);
                if ($safeKey === '' || $safeValue === '') {
                    continue;
                }
                $themeVarsCss .= "  {$safeKey}: {$safeValue};\n";
            }
            $themeVarsCss .= "}\n";
            $themeVarsDir = APP_ROOT . '/storage/uploads/system';
            if (!is_dir($themeVarsDir)) {
                @mkdir($themeVarsDir, 0775, true);
            }
            $themeVarsPath = $themeVarsDir . '/theme-vars.css';
            if (!is_file($themeVarsPath) || file_get_contents($themeVarsPath) !== $themeVarsCss) {
                @file_put_contents($themeVarsPath, $themeVarsCss, LOCK_EX);
            }
            $themeVarsVersion = @filemtime($themeVarsPath) ?: time();
        ?>
        <link rel="stylesheet" href="/storage/uploads/system/theme-vars.css?v=<?= (int)$themeVarsVersion ?>">
    <?php endif; ?>
    <?php \Core\Slot::render('head_end'); ?>
</head>
