<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('gallery.settings.title') ?></p>
            <h3><?= __('gallery.settings.subtitle') ?></h3>
        </div>
    </div>
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert success"><?= __('gallery.settings.saved') ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="muted"><?= __('gallery.settings.description') ?></div>
        <div class="module-settings-grid">
            <label class="field checkbox">
                <input type="checkbox" name="gallery_show_title" value="1" <?= !empty($settings['show_title']) ? 'checked' : '' ?>>
                <span><?= __('gallery.settings.show_title') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_show_description" value="1" <?= !empty($settings['show_description']) ? 'checked' : '' ?>>
                <span><?= __('gallery.settings.show_description') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_show_likes" value="1" <?= !empty($settings['show_likes']) ? 'checked' : '' ?>>
                <span><?= __('gallery.settings.show_likes') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_show_views" value="1" <?= !empty($settings['show_views']) ? 'checked' : '' ?>>
                <span><?= __('gallery.settings.show_views') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_show_tags" value="1" <?= !empty($settings['show_tags']) ? 'checked' : '' ?>>
                <span><?= __('gallery.settings.show_tags') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_enable_lightbox" value="1" <?= !empty($settings['enable_lightbox']) ? 'checked' : '' ?> id="gallery-lightbox-toggle">
                <span><?= __('gallery.settings.enable_lightbox') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="gallery_lightbox_likes" value="1" <?= !empty($settings['lightbox_likes']) ? 'checked' : '' ?> <?= empty($settings['enable_lightbox']) ? 'disabled' : '' ?>>
                <span><?= __('gallery.settings.lightbox_likes') ?></span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('gallery.settings.save') ?></button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = __('gallery.settings.page_title');
include APP_ROOT . '/modules/Admin/views/layout.php';
