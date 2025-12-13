<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('sitemap.title') ?></p>
            <h3><?= __('sitemap.subtitle') ?></h3>
            <p class="muted"><?= __('sitemap.description') ?></p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') ?>"><?= __('sitemap.action.back_admin') ?></a>
    </div>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?><div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field checkbox">
                <input type="checkbox" name="include_home" value="1" <?= !empty($config['include_home']) ? 'checked' : '' ?>>
                <span><?= __('sitemap.include.home') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="include_contact" value="1" <?= !empty($config['include_contact']) ? 'checked' : '' ?>>
                <span><?= __('sitemap.include.contact') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="include_articles" value="1" <?= !empty($config['include_articles']) ? 'checked' : '' ?>>
                <span><?= __('sitemap.include.articles') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="include_gallery" value="1" <?= !empty($config['include_gallery']) ? 'checked' : '' ?>>
                <span><?= __('sitemap.include.gallery') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="include_tags" value="1" <?= !empty($config['include_tags']) ? 'checked' : '' ?>>
                <span><?= __('sitemap.include.tags') ?></span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('sitemap.action.save') ?></button>
            <span class="muted"><?= __('sitemap.note.apply') ?></span>
        </div>
    </form>
    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/sitemap/clear-cache') ?>" class="form-actions">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="btn ghost"><?= __('sitemap.action.clear_cache') ?></button>
        <span class="muted"><?= __('sitemap.note.clear_cache') ?></span>
    </form>
</div>
<?php
$title = __('sitemap.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
