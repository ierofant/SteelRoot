<?php
use Core\Asset;

$settings  = $settings ?? [];
$title     = trim((string)($title   ?? 'Offline mode'));
$message   = trim((string)($message ?? 'The connection is unavailable right now. You can retry when the network is back.'));
$button    = trim((string)($button  ?? 'Try again'));
$appName   = trim((string)($settings['pwa_short_name'] ?? $settings['pwa_name'] ?? $settings['site_name'] ?? 'SteelRoot'));
$themeColor = trim((string)($settings['pwa_theme_color'] ?? '#07070e'));
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)($settings['pwa_lang'] ?? 'en')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor) ?>">
    <title><?= htmlspecialchars($appName . ' · ' . $title) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(Asset::url('/assets/css/pwa-offline.css')) ?>">
</head>
<body class="pwa-offline-shell">
    <main class="pwa-offline-card">

        <div class="pwa-offline-icon-wrap">
            <svg class="pwa-offline-icon" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <!-- outer diamond -->
                <polygon points="80,6 154,80 80,154 6,80" stroke="#00ffd7" stroke-width="1" fill="none" opacity="0.35"/>
                <!-- inner diamond -->
                <polygon points="80,28 132,80 80,132 28,80" stroke="#ff003c" stroke-width="0.75" fill="none" opacity="0.22"/>
                <!-- vertex dots -->
                <circle cx="80"  cy="6"   r="2.5" fill="#00ffd7" opacity="0.75"/>
                <circle cx="154" cy="80"  r="2.5" fill="#00ffd7" opacity="0.75"/>
                <circle cx="80"  cy="154" r="2.5" fill="#00ffd7" opacity="0.75"/>
                <circle cx="6"   cy="80"  r="2.5" fill="#00ffd7" opacity="0.75"/>
                <!-- circuit tick lines from vertices -->
                <line x1="80"  y1="6"   x2="80"  y2="0"   stroke="#00ffd7" stroke-width="1" opacity="0.5"/>
                <line x1="154" y1="80"  x2="160" y2="80"  stroke="#00ffd7" stroke-width="1" opacity="0.5"/>
                <line x1="80"  y1="154" x2="80"  y2="160" stroke="#00ffd7" stroke-width="1" opacity="0.5"/>
                <line x1="6"   y1="80"  x2="0"   y2="80"  stroke="#00ffd7" stroke-width="1" opacity="0.5"/>
                <!-- mid-edge circuit nodes -->
                <circle cx="117" cy="43"  r="1.5" fill="#ff003c" opacity="0.45"/>
                <circle cx="117" cy="117" r="1.5" fill="#ff003c" opacity="0.45"/>
                <circle cx="43"  cy="43"  r="1.5" fill="#ff003c" opacity="0.45"/>
                <circle cx="43"  cy="117" r="1.5" fill="#ff003c" opacity="0.45"/>
                <!-- broken wifi icon — center (80, 93) -->
                <!-- outer arc r=36 225°→315° clockwise -->
                <path d="M 54.5 67.5 A 36 36 0 0 1 105.5 67.5" stroke="#c8c8e0" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>
                <!-- middle arc r=22 — broken: left half 225°→255°, right half 285°→315° -->
                <path d="M 64.5 77.5 A 22 22 0 0 1 74.3 71.8" stroke="#c8c8e0" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>
                <path d="M 85.7 71.8 A 22 22 0 0 1 95.5 77.5" stroke="#c8c8e0" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>
                <!-- inner arc r=10 -->
                <path d="M 73 86 A 10 10 0 0 1 87 86" stroke="#c8c8e0" stroke-width="2.5" stroke-linecap="round" opacity="0.8"/>
                <!-- center dot -->
                <circle cx="80" cy="100" r="3" fill="#c8c8e0" opacity="0.8"/>
                <!-- X mark at the break — centered (80, 70) -->
                <line x1="75" y1="65" x2="85" y2="75" stroke="#ff003c" stroke-width="2.5" stroke-linecap="round"/>
                <line x1="85" y1="65" x2="75" y2="75" stroke="#ff003c" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
        </div>

        <p class="pwa-offline-kicker">
            <span class="pwa-offline-status-dot"></span>
            <?= htmlspecialchars($appName) ?>
        </p>

        <h1 data-text="<?= htmlspecialchars($title) ?>"><?= htmlspecialchars($title) ?></h1>

        <p class="pwa-offline-copy"><?= htmlspecialchars($message) ?></p>

        <div class="pwa-offline-divider"></div>

        <div class="pwa-offline-actions">
            <button type="button" class="pwa-offline-button" onclick="window.location.reload()">
                <span class="pwa-offline-button-label"><?= htmlspecialchars($button) ?></span>
            </button>
        </div>

        <p class="pwa-offline-hint">// signal lost — standing by</p>

    </main>
</body>
</html>
