<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$mode = $mode ?? 'list';
$isList = $mode === 'list';
$cat = $category ?? null;
$adminPrefix = $adminPrefix ?? $ap;
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
ob_start();
?>

<?php if ($isList): ?>
<section class="gallery-categories-admin stack">
    <div class="card stack gallery-categories-admin__shell">
        <div class="card-header gallery-categories-admin__header">
            <div>
                <p class="eyebrow">Gallery</p>
                <h3>Categories</h3>
                <p class="muted">Управление рубриками галереи, превью и SEO метаданными.</p>
            </div>
            <div class="gallery-categories-admin__toolbar">
                <span class="pill subtle"><?= count($categories ?? []) ?></span>
                <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload">К загрузкам</a>
                <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/gallery/categories/create">Новая категория</a>
            </div>
        </div>
        <div class="gallery-categories-list">
            <?php foreach ($categories as $c): ?>
                <article class="gallery-category-card">
                    <div class="gallery-category-card__media">
                        <?php if (!empty($c['image_url'])): ?>
                            <img src="<?= htmlspecialchars($c['image_url']) ?>" alt="" class="category-thumb">
                        <?php else: ?>
                            <div class="gallery-category-card__placeholder">IMG</div>
                        <?php endif; ?>
                    </div>
                    <div class="gallery-category-card__body">
                        <div class="gallery-category-card__head">
                            <div>
                                <p class="eyebrow">Category #<?= (int)$c['id'] ?></p>
                                <h4><?= htmlspecialchars(($showRu ? ($c['name_ru'] ?? '') : '') ?: ($showEn ? ($c['name_en'] ?? '') : '') ?: (($c['name_ru'] ?? '') ?: (($c['name_en'] ?? '') ?: 'Без названия'))) ?></h4>
                                <?php if ($showEn && $showRu): ?>
                                    <p class="muted"><?= htmlspecialchars($c['name_en'] ?: 'EN title is empty') ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="pill<?= !empty($c['enabled']) ? ' pill-accent-dark' : '' ?>">
                                <?= !empty($c['enabled']) ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </div>
                        <div class="gallery-category-card__meta">
                            <div>
                                <span class="pill subtle">Slug</span>
                                <strong><?= htmlspecialchars($c['slug']) ?></strong>
                                <small class="muted">/storage/uploads/gallery/<?= htmlspecialchars($c['slug']) ?>/</small>
                            </div>
                            <div>
                                <span class="pill subtle">Position</span>
                                <strong><?= (int)$c['position'] ?></strong>
                            </div>
                            <div>
                                <span class="pill subtle">SEO</span>
                                <strong><?= trim((string)($c['meta_title_ru'] ?? '')) !== '' || trim((string)($c['meta_title_en'] ?? '')) !== '' ? 'Configured' : 'Default' ?></strong>
                            </div>
                        </div>
                        <div class="gallery-category-card__actions">
                            <a class="btn ghost small" href="/gallery/category/<?= rawurlencode((string)$c['slug']) ?>" target="_blank" rel="noopener">Открыть</a>
                            <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/gallery/categories/edit/<?= (int)$c['id'] ?>">Редактировать</a>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/categories/delete/<?= (int)$c['id'] ?>" onsubmit="return confirm('Delete this category?')">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <button type="submit" class="btn danger small">Удалить</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <h3>No categories yet</h3>
                    <p class="muted">Создайте категорию, чтобы сортировать загрузки, папки и SEO для публичных страниц.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php else: ?>

<section class="gallery-categories-admin stack">
<div class="card stack gallery-category-form">
    <div class="card-header">
        <div>
            <p class="eyebrow">Gallery</p>
            <h3><?= $mode === 'edit' ? 'Edit Category' : 'New Category' ?></h3>
            <p class="muted">Slug, обложка, порядок отображения и SEO для страницы категории.</p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">← Back</a>
    </div>
    <form method="post"
          action="<?= $mode === 'edit' ? $ap . '/gallery/categories/edit/' . (int)($cat['id'] ?? 0) : $ap . '/gallery/categories/create' ?>"
          class="stack"
          enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <section class="form-section">
            <header>
                <p class="eyebrow">General</p>
                <h4>Basic category data</h4>
            </header>
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
                    <span>Name EN</span>
                    <input type="text" name="name_en" value="<?= htmlspecialchars($cat['name_en'] ?? '') ?>" required>
                </label>
            <?php else: ?>
                <label class="field">
                    <span>Name RU</span>
                    <input type="text" name="name_ru" value="<?= htmlspecialchars($cat['name_ru'] ?? '') ?>" required>
                </label>
            <?php endif; ?>

            <label class="field">
                <span>Slug <span class="muted">(also used as upload subfolder name)</span></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($cat['slug'] ?? '') ?>" placeholder="Auto-generated from name">
                <?php if (!empty($cat['slug'])): ?>
                    <span class="muted u-font-085em">
                        Files upload to: <code>/storage/uploads/gallery/<?= htmlspecialchars($cat['slug']) ?>/</code>
                    </span>
                <?php endif; ?>
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
        </section>

        <section class="form-section">
            <header>
                <p class="eyebrow">Media</p>
                <h4>Category cover and preview</h4>
            </header>
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
        </section>

        <section class="form-section">
            <header>
                <p class="eyebrow">SEO</p>
                <h4>Category metadata</h4>
            </header>
            <?php if ($showEn && $showRu): ?>
                <div class="grid two">
                    <label class="field">
                        <span>Meta title EN</span>
                        <input type="text" name="meta_title_en" value="<?= htmlspecialchars((string)($cat['meta_title_en'] ?? '')) ?>" placeholder="Blackwork gallery category">
                    </label>
                    <label class="field">
                        <span>Meta title RU</span>
                        <input type="text" name="meta_title_ru" value="<?= htmlspecialchars((string)($cat['meta_title_ru'] ?? '')) ?>" placeholder="Категория галереи blackwork">
                    </label>
                </div>

                <div class="grid two">
                    <label class="field">
                        <span>Meta description EN</span>
                        <textarea name="meta_description_en" rows="4" placeholder="Short SEO description for the public gallery category page"><?= htmlspecialchars((string)($cat['meta_description_en'] ?? '')) ?></textarea>
                    </label>
                    <label class="field">
                        <span>Meta description RU</span>
                        <textarea name="meta_description_ru" rows="4" placeholder="Краткое SEO-описание страницы категории галереи"><?= htmlspecialchars((string)($cat['meta_description_ru'] ?? '')) ?></textarea>
                    </label>
                </div>
            <?php elseif ($showEn): ?>
                <label class="field">
                    <span>Meta title EN</span>
                    <input type="text" name="meta_title_en" value="<?= htmlspecialchars((string)($cat['meta_title_en'] ?? '')) ?>" placeholder="Blackwork gallery category">
                </label>
                <label class="field">
                    <span>Meta description EN</span>
                    <textarea name="meta_description_en" rows="4" placeholder="Short SEO description for the public gallery category page"><?= htmlspecialchars((string)($cat['meta_description_en'] ?? '')) ?></textarea>
                </label>
            <?php else: ?>
                <label class="field">
                    <span>Meta title RU</span>
                    <input type="text" name="meta_title_ru" value="<?= htmlspecialchars((string)($cat['meta_title_ru'] ?? '')) ?>" placeholder="Категория галереи blackwork">
                </label>
                <label class="field">
                    <span>Meta description RU</span>
                    <textarea name="meta_description_ru" rows="4" placeholder="Краткое SEO-описание страницы категории галереи"><?= htmlspecialchars((string)($cat['meta_description_ru'] ?? '')) ?></textarea>
                </label>
            <?php endif; ?>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn primary">Save</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">Cancel</a>
        </div>
    </form>
</div>
</section>

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
