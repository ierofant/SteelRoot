<?php
$settings = $GLOBALS['settingsAll'] ?? [];
$prefix = 'error_404_';

$enabled = !empty($settings[$prefix . 'custom_enabled']);

$title = $enabled ? ($settings[$prefix . 'title'] ?? '') : '';
$message = $enabled ? ($settings[$prefix . 'message'] ?? '') : '';
$description = $enabled ? ($settings[$prefix . 'description'] ?? '') : '';
$ctaText = $enabled ? ($settings[$prefix . 'cta_text'] ?? '') : '';
$ctaUrl = $enabled ? ($settings[$prefix . 'cta_url'] ?? '') : '';
$showHome = $enabled && !empty($settings[$prefix . 'show_home_button']);
$icon = $enabled ? ($settings[$prefix . 'icon'] ?? '') : '';

function stripScripts(string $html): string {
    return preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html);
}
?>
<?php ob_start(); ?>
<div class="error-page">
    <?php if ($enabled && ($title || $message || $description || $ctaText || $ctaUrl || $showHome)): ?>
        <?php if ($icon): ?><div class="muted"><?= htmlspecialchars($icon) ?></div><?php endif; ?>
        <?php if ($title): ?><h2><?= htmlspecialchars($title) ?></h2><?php endif; ?>
        <?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($description): ?><div class="muted"><?= stripScripts($description) ?></div><?php endif; ?>
        <?php if ($ctaText && $ctaUrl): ?>
            <div class="form-actions">
                <a class="btn ghost" href="<?= htmlspecialchars($ctaUrl) ?>"><?= htmlspecialchars($ctaText) ?></a>
            </div>
        <?php endif; ?>
        <?php if ($showHome): ?>
            <div class="form-actions">
                <a class="btn" href="/"><?= __('nav.homepage') ?></a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <h2>Page not found</h2>
        <p>The page you requested could not be found.</p>
    <?php endif; ?>
</div>
<?php
$meta = [
    'title' => $title ?: '404 Not Found',
    'description' => $message ?: 'Page not found',
];
$content = ob_get_clean(); include __DIR__ . '/../layout.php';
