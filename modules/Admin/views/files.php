<?php ob_start(); ?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('files.title') ?></p>
            <h3><?= __('files.subtitle') ?></h3>
        </div>
        <form method="get" action="" class="search-inline">
            <input type="text" name="q" value="<?= htmlspecialchars($query ?? '') ?>" placeholder="<?= __('files.search.placeholder') ?>">
            <button type="submit" class="btn ghost"><?= __('files.search.submit') ?></button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= __('files.table.id') ?></th><th><?= __('files.table.thumb') ?></th><th><?= __('files.table.name') ?></th><th><?= __('files.table.actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int)$item['id'] ?></td>
                        <td><img src="<?= htmlspecialchars($item['path_thumb'] ?? $item['path']) ?>" alt="" class="thumb"></td>
                        <td><?= htmlspecialchars($item['title_en'] ?? '') ?></td>
                        <td class="actions">
                            <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/files/regenerate/' . (int)$item['id']) ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="btn ghost small"><?= __('files.actions.regenerate') ?></button>
                            </form>
                            <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/files/delete/' . (int)$item['id']) ?>" onsubmit="return confirm('<?= __('files.actions.confirm_delete') ?>');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="btn danger small"><?= __('files.actions.delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$title = __('files.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
