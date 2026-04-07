<?php ob_start(); ?>
<section class="pages-editor stack">
<div class="card stack pages-editor__shell">
    <div class="card-header pages-editor__header">
        <div class="pages-editor__headline">
            <p class="eyebrow"><?= __('pages.admin.title') ?></p>
            <h3><?= $isNew ? __('pages.admin.action.create') : __('pages.admin.action.edit') ?></h3>
        </div>
        <div class="header-actions pages-editor__actions">
            <?php if (!$isNew && !empty($page['slug'])): ?>
                <a class="btn ghost" href="/<?= htmlspecialchars($page['slug']) ?>" target="_blank" rel="noopener"><?= __('pages.admin.action.open') ?></a>
            <?php endif; ?>
            <button type="submit" form="pages-form" class="btn primary"><?= __('pages.admin.action.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($adminPrefix . '/pages') ?>"><?= __('pages.admin.action.cancel') ?></a>
        </div>
    </div>
    <form method="post" id="pages-form" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <section class="form-section">
            <header>
                <p class="eyebrow"><?= __('pages.admin.section.general') ?></p>
                <h4><?= __('pages.admin.section.general_sub') ?></h4>
            </header>
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
                <label class="field locale-en">
                    <span><?= __('pages.admin.fields.title_en') ?></span>
                    <input type="text" name="title_en" value="<?= htmlspecialchars($page['title_en'] ?? '') ?>">
                </label>
                <label class="field locale-ru">
                    <span><?= __('pages.admin.fields.title_ru') ?></span>
                    <input type="text" name="title_ru" value="<?= htmlspecialchars($page['title_ru'] ?? '') ?>">
                </label>
            </div>
        </section>

        <section class="form-section">
            <header>
                <p class="eyebrow"><?= __('pages.admin.section.content') ?></p>
                <h4><?= __('pages.admin.section.content_sub') ?></h4>
            </header>
            <div class="grid two">
                <label class="field locale-en">
                    <span><?= __('pages.admin.fields.content_en') ?></span>
                    <textarea name="content_en" rows="7"><?= htmlspecialchars($page['content_en'] ?? '') ?></textarea>
                </label>
                <label class="field locale-ru">
                    <span><?= __('pages.admin.fields.content_ru') ?></span>
                    <textarea name="content_ru" rows="7"><?= htmlspecialchars($page['content_ru'] ?? '') ?></textarea>
                </label>
            </div>
            <label class="field">
                <span><?= __('pages.admin.fields.tags') ?> (#tag #tag-two)</span>
                <input type="text" name="tags" value="<?= htmlspecialchars($tagsInput ?? '') ?>" placeholder="#tattoo #guide #faq">
            </label>
        </section>

        <section class="form-section">
            <header>
                <p class="eyebrow"><?= __('pages.admin.section.meta') ?></p>
                <h4><?= __('pages.admin.section.meta_sub') ?></h4>
            </header>
            <div class="grid two">
                <label class="field locale-en">
                    <span><?= __('pages.admin.fields.meta_title_en') ?></span>
                    <input type="text" name="meta_title_en" value="<?= htmlspecialchars($page['meta_title_en'] ?? '') ?>">
                </label>
                <label class="field locale-ru">
                    <span><?= __('pages.admin.fields.meta_title_ru') ?></span>
                    <input type="text" name="meta_title_ru" value="<?= htmlspecialchars($page['meta_title_ru'] ?? '') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field locale-en">
                    <span><?= __('pages.admin.fields.meta_description_en') ?></span>
                    <textarea name="meta_description_en" rows="3"><?= htmlspecialchars($page['meta_description_en'] ?? '') ?></textarea>
                </label>
                <label class="field locale-ru">
                    <span><?= __('pages.admin.fields.meta_description_ru') ?></span>
                    <textarea name="meta_description_ru" rows="3"><?= htmlspecialchars($page['meta_description_ru'] ?? '') ?></textarea>
                </label>
            </div>
        </section>

        <section class="form-section">
            <div class="grid two pages-editor__flags">
                <label class="field checkbox">
                    <input type="checkbox" name="visible" value="1" <?= !empty($page['visible']) ? 'checked' : '' ?>>
                    <span><?= __('pages.admin.fields.visible') ?></span>
                </label>
                <label class="field checkbox">
                    <input type="checkbox" name="show_in_menu" value="1" <?= !empty($page['show_in_menu']) ? 'checked' : '' ?>>
                    <span><?= __('pages.admin.fields.show_in_menu') ?></span>
                </label>
            </div>
        </section>

        <section class="form-section">
            <header>
                <p class="eyebrow"><?= __('pages.admin.section.comments') ?></p>
                <h4><?= __('pages.admin.section.comments_sub') ?></h4>
            </header>
            <label class="field">
                <span><?= __('pages.admin.fields.comments_mode') ?></span>
                <select name="comments_mode">
                    <option value="default" <?= ($commentsMode ?? 'default') === 'default' ? 'selected' : '' ?>><?= __('pages.admin.comments_mode.default') ?></option>
                    <option value="enabled" <?= ($commentsMode ?? 'default') === 'enabled' ? 'selected' : '' ?>><?= __('pages.admin.comments_mode.enabled') ?></option>
                    <option value="disabled" <?= ($commentsMode ?? 'default') === 'disabled' ? 'selected' : '' ?>><?= __('pages.admin.comments_mode.disabled') ?></option>
                </select>
            </label>
        </section>
    </form>
</div>
</section>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../Admin/views/layout.php';
