<?php ob_start(); ?>
<div class="card">
    <div class="toolbar">
        <h3><?= htmlspecialchars($title ?? 'Faq') ?></h3>
        <a class="btn" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/create') ?>">Add</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <?php foreach ($listColumns as $col): ?>
                <th><?= htmlspecialchars(ucfirst($col)) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <?php foreach ($listColumns as $col): ?>
                    <td><?= htmlspecialchars($item[$col] ?? '') ?></td>
                <?php endforeach; ?>
                <td class="actions">
                    <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/edit/' . (int)($item['id'] ?? 0)) ?>">Edit</a>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/delete/' . (int)($item['id'] ?? 0)) ?>" onsubmit="return confirm('Delete item?')" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn danger ghost">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($items)): ?>
        <p class="muted">No items yet.</p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title = $title ?? 'Faq';
include APP_ROOT . '/modules/Admin/views/layout.php';
?>