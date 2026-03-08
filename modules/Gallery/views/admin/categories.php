<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$mode = $mode ?? 'list';
$isList = $mode === 'list';
$cat = $category ?? null;
ob_start();
?>

<?php if ($isList): ?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Gallery</p>
            <h3>Categories</h3>
        </div>
        <div class="u-flex u-gap-half">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload">← Gallery</a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/gallery/categories/create">+ New Category</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name EN</th>
                    <th>Name RU</th>
                    <th>Slug / Folder</th>
                    <th>Position</th>
                    <th>Enabled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td>
                        <?php if (!empty($c['image_url'])): ?>
                            <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="" class="category-thumb">
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($c['name_en']) ?></td>
                    <td><?= htmlspecialchars($c['name_ru']) ?></td>
                    <td>
                        <code><?= htmlspecialchars($c['slug']) ?></code>
                        <div class="muted u-font-08em">/storage/uploads/gallery/<?= htmlspecialchars($c['slug']) ?>/</div>
                    </td>
                    <td><?= (int)$c['position'] ?></td>
                    <td>
                        <?php if ($c['enabled']): ?>
                            <span class="pill pill-accent-dark">Yes</span>
                        <?php else: ?>
                            <span class="pill">No</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/gallery/categories/edit/<?= (int)$c['id'] ?>">Edit</a>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/categories/delete/<?= (int)$c['id'] ?>" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <button type="submit" class="btn danger small">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="7" class="muted">No categories yet. Images upload to the root folder.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Gallery</p>
            <h3><?= $mode === 'edit' ? 'Edit Category' : 'New Category' ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">← Back</a>
    </div>
    <form method="post"
          action="<?= $mode === 'edit' ? $ap . '/gallery/categories/edit/' . (int)($cat['id'] ?? 0) : $ap . '/gallery/categories/create' ?>"
          class="stack"
          enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="grid two">
            <label class="field">
                <span>Name EN</span>
                <input type="text" name="name_en" value="<?= htmlspecialchars($cat['name_en'] ?? '') ?>" required>
            </label>
            <label class="field">
                <span>Name RU</span>
                <input type="text" name="name_ru" value="<?= htmlspecialchars($cat['name_ru'] ?? '') ?>">
            </label>
        </div>

        <label class="field">
            <span>Slug <span class="muted">(also used as upload subfolder name)</span></span>
            <input type="text" name="slug" value="<?= htmlspecialchars($cat['slug'] ?? '') ?>" placeholder="Auto-generated from name">
            <?php if (!empty($cat['slug'])): ?>
                <span class="muted u-font-085em">
                    Files upload to: <code>/storage/uploads/gallery/<?= htmlspecialchars($cat['slug']) ?>/</code>
                </span>
            <?php endif; ?>
        </label>

        <label class="field">
            <span>Category Image</span>
            <input type="file" name="image" accept="image/*" onchange="previewGcatImage(this)">
            <input type="text" name="image_url" id="gcat_image_url"
                   placeholder="Or paste URL"
                   value="<?= htmlspecialchars($cat['image_url'] ?? '') ?>">
            <div id="gcat_image_preview" class="<?= empty($cat['image_url']) ? 'u-hide' : '' ?>">
                <?php if (!empty($cat['image_url'])): ?>
                    <img src="<?= htmlspecialchars($cat['image_url']) ?>"
                         alt="preview"
                         class="category-preview-image">
                <?php endif; ?>
            </div>
        </label>

        <div class="grid two">
            <label class="field">
                <span>Position</span>
                <input type="number" name="position" value="<?= (int)($cat['position'] ?? 0) ?>" min="0">
            </label>
            <label class="field category-enabled-field">
                <input type="checkbox" name="enabled" value="1" <?= ($cat === null || !empty($cat['enabled'])) ? 'checked' : '' ?>>
                <span>Enabled</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary">Save</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">Cancel</a>
        </div>
    </form>
</div>

<script>
function previewGcatImage(input) {
    const preview = document.getElementById('gcat_image_preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.style.display = '';
            preview.innerHTML = '<img src="'+e.target.result+'" class="category-preview-image">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
document.getElementById('gcat_image_url')?.addEventListener('input', function() {
    const preview = document.getElementById('gcat_image_preview');
    if (this.value) {
        preview.style.display = '';
        preview.innerHTML = '<img src="'+this.value+'" class="category-preview-image">';
    }
});
</script>
<?php endif; ?>

<?php
$pageTitle = $title ?? 'Gallery Categories';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
