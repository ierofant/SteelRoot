<?php ob_start(); ?>
<div class="card">
    <div class="toolbar">
        <h3><?= htmlspecialchars($title ?? __('faq.title')) ?></h3>
        <div class="faq-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/settings') ?>"><?= __('faq.settings.link') ?></a>
            <a class="btn" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/create') ?>"><?= __('faq.actions.add') ?></a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <?php foreach ($listColumns as $col): ?>
                <th><?= htmlspecialchars(ucfirst($col)) ?></th>
            <?php endforeach; ?>
            <th><?= __('faq.actions.title') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <?php foreach ($listColumns as $col): ?>
                    <td><?= htmlspecialchars($item[$col] ?? '') ?></td>
                <?php endforeach; ?>
                <td class="actions">
                    <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/edit/' . (int)($item['id'] ?? 0)) ?>"><?= __('faq.actions.edit') ?></a>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq/delete/' . (int)($item['id'] ?? 0)) ?>" onsubmit="return confirm('<?= htmlspecialchars(__('faq.actions.delete_confirm'), ENT_QUOTES, 'UTF-8') ?>')" class="module-action-form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn danger ghost"><?= __('faq.actions.delete') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($items)): ?>
        <p class="muted"><?= __('faq.empty') ?></p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$title = $title ?? __('faq.title');
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
