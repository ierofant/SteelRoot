<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$mode = $mode ?? 'list';
$isList = $mode === 'list';
$isCreate = $mode === 'create';
$isEdit = $mode === 'edit';
$categories = $categories ?? [];
$articleCatId = (int)($article['category_id'] ?? 0);
$imageUrl = $article['image_url'] ?? '';
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
ob_start();
?>

<?php if ($isList): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow"><?= __('articles.title') ?></p>
                <h3><?= __('articles.subtitle') ?></h3>
            </div>
            <div style="display:flex;gap:.5rem">
                <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles/categories">Categories</a>
                <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/articles/create"><?= __('articles.action.create') ?></a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table data">
                <thead>
                    <tr>
                        <th><?= $showRu && !$showEn ? __('articles.table.title_ru') : __('articles.table.title_en') ?></th>
                        <th>Preview</th>
                        <th><?= __('articles.table.meta_description') ?></th>
                        <th><?= __('articles.table.slug') ?></th>
                        <th><?= __('articles.table.created') ?></th>
                        <th><?= __('articles.table.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                        <?php $titleCol = ($showRu && !$showEn) ? 'title_ru' : 'title_en'; ?>
                        <?php $previewCol = ($showRu && !$showEn) ? 'preview_ru' : 'preview_en'; ?>
                        <?php $descCol = ($showRu && !$showEn) ? 'description_ru' : 'description_en'; ?>
                        <tr>
                            <td><?= htmlspecialchars($a[$titleCol]) ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($a[$previewCol] ?? '', 0, 60, '…', 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($a[$descCol] ?? '', 0, 60, '…', 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars($a['slug']) ?></td>
                            <td><?= htmlspecialchars($a['created_at']) ?></td>
                            <td class="actions">
                                <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/articles/edit/<?= urlencode($a['slug']) ?>"><?= __('articles.action.edit') ?></a>
                                <form method="post" action="<?= htmlspecialchars($ap) ?>/articles/delete/<?= urlencode($a['slug']) ?>" onsubmit="return confirm('<?= __('articles.action.confirm_delete') ?>');">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                    <button type="submit" class="btn danger small"><?= __('articles.action.delete') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($articles)): ?>
                        <tr><td colspan="6" class="muted"><?= __('articles.empty') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <div class="card stack">
            <div class="card-header">
                <div>
            <p class="eyebrow"><?= $isEdit ? __('articles.form.editing') : __('articles.form.new') ?></p>
            <h3><?= $isEdit ? __('articles.form.edit_title') : __('articles.form.create_title') ?></h3>
                </div>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles"><?= __('articles.action.back') ?></a>
        </div>
            <form method="post" action="<?= $isEdit ? $ap . '/articles/edit/' . urlencode($article['slug'] ?? '') : $ap . '/articles/create' ?>" class="stack" enctype="multipart/form-data" id="article-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <?php if ($showEn && $showRu): ?>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('articles.form.title_en') ?></span>
                        <input type="text" name="title_en" value="<?= htmlspecialchars($article['title_en'] ?? '') ?>" required>
                    </label>
                    <label class="field">
                        <span><?= __('articles.form.title_ru') ?></span>
                        <input type="text" name="title_ru" value="<?= htmlspecialchars($article['title_ru'] ?? '') ?>">
                    </label>
                </div>
                <?php elseif ($showEn): ?>
                <label class="field">
                    <span><?= __('articles.form.title_en') ?></span>
                    <input type="text" name="title_en" value="<?= htmlspecialchars($article['title_en'] ?? '') ?>" required>
                </label>
                <?php else: ?>
                <label class="field">
                    <span><?= __('articles.form.title_ru') ?></span>
                    <input type="text" name="title_ru" value="<?= htmlspecialchars($article['title_ru'] ?? '') ?>" required>
                </label>
                <?php endif; ?>
            <label class="field">
                <span><?= __('articles.form.slug') ?></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($article['slug'] ?? '') ?>" placeholder="<?= __('articles.placeholder.slug') ?>">
            </label>
            <?php if ($showEn && $showRu): ?>
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.form.preview_en') ?></span>
                    <textarea name="preview_en" rows="3"><?= htmlspecialchars($article['preview_en'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span><?= __('articles.form.preview_ru') ?></span>
                    <textarea name="preview_ru" rows="3"><?= htmlspecialchars($article['preview_ru'] ?? '') ?></textarea>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.form.description_en') ?></span>
                    <textarea name="description_en" rows="2"><?= htmlspecialchars($article['description_en'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span><?= __('articles.form.description_ru') ?></span>
                    <textarea name="description_ru" rows="2"><?= htmlspecialchars($article['description_ru'] ?? '') ?></textarea>
                </label>
            </div>
            <?php elseif ($showEn): ?>
            <label class="field">
                <span><?= __('articles.form.preview_en') ?></span>
                <textarea name="preview_en" rows="3"><?= htmlspecialchars($article['preview_en'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('articles.form.description_en') ?></span>
                <textarea name="description_en" rows="2"><?= htmlspecialchars($article['description_en'] ?? '') ?></textarea>
            </label>
            <?php else: ?>
            <label class="field">
                <span><?= __('articles.form.preview_ru') ?></span>
                <textarea name="preview_ru" rows="3"><?= htmlspecialchars($article['preview_ru'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('articles.form.description_ru') ?></span>
                <textarea name="description_ru" rows="2"><?= htmlspecialchars($article['description_ru'] ?? '') ?></textarea>
            </label>
            <?php endif; ?>
            <label class="field">
                <span><?= __('articles.form.category') ?></span>
                <select name="category_id">
                    <option value="">— No category —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $articleCatId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name_en']) ?><?= $cat['name_ru'] ? ' / ' . htmlspecialchars($cat['name_ru']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($categories)): ?>
                    <span class="muted" style="font-size:.85em">
                        No categories yet. <a href="<?= htmlspecialchars($ap) ?>/articles/categories">Manage categories</a>
                    </span>
                <?php endif; ?>
            </label>
            <?php if (!empty($users)): ?>
            <label class="field">
                <span>Author</span>
                <select name="author_id">
                    <option value="">— No author —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)($article['author_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <label class="field">
                <span><?= __('articles.form.image') ?></span>
                <input type="file" name="image" accept="image/*">
                <input type="text" name="image_url" id="image_url" placeholder="<?= __('articles.placeholder.image_url') ?>" value="<?= htmlspecialchars($imageUrl) ?>">
                <div class="muted"><?= __('articles.help.image') ?></div>
                <div class="thumb-preview" id="image_preview" <?= $imageUrl ? '' : 'style="display:none;"' ?>>
                    <?php if ($imageUrl): ?><img src="<?= htmlspecialchars($imageUrl) ?>" alt="preview"><?php endif; ?>
                </div>
                <button type="button" class="btn ghost small" onclick="openAttachmentPicker()"><?= __('articles.action.pick_attachment') ?></button>
            </label>
            <?php if ($showEn && $showRu): ?>
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.form.body_en') ?></span>
                    <textarea id="body_en" name="body_en" rows="8"><?= htmlspecialchars($article['body_en'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span><?= __('articles.form.body_ru') ?></span>
                    <textarea id="body_ru" name="body_ru" rows="8"><?= htmlspecialchars($article['body_ru'] ?? '') ?></textarea>
                </label>
            </div>
            <?php elseif ($showEn): ?>
            <label class="field">
                <span><?= __('articles.form.body_en') ?></span>
                <textarea id="body_en" name="body_en" rows="12"><?= htmlspecialchars($article['body_en'] ?? '') ?></textarea>
            </label>
            <?php else: ?>
            <label class="field">
                <span><?= __('articles.form.body_ru') ?></span>
                <textarea id="body_ru" name="body_ru" rows="12"><?= htmlspecialchars($article['body_ru'] ?? '') ?></textarea>
            </label>
            <?php endif; ?>
            <?php
                $tagNames = [];
                if (!empty($tags)) {
                    foreach ($tags as $t) { $tagNames[] = $t['name']; }
                } elseif (!empty($article['tags'])) {
                    $tagNames = $article['tags'];
                }
            ?>
            <label class="field">
                <span><?= __('articles.form.tags') ?></span>
                <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', $tagNames)) ?>">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('articles.action.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles"><?= __('articles.action.cancel') ?></a>
        </div>
        </form>
    </div>
    <script>
        function initMDE(id) {
            return new EasyMDE({
                element: document.getElementById(id),
                spellChecker: false,
                autosave: { enabled: false },
                theme: 'dark',
                uploadImage: true,
                imageUploadEndpoint: '/api/v1/attachments',
                imageUploadFunction: function(file, onSuccess, onError){
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('_token', document.querySelector('input[name=\"_token\"]').value);
                    fetch('/api/v1/attachments', {method:'POST', body: formData})
                        .then(r => r.json())
                        .then(data => {
                            if (data && data.url) onSuccess(data.url);
                            else onError(data.error || <?= json_encode(__('articles.error.upload_failed')) ?>);
                        })
                        .catch(() => onError(<?= json_encode(__('articles.error.upload_error')) ?>));
                }
            });
        }
        const mdeEn = document.getElementById('body_en') ? initMDE('body_en') : null;
        const mdeRu = document.getElementById('body_ru') ? initMDE('body_ru') : null;
        const imageInput = document.getElementById('image_url');
        const imagePreview = document.getElementById('image_preview');

        window.insertAttachmentUrl = function(url){
            if (imageInput) {
                imageInput.value = url;
                if (imagePreview) {
                    imagePreview.style.display = 'block';
                    imagePreview.innerHTML = '<img src=\"'+url+'\" alt=\"preview\">';
                }
            }
            if (mdeEn && mdeEn.codemirror && mdeEn.codemirror.hasFocus()) {
                mdeEn.codemirror.replaceSelection('![]('+url+')');
            } else if (mdeRu && mdeRu.codemirror) {
                mdeRu.codemirror.replaceSelection('![]('+url+')');
            }
        };
        function openAttachmentPicker() {
            window.open('<?= htmlspecialchars($ap) ?>/attachments', 'attachments', 'width=1000,height=700');
        }
    </script>
<?php endif; ?>

<?php
$pageTitle = $title ?? __('articles.page_title');
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
