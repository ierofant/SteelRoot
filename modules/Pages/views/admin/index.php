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

    <div class="pages-grid">
        <?php foreach ($pages as $page): ?>
            <article class="page-card" data-slug="<?= htmlspecialchars($page['slug']) ?>">
                <header>
                    <p class="eyebrow"><?= __('pages.admin.record', ['id' => (int)$page['id']]) ?></p>
                    <h3><?= htmlspecialchars($page['title_en'] ?? __('pages.admin.no_title')) ?></h3>
                    <p class="muted"><?= htmlspecialchars($page['slug']) ?></p>
                </header>
                <div class="page-card__details">
                    <div>
                        <span class="pill subtle"><?= __('pages.admin.fields.visible') ?></span>
                        <strong><?= !empty($page['visible']) ? __('pages.admin.yes') : __('pages.admin.no') ?></strong>
                    </div>
                    <div>
                        <span class="pill subtle"><?= __('pages.admin.fields.show_in_menu') ?></span>
                        <strong><?= !empty($page['show_in_menu']) ? __('pages.admin.yes') : __('pages.admin.no') ?></strong>
                    </div>
                    <div>
                        <span class="pill subtle"><?= __('pages.admin.fields.menu_order') ?></span>
                        <strong><?= (int)($page['menu_order'] ?? 0) ?></strong>
                    </div>
                </div>
                <div class="page-card__actions">
                    <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/edit/' . (int)$page['id']) ?>"><?= __('pages.admin.action.edit') ?></a>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/delete/' . (int)$page['id']) ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn ghost danger"><?= __('pages.admin.action.delete') ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (empty($pages)): ?>
            <div class="empty-state">
                <h3><?= __('pages.admin.empty') ?></h3>
                <p class="muted"><?= __('pages.admin.empty_hint') ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../Admin/views/layout.php';
