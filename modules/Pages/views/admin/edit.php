<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= $isNew ? __('pages.admin.create') : __('pages.admin.edit') ?></p>
            <h3><?= $isNew ? __('pages.admin.create') : __('pages.admin.edit') ?></h3>
        </div>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field">
                <span><?= __('pages.admin.fields.slug') ?></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($page['slug'] ?? '') ?>" required>
                <small class="muted"><?= __('pages.admin.help.slug') ?></small>
            </label>
            <label class="field">
                <span><?= __('pages.admin.fields.menu_order') ?></span>
                <input type="number" name="menu_order" value="<?= (int)($page['menu_order'] ?? 0) ?>">
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pages.admin.fields.title_en') ?></span>
                <input type="text" name="title_en" value="<?= htmlspecialchars($page['title_en'] ?? '') ?>">
            </label>
            <label class="field">
                <span><?= __('pages.admin.fields.title_ru') ?></span>
                <input type="text" name="title_ru" value="<?= htmlspecialchars($page['title_ru'] ?? '') ?>">
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pages.admin.fields.content_en') ?></span>
                <textarea name="content_en" rows="6"><?= htmlspecialchars($page['content_en'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('pages.admin.fields.content_ru') ?></span>
                <textarea name="content_ru" rows="6"><?= htmlspecialchars($page['content_ru'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pages.admin.fields.meta_title_en') ?></span>
                <input type="text" name="meta_title_en" value="<?= htmlspecialchars($page['meta_title_en'] ?? '') ?>">
            </label>
            <label class="field">
                <span><?= __('pages.admin.fields.meta_title_ru') ?></span>
                <input type="text" name="meta_title_ru" value="<?= htmlspecialchars($page['meta_title_ru'] ?? '') ?>">
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pages.admin.fields.meta_description_en') ?></span>
                <textarea name="meta_description_en" rows="3"><?= htmlspecialchars($page['meta_description_en'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('pages.admin.fields.meta_description_ru') ?></span>
                <textarea name="meta_description_ru" rows="3"><?= htmlspecialchars($page['meta_description_ru'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="grid two">
            <label class="field checkbox">
                <input type="checkbox" name="visible" value="1" <?= !empty($page['visible']) ? 'checked' : '' ?>>
                <span><?= __('pages.admin.fields.visible') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="show_in_menu" value="1" <?= !empty($page['show_in_menu']) ? 'checked' : '' ?>>
                <span><?= __('pages.admin.fields.show_in_menu') ?></span>
            </label>
        </div>

        <div class="form-actions" style="gap:8px;">
            <button type="submit" class="btn primary"><?= __('pages.admin.action.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/pages') ?>"><?= __('pages.admin.action.cancel') ?></a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../Admin/views/layout.php';
