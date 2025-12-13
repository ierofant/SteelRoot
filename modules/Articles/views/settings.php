<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('articles.settings.title') ?></p>
            <h3><?= __('articles.settings.subtitle') ?></h3>
        </div>
    </div>
    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert success"><?= __('articles.settings.saved') ?></div>
    <?php endif; ?>
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
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('articles.settings.save') ?></button>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = __('articles.settings.page_title');
include APP_ROOT . '/modules/Admin/views/layout.php';
