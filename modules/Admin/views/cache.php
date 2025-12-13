<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('cache.title') ?></p>
            <h3><?= __('cache.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('cache.action.back_admin') ?></a>
    </div>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <div class="table-wrap">
        <table class="table data">
            <thead><tr><th><?= __('cache.table.path') ?></th><th><?= __('cache.table.files') ?></th><th><?= __('cache.table.size') ?></th></tr></thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($stats['path'] ?? '') ?></td>
                    <td><?= (int)($stats['files'] ?? 0) ?></td>
                    <td><?= number_format(($stats['size'] ?? 0)/1024, 1) ?> KB</td>
                </tr>
            </tbody>
        </table>
    </div>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/cache/clear" class="form-actions">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="btn primary"><?= __('cache.action.clear') ?></button>
    </form>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/cache/delete" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span><?= __('cache.delete.label') ?></span>
            <input type="text" name="key" placeholder="cache_key">
        </label>
        <button type="submit" class="btn ghost"><?= __('cache.delete.action') ?></button>
    </form>
</div>
<?php
$title = __('cache.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
