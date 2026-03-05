<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$mode = $mode ?? 'list';
$isList = $mode === 'list';
$isCreate = $mode === 'create';
$isEdit = $mode === 'edit';
$cat = $category ?? null;
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
ob_start();
?>

<?php if ($isList): ?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Articles</p>
            <h3>Categories</h3>
        </div>
        <div style="display:flex;gap:.5rem">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles">← Articles</a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/articles/categories/create">+ New Category</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Image</th>
                    <?php if ($showEn): ?><th>Name EN</th><?php endif; ?>
                    <?php if ($showRu): ?><th>Name RU</th><?php endif; ?>
                    <th>Slug</th>
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
                            <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="" style="width:48px;height:32px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($showEn): ?><td><?= htmlspecialchars($c['name_en']) ?></td><?php endif; ?>
                    <?php if ($showRu): ?><td><?= htmlspecialchars($c['name_ru']) ?></td><?php endif; ?>
                    <td><code><?= htmlspecialchars($c['slug']) ?></code></td>
                    <td><?= (int)$c['position'] ?></td>
                    <td>
                        <?php if ($c['enabled']): ?>
                            <span class="pill" style="background:var(--accent);color:#000">Yes</span>
                        <?php else: ?>
                            <span class="pill">No</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/articles/categories/edit/<?= (int)$c['id'] ?>">Edit</a>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/articles/categories/delete/<?= (int)$c['id'] ?>" onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <button type="submit" class="btn danger small">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="<?= 5 + (int)$showEn + (int)$showRu ?>" class="muted">No categories yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Articles</p>
            <h3><?= $isEdit ? 'Edit Category' : 'New Category' ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles/categories">← Back</a>
    </div>
    <form method="post"
          action="<?= $isEdit ? $ap . '/articles/categories/edit/' . (int)($cat['id'] ?? 0) : $ap . '/articles/categories/create' ?>"
          class="stack"
          enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <?php if ($showEn && $showRu): ?>
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
        <?php elseif ($showEn): ?>
        <label class="field">
            <span>Name</span>
            <input type="text" name="name_en" value="<?= htmlspecialchars($cat['name_en'] ?? '') ?>" required>
        </label>
        <?php else: ?>
        <label class="field">
            <span>Name</span>
            <input type="text" name="name_ru" value="<?= htmlspecialchars($cat['name_ru'] ?? '') ?>" required>
        </label>
        <?php endif; ?>

        <label class="field">
            <span>Slug</span>
            <input type="text" name="slug" value="<?= htmlspecialchars($cat['slug'] ?? '') ?>" placeholder="Auto-generated from name if empty">
        </label>

        <label class="field">
            <span>Image</span>
            <input type="file" name="image" accept="image/*" onchange="previewCatImage(this)">
            <input type="text" name="image_url" id="cat_image_url"
                   placeholder="Or paste URL"
                   value="<?= htmlspecialchars($cat['image_url'] ?? '') ?>">
            <div id="cat_image_preview" <?= empty($cat['image_url']) ? 'style="display:none"' : '' ?>>
                <?php if (!empty($cat['image_url'])): ?>
                    <img src="<?= htmlspecialchars($cat['image_url']) ?>"
                         alt="preview"
                         style="max-width:200px;max-height:120px;border-radius:6px;margin-top:.5rem">
                <?php endif; ?>
            </div>
        </label>

        <div class="grid two">
            <label class="field">
                <span>Position</span>
                <input type="number" name="position" value="<?= (int)($cat['position'] ?? 0) ?>" min="0">
            </label>
            <label class="field" style="flex-direction:row;align-items:center;gap:.75rem;padding-top:1.5rem">
                <input type="checkbox" name="enabled" value="1" <?= ($cat === null || !empty($cat['enabled'])) ? 'checked' : '' ?>>
                <span>Enabled</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary">Save</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles/categories">Cancel</a>
        </div>
    </form>
</div>

<script>
function previewCatImage(input) {
    const preview = document.getElementById('cat_image_preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.style.display = '';
            preview.innerHTML = '<img src="'+e.target.result+'" style="max-width:200px;max-height:120px;border-radius:6px;margin-top:.5rem">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
document.getElementById('cat_image_url')?.addEventListener('input', function() {
    const preview = document.getElementById('cat_image_preview');
    if (this.value) {
        preview.style.display = '';
        preview.innerHTML = '<img src="'+this.value+'" style="max-width:200px;max-height:120px;border-radius:6px;margin-top:.5rem">';
    }
});
</script>
<?php endif; ?>

<?php
$pageTitle = $title ?? 'Article Categories';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
