<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('articles.settings.title') ?></p>
            <h3><?= __('articles.settings.subtitle') ?></h3>
        </div>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="muted"><?= __('articles.settings.description') ?></div>
        <div class="module-settings-grid">
            <label class="field checkbox">
                <input type="checkbox" name="articles_show_author" value="1" <?= !empty($settings['show_author']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.show_author') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="articles_show_date" value="1" <?= !empty($settings['show_date']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.show_date') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="articles_show_likes" value="1" <?= !empty($settings['show_likes']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.show_likes') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="articles_show_views" value="1" <?= !empty($settings['show_views']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.show_views') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="articles_show_tags" value="1" <?= !empty($settings['show_tags']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.show_tags') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="articles_description_enabled" value="1" <?= !empty($settings['description_enabled']) ? 'checked' : '' ?>>
                <span><?= __('articles.settings.description_enabled') ?></span>
            </label>
        </div>
        <div class="grid two articles-settings-grid">
            <label class="field">
                <span>Columns per row</span>
                <select name="articles_grid_cols">
                    <?php foreach ([1,2,3,4,5,6] as $n): ?>
                        <option value="<?= $n ?>" <?= (int)($settings['grid_cols'] ?? 3) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Articles per page</span>
                <select name="articles_per_page">
                    <?php foreach ([3,6,9,12,18,24] as $n): ?>
                        <option value="<?= $n ?>" <?= (int)($settings['per_page'] ?? 6) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO title (RU) for /articles</span>
                <input type="text" name="articles_seo_title_ru" value="<?= htmlspecialchars((string)($settings['seo_title_ru'] ?? '')) ?>" placeholder="Статьи — ваш проект">
            </label>
            <label class="field">
                <span>SEO title (EN) for /articles</span>
                <input type="text" name="articles_seo_title_en" value="<?= htmlspecialchars((string)($settings['seo_title_en'] ?? '')) ?>" placeholder="Articles — your project">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO description (RU) for /articles</span>
                <textarea name="articles_seo_desc_ru" rows="3" placeholder="Краткое описание страницы статей"><?= htmlspecialchars((string)($settings['seo_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field">
                <span>SEO description (EN) for /articles</span>
                <textarea name="articles_seo_desc_en" rows="3" placeholder="Short description for articles page"><?= htmlspecialchars((string)($settings['seo_desc_en'] ?? '')) ?></textarea>
            </label>
        </div>
        <label class="field">
            <span>Папка загрузки по умолчанию</span>
            <input type="text" name="articles_default_upload_folder" value="<?= htmlspecialchars((string)($settings['default_upload_folder'] ?? '')) ?>" placeholder="например: masters/moscow">
            <small class="muted">Подпапка внутри `/storage/uploads/articles`, которая будет подставляться в форму статьи автоматически.</small>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('articles.settings.save') ?></button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = __('articles.settings.page_title');
$flash = (($_GET['msg'] ?? '') === 'saved') ? 'Saved' : null;
include APP_ROOT . '/modules/Admin/views/layout.php';
