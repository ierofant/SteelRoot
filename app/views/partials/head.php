<?php
$meta = $meta ?? [];
$settings = $settings ?? ($GLOBALS['settingsAll'] ?? []);
$themeVars = $themeVars ?? [];
$themeHref = $themeHref ?? null;
$theme = $theme ?? ($GLOBALS['viewTheme'] ?? 'light');
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
    <?php if (!empty($meta['jsonld'])): ?><script type="application/ld+json"><?= $meta['jsonld'] ?></script><?php endif; ?>
    <?php if (!empty($settings['theme_favicon'])): ?><link rel="icon" href="<?= htmlspecialchars($settings['theme_favicon']) ?>"><?php endif; ?>
    <?php $v = '?v=20260122'; ?>
    <link rel="stylesheet" href="/assets/css/app.css<?= $v ?>">
    <?php if ($themeHref): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($themeHref) ?><?= str_contains($themeHref, '?') ? '' : $v ?>">
    <?php endif; ?>
    <?php if (!empty($themeVars)): ?>
        <style>:root {<?php foreach ($themeVars as $k=>$v): ?> <?= $k ?>: <?= htmlspecialchars($v) ?>;<?php endforeach; ?> }</style>
    <?php endif; ?>
</head>
