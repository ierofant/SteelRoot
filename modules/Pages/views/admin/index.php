<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('pages.admin.title') ?></p>
            <h3><?= __('pages.admin.subtitle') ?></h3>
        </div>
        <div class="form-actions" style="gap:8px;">
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/create') ?>"><?= __('pages.admin.action.create') ?></a>
        </div>
    </div>

    <div class="table">
        <div class="table__head">
            <div>#</div>
            <div><?= __('pages.admin.fields.slug') ?></div>
            <div><?= __('pages.admin.fields.title') ?></div>
            <div><?= __('pages.admin.fields.visible') ?></div>
            <div><?= __('pages.admin.fields.show_in_menu') ?></div>
            <div><?= __('pages.admin.fields.menu_order') ?></div>
            <div><?= __('pages.admin.actions') ?></div>
        </div>
        <?php foreach ($pages as $page): ?>
            <div class="table__row">
                <div><?= (int)$page['id'] ?></div>
                <div><?= htmlspecialchars($page['slug']) ?></div>
                <div><?= htmlspecialchars($page['title_en'] ?? '') ?> / <?= htmlspecialchars($page['title_ru'] ?? '') ?></div>
                <div><?= !empty($page['visible']) ? __('pages.admin.yes') : __('pages.admin.no') ?></div>
                <div><?= !empty($page['show_in_menu']) ? __('pages.admin.yes') : __('pages.admin.no') ?></div>
                <div><?= (int)($page['menu_order'] ?? 0) ?></div>
                <div class="actions">
                    <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/edit/' . (int)$page['id']) ?>"><?= __('pages.admin.action.edit') ?></a>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/delete/' . (int)$page['id']) ?>" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn ghost danger"><?= __('pages.admin.action.delete') ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($pages)): ?>
            <div class="table__row">
                <div class="muted" style="grid-column: 1 / -1;"><?= __('pages.admin.empty') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../Admin/views/layout.php';
