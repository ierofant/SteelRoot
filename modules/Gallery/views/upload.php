<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$categories = $categories ?? [];
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('gallery.upload.title') ?></p>
            <h3><?= __('gallery.upload.subtitle') ?></h3>
        </div>
        <div class="u-flex u-gap-half">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">Categories</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('gallery.upload.back_admin') ?></a>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="stack" id="gallery-upload-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span><?= __('gallery.upload.field.file') ?></span>
            <input type="file" name="image" required id="gallery-file-input" accept="image/*">
            <div id="gallery-preview" class="gallery-preview-wrap u-hide">
                <img id="gallery-preview-img" src="" alt="preview" class="gallery-preview-image">
            </div>
        </label>
        <?php if ($showEn && $showRu): ?>
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
                <textarea name="description_en" rows="3"></textarea>
            </label>
            <label class="field">
                <span><?= __('gallery.upload.field.description_ru') ?></span>
                <textarea name="description_ru" rows="3"></textarea>
            </label>
        </div>
        <?php elseif ($showEn): ?>
        <label class="field">
            <span><?= __('gallery.upload.field.title_en') ?></span>
            <input type="text" name="title_en">
        </label>
        <label class="field">
            <span><?= __('gallery.upload.field.description_en') ?></span>
            <textarea name="description_en" rows="3"></textarea>
        </label>
        <?php else: ?>
        <label class="field">
            <span><?= __('gallery.upload.field.title_ru') ?></span>
            <input type="text" name="title_ru">
        </label>
        <label class="field">
            <span><?= __('gallery.upload.field.description_ru') ?></span>
            <textarea name="description_ru" rows="3"></textarea>
        </label>
        <?php endif; ?>
        <label class="field">
            <span><?= __('gallery.upload.field.tags') ?></span>
            <input type="text" name="tags">
        </label>
        <label class="field">
            <span>Folder</span>
            <select name="target_folder" id="target-folder-select">
                <option value="">— root (no subfolder) —</option>
                <?php foreach ($folders ?? [] as $f): ?>
                    <option value="<?= htmlspecialchars($f) ?>">/<?= htmlspecialchars($f) ?>/</option>
                <?php endforeach; ?>
                <option value="__new__">+ New folder…</option>
            </select>
            <input type="text" name="target_folder_new" id="target-folder-new"
                   placeholder="new-folder-name (a-z, 0-9, hyphen)"
                   pattern="[a-zA-Z0-9_\-]+"
                   class="gallery-new-folder-input u-hide">
        </label>
        <label class="field">
            <span><?= __('gallery.upload.field.category') ?></span>
            <select name="category_id">
                <option value="">— No category —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>">
                        <?= htmlspecialchars($cat['name_en']) ?><?= $cat['name_ru'] ? ' / ' . htmlspecialchars($cat['name_ru']) : '' ?>
                        — /<?= htmlspecialchars($cat['slug']) ?>/
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($categories)): ?>
                <span class="muted u-font-085em">
                    <a href="<?= htmlspecialchars($ap) ?>/gallery/categories">Create categories</a> to organise uploads.
                </span>
            <?php endif; ?>
        </label>
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
    <?php
    $paginationPage    = $page ?? 1;
    $paginationTotal   = $total ?? 0;
    $paginationPerPage = $perPage ?? 20;
    $paginationBase    = $ap . '/gallery/upload';
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</div>
<script>
document.getElementById('gallery-file-input').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    const preview = document.getElementById('gallery-preview');
    const img = document.getElementById('gallery-preview-img');
    const reader = new FileReader();
    reader.onload = function(e) {
        img.src = e.target.result;
        preview.classList.remove('u-hide');
    };
    reader.readAsDataURL(file);
});

(function() {
    const sel = document.getElementById('target-folder-select');
    const newInput = document.getElementById('target-folder-new');
    if (!sel || !newInput) return;

    sel.addEventListener('change', function() {
        newInput.classList.toggle('u-hide', this.value !== '__new__');
        newInput.required = this.value === '__new__';
    });

    document.getElementById('gallery-upload-form').addEventListener('submit', function() {
        if (sel.value === '__new__' && newInput.value.trim() !== '') {
            sel.value = newInput.value.trim();
        } else if (sel.value === '__new__') {
            sel.value = '';
        }
    });
})();
</script>
<?php
$title = __('gallery.upload.page_title');
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
