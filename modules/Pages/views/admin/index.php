<?php ob_start(); ?>
<section class="pages-admin stack">
    <div class="card stack pages-admin__shell">
        <div class="card-header pages-admin__header">
            <div class="pages-admin__headline">
                <p class="eyebrow"><?= __('pages.admin.title') ?></p>
                <h3><?= __('pages.admin.subtitle') ?></h3>
            </div>
            <div class="pages-admin__toolbar">
                <span class="pill subtle"><?= count($pages ?? []) ?></span>
                <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages/create') ?>"><?= __('pages.admin.action.create') ?></a>
            </div>
        </div>

        <div class="pages-grid">
        <?php foreach ($pages as $page): ?>
            <article class="page-card" data-slug="<?= htmlspecialchars($page['slug']) ?>">
                <header class="page-card__header">
                    <p class="eyebrow"><?= __('pages.admin.record', ['id' => (int)$page['id']]) ?></p>
                    <h3><?= htmlspecialchars(($page['title_ru'] ?: $page['title_en']) ?? __('pages.admin.no_title')) ?></h3>
                    <p class="muted"><?= htmlspecialchars($page['slug']) ?></p>
                </header>
                <div class="page-card__details">
                    <div class="page-card__detail">
                        <span class="pill subtle"><?= __('pages.admin.fields.visible') ?></span>
                        <strong><?= !empty($page['visible']) ? __('pages.admin.yes') : __('pages.admin.no') ?></strong>
                    </div>
                    <div class="page-card__detail">
                        <span class="pill subtle"><?= __('pages.admin.fields.show_in_menu') ?></span>
                        <strong><?= !empty($page['show_in_menu']) ? __('pages.admin.yes') : __('pages.admin.no') ?></strong>
                    </div>
                    <div class="page-card__detail">
                        <span class="pill subtle"><?= __('pages.admin.fields.menu_order') ?></span>
                        <strong><?= (int)($page['menu_order'] ?? 0) ?></strong>
                    </div>
                    <div class="page-card__detail">
                        <span class="pill subtle"><?= __('pages.admin.fields.comments_mode') ?></span>
                        <strong><?= htmlspecialchars(__('pages.admin.comments_mode.' . (($page['comments_mode'] ?? 'default') ?: 'default'))) ?></strong>
                    </div>
                </div>
                <div class="page-card__actions">
                    <a class="btn ghost" href="/<?= htmlspecialchars($page['slug']) ?>" target="_blank" rel="noopener"><?= __('pages.admin.action.open') ?></a>
                    <a class="btn ghost" href="<?= htmlspecialchars($adminPrefix . '/pages/edit/' . (int)$page['id']) ?>"><?= __('pages.admin.action.edit') ?></a>
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
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../Admin/views/layout.php';
