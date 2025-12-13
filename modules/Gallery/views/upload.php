<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('gallery.upload.title') ?></p>
            <h3><?= __('gallery.upload.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('gallery.upload.back_admin') ?></a>
    </div>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span><?= __('gallery.upload.field.file') ?></span>
            <input type="file" name="image" required>
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('gallery.upload.field.title_en') ?></span>
                <input type="text" name="title_en">
            </label>
            <label class="field">
                <span><?= __('gallery.upload.field.title_ru') ?></span>
                <input type="text" name="title_ru">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span><?= __('gallery.upload.field.description_en') ?></span>
                <textarea name="description_en" rows="4"></textarea>
            </label>
            <label class="field">
                <span><?= __('gallery.upload.field.description_ru') ?></span>
                <textarea name="description_ru" rows="4"></textarea>
            </label>
        </div>
        <label class="field">
            <span><?= __('gallery.upload.field.tags') ?></span>
            <input type="text" name="tags">
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('gallery.upload.field.category') ?></span>
                <input type="text" name="category" list="cat-list" placeholder="<?= __('gallery.upload.placeholder.category') ?>">
                <?php if (!empty($categories)): ?>
                    <datalist id="cat-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                <?php endif; ?>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('gallery.upload.action.upload') ?></button>
            <a class="btn ghost" href="/gallery"><?= __('gallery.upload.action.open_gallery') ?></a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('gallery.upload.recent_title') ?></p>
            <h3><?= __('gallery.upload.recent_subtitle') ?></h3>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th><?= __('gallery.upload.table.preview') ?></th><th><?= __('gallery.upload.table.title') ?></th><th><?= __('gallery.upload.table.date') ?></th><th><?= __('gallery.upload.table.actions') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($items ?? [] as $item): ?>
                    <tr>
                        <td><img src="<?= htmlspecialchars($item['path_thumb'] ?? '') ?>" alt="" class="thumb"></td>
                        <td><?= htmlspecialchars($item['title_ru'] ?: ($item['title_en'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($item['created_at'] ?? '') ?></td>
                        <td class="actions">
                            <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/gallery/edit/<?= (int)$item['id'] ?>"><?= __('gallery.upload.action.edit') ?></a>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/delete/<?= (int)$item['id'] ?>" onsubmit="return confirm('<?= __('gallery.upload.action.confirm_delete') ?>');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="btn danger small"><?= __('gallery.upload.action.delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="4" class="muted"><?= __('gallery.upload.empty') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$title = __('gallery.upload.page_title');
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
