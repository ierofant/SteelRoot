<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('gallery.settings.title') ?></p>
            <h3><?= __('gallery.settings.subtitle') ?></h3>
        </div>
    </div>
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
        <div class="field">
            <span><?= __('gallery.settings.per_page') ?></span>
            <input type="number" name="gallery_per_page" min="1" max="100"
                value="<?= (int)($settings['per_page'] ?? 9) ?>">
        </div>
        <div class="grid three">
            <label class="field">
                <span><?= __('gallery.settings.thumb_width') ?></span>
                <input type="number" name="gallery_thumb_width" min="160" max="2000"
                    value="<?= (int)($settings['thumb_width'] ?? 640) ?>">
            </label>
            <label class="field">
                <span><?= __('gallery.settings.medium_width') ?></span>
                <input type="number" name="gallery_medium_width" min="320" max="4000"
                    value="<?= (int)($settings['medium_width'] ?? 1600) ?>">
            </label>
            <label class="field">
                <span><?= __('gallery.settings.variants_format') ?></span>
                <select name="gallery_variants_format">
                    <option value="webp" <?= (($settings['variants_format'] ?? 'webp') === 'webp') ? 'selected' : '' ?>>WEBP</option>
                    <option value="source" <?= (($settings['variants_format'] ?? 'webp') === 'source') ? 'selected' : '' ?>>Source format</option>
                </select>
            </label>
        </div>
        <p class="muted"><?= __('gallery.settings.variants_hint') ?></p>
        <div class="grid two">
            <label class="field">
                <span>SEO title (RU) for /gallery</span>
                <input type="text" name="gallery_seo_title_ru" value="<?= htmlspecialchars((string)($settings['seo_title_ru'] ?? '')) ?>" placeholder="Галерея — ваш проект">
            </label>
            <label class="field">
                <span>SEO title (EN) for /gallery</span>
                <input type="text" name="gallery_seo_title_en" value="<?= htmlspecialchars((string)($settings['seo_title_en'] ?? '')) ?>" placeholder="Gallery — your project">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO description (RU) for /gallery</span>
                <textarea name="gallery_seo_desc_ru" rows="3" placeholder="Краткое описание страницы галереи"><?= htmlspecialchars((string)($settings['seo_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field">
                <span>SEO description (EN) for /gallery</span>
                <textarea name="gallery_seo_desc_en" rows="3" placeholder="Short description for gallery page"><?= htmlspecialchars((string)($settings['seo_desc_en'] ?? '')) ?></textarea>
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
