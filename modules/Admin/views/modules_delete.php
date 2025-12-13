<?php ob_start(); ?>
<div class="card danger">
    <h3>Delete module</h3>
    <p>Модуль будет полностью удалён. Все данные будут потеряны.</p>
    <?php if (!empty($module['definition']['events'])): ?>
        <div class="alert warning">Warning: module listens to events (<?= htmlspecialchars(implode(', ', array_keys($module['definition']['events']))) ?>).</div>
    <?php endif; ?>
    <p><strong><?= htmlspecialchars($module['name'] ?? $module['slug'] ?? '') ?></strong> (<?= htmlspecialchars($module['slug'] ?? '') ?>)</p>
    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/delete/' . rawurlencode($module['slug'] ?? '')) ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="toolbar">
            <button type="submit" class="btn danger">Delete</button>
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules') ?>">Cancel</a>
        </div>
    </form>
</div>
<?php
$title = $title ?? 'Delete Module';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
