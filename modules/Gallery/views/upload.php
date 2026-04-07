<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$categories = $categories ?? [];
$lm = $localeMode ?? 'multi';
$sort = $sort ?? 'new';
$status = $status ?? '';
$tagFilter = $tagFilter ?? '';
$suggestedTags = $suggestedTags ?? [];
$summary = $summary ?? ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
$returnQuery = array_filter([
    'sort' => $sort !== 'new' ? $sort : '',
    'status' => $status,
    'tag' => $tagFilter,
    'page' => ($page ?? 1) > 1 ? (string)($page ?? 1) : '',
], static fn ($value) => $value !== '');
$returnUrl = $ap . '/gallery/upload' . ($returnQuery !== [] ? '?' . http_build_query($returnQuery) : '');
ob_start();
?>
<div class="gallery-admin-page stack">
<div class="card stack gallery-admin-shell">
    <div class="card-header gallery-admin-hero">
        <div class="gallery-admin-hero__copy">
            <p class="eyebrow"><?= __('gallery.upload.title') ?></p>
            <h3><?= __('gallery.upload.subtitle') ?></h3>
            <p class="muted">Основная рабочая зона: загрузка, разметка, распределение по папкам и категориям.</p>
        </div>
        <div class="u-flex u-gap-half u-flex-wrap gallery-admin-hero__actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/tags">Tags</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">Categories</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('gallery.upload.back_admin') ?></a>
        </div>
    </div>
    <div class="mini-cards gallery-admin-summary">
        <div class="stat-card">
            <p class="muted">Всего работ</p>
            <h3><?= (int)($summary['total'] ?? 0) ?></h3>
        </div>
        <div class="stat-card">
            <p class="muted">На модерации</p>
            <h3><?= (int)($summary['pending'] ?? 0) ?></h3>
        </div>
        <div class="stat-card">
            <p class="muted">Опубликовано</p>
            <h3><?= (int)($summary['approved'] ?? 0) ?></h3>
        </div>
        <div class="stat-card">
            <p class="muted">Отклонено</p>
            <h3><?= (int)($summary['rejected'] ?? 0) ?></h3>
        </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="stack gallery-upload-form" id="gallery-upload-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="gallery-admin-layout">
            <section class="card stack gallery-upload-main">
                <div class="gallery-upload-main__intro">
                    <p class="eyebrow">Upload</p>
                    <h4>Новая работа</h4>
                    <p class="muted">Файл, метаданные и тегирование в одном проходе без лишних переходов.</p>
                </div>
                <label class="field gallery-upload-dropzone">
                    <span><?= __('gallery.upload.field.file') ?></span>
                    <input type="file" name="image" required id="gallery-file-input" accept="image/*">
                    <div id="gallery-preview" class="gallery-preview-wrap u-hide" hidden>
                        <img id="gallery-preview-img" src="" alt="preview" class="gallery-preview-image">
                    </div>
                    <span class="muted u-font-085em">Сначала файл, затем метаданные. Превью появляется сразу до отправки.</span>
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
                            <textarea name="description_en" rows="4"></textarea>
                        </label>
                        <label class="field">
                            <span><?= __('gallery.upload.field.description_ru') ?></span>
                            <textarea name="description_ru" rows="4"></textarea>
                        </label>
                    </div>
                <?php elseif ($showEn): ?>
                    <label class="field">
                        <span><?= __('gallery.upload.field.title_en') ?></span>
                        <input type="text" name="title_en">
                    </label>
                    <label class="field">
                        <span><?= __('gallery.upload.field.description_en') ?></span>
                        <textarea name="description_en" rows="4"></textarea>
                    </label>
                <?php else: ?>
                    <label class="field">
                        <span><?= __('gallery.upload.field.title_ru') ?></span>
                        <input type="text" name="title_ru">
                    </label>
                    <label class="field">
                        <span><?= __('gallery.upload.field.description_ru') ?></span>
                        <textarea name="description_ru" rows="4"></textarea>
                    </label>
                <?php endif; ?>
                <label class="field">
                    <span>Slug</span>
                    <input type="text" name="slug" placeholder="optional-custom-slug">
                    <span class="muted u-font-085em">Если оставить пустым, slug создастся автоматически один раз при загрузке и дальше не будет меняться от title.</span>
                </label>
                <label class="field">
                    <span><?= __('gallery.upload.field.tags') ?></span>
                    <input type="text" name="tags" list="gallery-tags-list" placeholder="#tiger #blackwork #hand">
                    <span class="muted u-font-085em">Для привязки к существующему canonical slug выбирайте тег из подсказок, например `#tiger`, а не `#тигр`.</span>
                </label>
            </section>
            <aside class="card stack gallery-upload-side">
                <div class="stack">
                    <p class="eyebrow">Организация</p>
                    <h4>Папки и категории</h4>
                    <p class="muted">Сначала определите структуру размещения, затем отправляйте файл.</p>
                </div>
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
                    <span><?= __('gallery.upload.field.category') ?> (multi)</span>
                    <select name="category_ids[]" multiple size="<?= max(8, min(14, count($categories) + 2)) ?>">
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
                    <?php else: ?>
                        <span class="muted u-font-085em">Можно выбрать несколько категорий. Первая станет основной для fallback и папки, если folder не задан.</span>
                    <?php endif; ?>
                </label>
                <div class="form-actions gallery-upload-side__actions">
                    <button type="submit" class="btn primary"><?= __('gallery.upload.action.upload') ?></button>
                    <a class="btn ghost" href="/gallery"><?= __('gallery.upload.action.open_gallery') ?></a>
                </div>
            </aside>
        </div>
        <?php if ($suggestedTags !== []): ?>
            <datalist id="gallery-tags-list">
                <?php foreach ($suggestedTags as $tag): ?>
                    <?php $tagName = ltrim((string)($tag['name'] ?? $tag['slug'] ?? ''), '#'); ?>
                    <?php if ($tagName === '') { continue; } ?>
                    <option value="#<?= htmlspecialchars($tagName) ?>">
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
    </form>
</div>

<div class="card gallery-admin-list-card">
    <div class="card-header gallery-admin-list-head">
        <div class="gallery-admin-list-intro">
            <p class="eyebrow"><?= __('gallery.upload.recent_title') ?></p>
            <h3><?= __('gallery.upload.recent_subtitle') ?></h3>
            <p class="muted">Фильтруйте поток, модерируйте карточки и редактируйте записи без потери текущей страницы.</p>
        </div>
        <form method="get" action="<?= htmlspecialchars($ap) ?>/gallery/upload" class="gallery-admin-filters">
            <label class="field u-m-0">
                <span>Сортировка</span>
                <select name="sort" onchange="this.form.submit()">
                    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Сначала новые</option>
                    <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Сначала старые</option>
                    <option value="likes" <?= $sort === 'likes' ? 'selected' : '' ?>>По лайкам</option>
                    <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>По просмотрам</option>
                    <option value="category" <?= $sort === 'category' ? 'selected' : '' ?>>По категориям</option>
                    <option value="tags" <?= $sort === 'tags' ? 'selected' : '' ?>>По тегам</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>По заголовку</option>
                </select>
            </label>
            <label class="field u-m-0">
                <span>Статус</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="" <?= $status === '' ? 'selected' : '' ?>>Все</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </label>
            <label class="field u-m-0">
                <span>Тег</span>
                <input type="text" name="tag" value="<?= htmlspecialchars($tagFilter) ?>" placeholder="#tiger">
            </label>
            <noscript><button type="submit" class="btn ghost small">Применить</button></noscript>
        </form>
    </div>
    <div class="table-wrap gallery-admin-table-wrap">
        <table class="data gallery-admin-table">
            <thead>
                <tr><th><?= __('gallery.upload.table.preview') ?></th><th><?= __('gallery.upload.table.title') ?></th><th>Теги</th><th>Status</th><th><?= __('gallery.upload.table.date') ?></th><th><?= __('gallery.upload.table.actions') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($items ?? [] as $item): ?>
                    <?php $previewSrc = $item['path_thumb'] ?? ($item['path_medium'] ?? ($item['path'] ?? '')); ?>
                    <tr class="gallery-admin-table__row">
                        <td><img src="<?= htmlspecialchars((string)$previewSrc) ?>" alt="" class="thumb gallery-admin-thumb"></td>
                        <td class="gallery-admin-table__title-cell"><?= htmlspecialchars($item['title_ru'] ?: ($item['title_en'] ?? '')) ?></td>
                        <td>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/tags/<?= (int)$item['id'] ?>" class="gallery-admin-tags-form">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                <input type="text" name="tags" list="gallery-tags-list" value="<?= htmlspecialchars((string)($item['tags_display'] ?? '')) ?>" placeholder="#tiger #blackwork">
                                <button type="submit" class="btn ghost small">Теги</button>
                            </form>
                        </td>
                        <td><span class="pill gallery-admin-status gallery-admin-status--<?= htmlspecialchars((string)($item['status'] ?? 'approved')) ?>"><?= htmlspecialchars((string)($item['status'] ?? 'approved')) ?></span></td>
                        <td><?= htmlspecialchars($item['created_at'] ?? '') ?></td>
                        <td class="actions gallery-admin-actions">
                            <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/gallery/edit/<?= (int)$item['id'] ?>?return=<?= urlencode($returnUrl) ?>"><?= __('gallery.upload.action.edit') ?></a>
                            <?php if (($item['status'] ?? 'approved') !== 'approved'): ?>
                                <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/approve/<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($moderateToken ?? '') ?>">
                                    <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                    <button type="submit" class="btn ghost small">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if (($item['status'] ?? 'approved') !== 'rejected'): ?>
                                <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/reject/<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($moderateToken ?? '') ?>">
                                    <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                    <input type="hidden" name="status_note" value="Rejected by moderator">
                                    <button type="submit" class="btn ghost small">Reject</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/delete/<?= (int)$item['id'] ?>" onsubmit="return confirm('<?= __('gallery.upload.action.confirm_delete') ?>');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                <button type="submit" class="btn danger small"><?= __('gallery.upload.action.delete') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="muted gallery-admin-empty"><?= __('gallery.upload.empty') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $paginationPage    = $page ?? 1;
    $paginationTotal   = $total ?? 0;
    $paginationPerPage = $perPage ?? 20;
    $query = array_filter([
        'sort' => $sort !== 'new' ? $sort : '',
        'status' => $status,
        'tag' => $tagFilter,
    ], static fn ($value) => $value !== '');
    $paginationBase    = $ap . '/gallery/upload' . ($query !== [] ? '?' . http_build_query($query) : '');
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</div>
</div>
<script>
(function() {
    const fileInput = document.getElementById('gallery-file-input');
    const preview = document.getElementById('gallery-preview');
    const img = document.getElementById('gallery-preview-img');

    if (fileInput && preview && img) {
        fileInput.addEventListener('change', function() {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                img.removeAttribute('src');
                preview.classList.add('u-hide');
                preview.setAttribute('hidden', 'hidden');
                return;
            }
            const objectUrl = URL.createObjectURL(file);
            img.onload = function() {
                URL.revokeObjectURL(objectUrl);
            };
            img.src = objectUrl;
            preview.classList.remove('u-hide');
            preview.removeAttribute('hidden');
        });
    }

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
