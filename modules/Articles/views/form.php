<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$mode = $mode ?? 'list';
$isList = $mode === 'list';
$isCreate = $mode === 'create';
$isEdit = $mode === 'edit';
$categories = $categories ?? [];
$users = $users ?? [];
$articleCatId = (int)($article['category_id'] ?? 0);
$imageUrl = $article['image_url'] ?? '';
$coverUrl = $article['cover_url'] ?? '';
$lm = $localeMode ?? 'multi';
$showEn = ($lm !== 'ru');
$showRu = ($lm !== 'en');
$returnUrl = $return ?? '';
ob_start();
?>

<?php if ($isList): ?>
<?php
$sort    = $sort ?? 'created_at';
$dir     = $dir  ?? 'desc';
$filters = $filters ?? [];
$categoryFilterId = (int)($filters['category_id'] ?? 0);
$authorFilterId = (int)($filters['author_id'] ?? 0);
$queryFilter = (string)($filters['q'] ?? '');
$titleCol   = ($showRu && !$showEn) ? 'title_ru' : 'title_en';
$previewCol = ($showRu && !$showEn) ? 'preview_ru' : 'preview_en';
$descCol    = ($showRu && !$showEn) ? 'description_ru' : 'description_en';

$buildUrl = static function(array $extra) use ($ap, $categoryFilterId, $authorFilterId, $queryFilter): string {
    $query = [];
    if ($categoryFilterId > 0) {
        $query['category_id'] = $categoryFilterId;
    }
    if ($authorFilterId > 0) {
        $query['author_id'] = $authorFilterId;
    }
    if ($queryFilter !== '') {
        $query['q'] = $queryFilter;
    }
    foreach ($extra as $k => $v) {
        $query[$k] = $v;
    }
    return htmlspecialchars($ap . '/articles' . (!empty($query) ? ('?' . http_build_query($query)) : ''));
};

$sortUrl = static function(string $col) use ($sort, $dir, $page, $buildUrl): string {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    return $buildUrl(['sort' => $col, 'dir' => $newDir, 'page' => $page]);
};
$sortIcon = static function(string $col) use ($sort, $dir): string {
    if ($sort !== $col) return '<span class="sort-icon muted">↕</span>';
    return '<span class="sort-icon">' . ($dir === 'asc' ? '↑' : '↓') . '</span>';
};
?>
    <div class="card">
        <?php $listReturn = htmlspecialchars_decode($buildUrl(['sort' => $sort, 'dir' => $dir, 'page' => $page]), ENT_QUOTES); ?>
        <div class="card-header">
            <div>
                <p class="eyebrow"><?= __('articles.title') ?></p>
                <h3><?= __('articles.subtitle') ?></h3>
            </div>
            <div class="u-flex u-gap-half">
                <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles/categories">Categories</a>
                <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/articles/create?return=<?= rawurlencode($listReturn) ?>"><?= __('articles.action.create') ?></a>
            </div>
        </div>
        <form method="get" action="<?= htmlspecialchars($ap) ?>/articles" class="stack">
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.filter.query') ?></span>
                    <input type="text" name="q" value="<?= htmlspecialchars($queryFilter) ?>" placeholder="<?= __('articles.filter.query_placeholder') ?>">
                </label>
                <label class="field">
                    <span><?= __('articles.filter.category') ?></span>
                    <select name="category_id">
                        <option value="0"><?= __('articles.filter.all_categories') ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilterId === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name_en'] ?? '') ?><?= !empty($cat['name_ru']) ? (' / ' . htmlspecialchars($cat['name_ru'])) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.filter.author') ?></span>
                    <select name="author_id">
                        <option value="0"><?= __('articles.filter.all_authors') ?></option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= $authorFilterId === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name'] ?? ('#' . (int)$u['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('articles.filter.sort') ?></span>
                        <select name="sort">
                            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>><?= __('articles.filter.sort_created') ?></option>
                            <option value="<?= htmlspecialchars($titleCol) ?>" <?= $sort === $titleCol ? 'selected' : '' ?>><?= __('articles.filter.sort_title') ?></option>
                            <option value="category" <?= $sort === 'category' ? 'selected' : '' ?>><?= __('articles.filter.sort_category') ?></option>
                            <option value="author" <?= $sort === 'author' ? 'selected' : '' ?>><?= __('articles.filter.sort_author') ?></option>
                            <option value="slug" <?= $sort === 'slug' ? 'selected' : '' ?>><?= __('articles.filter.sort_slug') ?></option>
                        </select>
                    </label>
                    <label class="field">
                        <span><?= __('articles.filter.direction') ?></span>
                        <select name="dir">
                            <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>><?= __('articles.filter.dir_desc') ?></option>
                            <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>><?= __('articles.filter.dir_asc') ?></option>
                        </select>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary"><?= __('articles.filter.apply') ?></button>
                <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles"><?= __('articles.filter.reset') ?></a>
            </div>
        </form>
        <div class="table-wrap">
            <table class="table data">
                <thead>
                    <tr>
                        <th><?= __('articles.table.preview_thumb') ?></th>
                        <th><a class="sort-link" href="<?= $sortUrl($titleCol) ?>"><?= $showRu && !$showEn ? __('articles.table.title_ru') : __('articles.table.title_en') ?> <?= $sortIcon($titleCol) ?></a></th>
                        <th><?= __('articles.table.meta_description') ?></th>
                        <th><a class="sort-link" href="<?= $sortUrl('category') ?>"><?= __('articles.table.category') ?> <?= $sortIcon('category') ?></a></th>
                        <th><a class="sort-link" href="<?= $sortUrl('author') ?>"><?= __('articles.table.author') ?> <?= $sortIcon('author') ?></a></th>
                        <th><a class="sort-link" href="<?= $sortUrl('slug') ?>"><?= __('articles.table.slug') ?> <?= $sortIcon('slug') ?></a></th>
                        <th><a class="sort-link" href="<?= $sortUrl('created_at') ?>"><?= __('articles.table.created') ?> <?= $sortIcon('created_at') ?></a></th>
                        <th><?= __('articles.table.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                        <?php
                        $categoryName = ($showRu && !$showEn)
                            ? ($a['category_name_ru'] ?? ($a['category_name_en'] ?? ($a['category'] ?? '')))
                            : ($a['category_name_en'] ?? ($a['category_name_ru'] ?? ($a['category'] ?? '')));
                        $authorName = $a['author_name'] ?? '';
                        $thumbUrl = (string)($a['image_url'] ?? '');
                        if ($thumbUrl === '' && !empty($a['cover_url'])) {
                            $thumbUrl = (string)$a['cover_url'];
                            if ($thumbUrl !== '' && $thumbUrl[0] === '/') {
                                $basePath = parse_url($thumbUrl, PHP_URL_PATH) ?: $thumbUrl;
                                $jpgCandidate = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $basePath);
                                if (is_string($jpgCandidate) && $jpgCandidate !== $basePath && is_file(APP_ROOT . $jpgCandidate)) {
                                    $thumbUrl = $jpgCandidate;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <?php if ($thumbUrl !== ''): ?>
                                    <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="" class="thumb">
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($a[$titleCol] ?? '') ?></td>
                            <td><?= htmlspecialchars(mb_strimwidth($a[$descCol] ?? '', 0, 60, '…', 'UTF-8')) ?></td>
                            <td><?= htmlspecialchars($categoryName) ?></td>
                            <td><?= htmlspecialchars($authorName) ?></td>
                            <td><?= htmlspecialchars($a['slug']) ?></td>
                            <td><?= htmlspecialchars($a['created_at']) ?></td>
                            <td class="actions">
                                <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/articles/edit/<?= urlencode($a['slug']) ?>?return=<?= rawurlencode($listReturn) ?>"><?= __('articles.action.edit') ?></a>
                                <form method="post" action="<?= htmlspecialchars($ap) ?>/articles/delete/<?= urlencode($a['slug']) ?>" onsubmit="return confirm('<?= __('articles.action.confirm_delete') ?>');">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                    <input type="hidden" name="return" value="<?= htmlspecialchars($listReturn) ?>">
                                    <button type="submit" class="btn danger small"><?= __('articles.action.delete') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($articles)): ?>
                        <tr><td colspan="8" class="muted"><?= __('articles.empty') ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $paginationPage    = $page ?? 1;
        $paginationTotal   = $total ?? 0;
        $paginationPerPage = $perPage ?? 20;
        $paginationBase    = $buildUrl(['sort' => $sort, 'dir' => $dir]);
        include APP_ROOT . '/app/views/partials/pagination.php';
        ?>
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
            <form method="post" action="<?= $isEdit ? $ap . '/articles/edit/' . urlencode($article['slug'] ?? '') : $ap . '/articles/create' ?>" class="stack article-editor-form" enctype="multipart/form-data" id="article-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="_upload_token" value="<?= htmlspecialchars($uploadCsrf ?? '') ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
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
                    <span class="muted u-font-085em">
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
            <div class="grid two">
                <label class="field">
                    <span><?= __('articles.form.image') ?> <span class="muted u-font-08em">(превью в списке)</span></span>
                    <input type="file" name="image" accept="image/*">
                    <input type="text" name="image_url" id="image_url" placeholder="<?= __('articles.placeholder.image_url') ?>" value="<?= htmlspecialchars($imageUrl) ?>">
                    <div class="thumb-preview <?= $imageUrl ? '' : 'u-hide' ?>" id="image_preview">
                        <?php if ($imageUrl): ?><img src="<?= htmlspecialchars($imageUrl) ?>" alt="preview"><?php endif; ?>
                    </div>
                    <button type="button" class="btn ghost small" onclick="openAttachmentPicker('image_url', 'image_preview')"><?= __('articles.action.pick_attachment') ?></button>
                </label>
                <label class="field">
                    <span>Обложка статьи <span class="muted u-font-08em">(большое фото в статье)</span></span>
                    <input type="file" name="cover" accept="image/*">
                    <input type="text" name="cover_url" id="cover_url" placeholder="URL обложки" value="<?= htmlspecialchars($coverUrl) ?>">
                    <div class="thumb-preview <?= $coverUrl ? '' : 'u-hide' ?>" id="cover_preview">
                        <?php if ($coverUrl): ?><img src="<?= htmlspecialchars($coverUrl) ?>" alt="cover preview"><?php endif; ?>
                    </div>
                    <button type="button" class="btn ghost small" onclick="openAttachmentPicker('cover_url', 'cover_preview')">Выбрать обложку</button>
                </label>
            </div>
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
            <div id="editor_mode_control" class="editor-mode-control" title="Вкл — EasyMDE, выкл — визуальный HTML">
                <input type="checkbox" id="toggle_editor_mode" checked>
                <span>EasyMDE</span>
            </div>
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
        const hasEasyMDE = typeof window.EasyMDE === 'function';
        const editorToggle = document.getElementById('toggle_editor_mode');
        const editorModeControl = document.getElementById('editor_mode_control');
        const editorPrefKey = 'articles_editor_enabled';
        let mdeEn = null;
        let mdeRu = null;

        function initMDE(id) {
            if (!hasEasyMDE) {
                return null;
            }
            return new EasyMDE({
                element: document.getElementById(id),
                spellChecker: false,
                autosave: { enabled: false },
                lineWrapping: true,
                theme: 'dark',
                uploadImage: true,
                imageUploadEndpoint: '/api/v1/attachments',
                imageUploadFunction: function(file, onSuccess, onError){
                    const formData = new FormData();
                    formData.append('file', file);
                    const uploadTokenInput = document.querySelector('input[name="_upload_token"]');
                    const fallbackTokenInput = document.querySelector('input[name="_token"]');
                    formData.append('_token', uploadTokenInput ? uploadTokenInput.value : (fallbackTokenInput ? fallbackTokenInput.value : ''));
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

        function getBodyTextarea(id) {
            return document.getElementById(id);
        }

        const liteEditors = {};
        let activeLiteEditorId = null;

        function createLiteEditor(textarea) {
            if (!textarea || !textarea.id || liteEditors[textarea.id]) {
                return;
            }

            const wrap = document.createElement('div');
            wrap.className = 'sr-lite-editor';

            const toolbar = document.createElement('div');
            toolbar.className = 'sr-lite-editor__toolbar';

            const area = document.createElement('div');
            area.className = 'sr-lite-editor__area';
            area.contentEditable = 'true';
            area.innerHTML = textarea.value || '';

            const tools = [
                {cmd: 'bold', label: 'B'},
                {cmd: 'italic', label: 'I'},
                {cmd: 'underline', label: 'U'},
                {cmd: 'insertUnorderedList', label: '•'},
                {cmd: 'insertOrderedList', label: '1.'},
                {cmd: 'formatBlock', value: 'H2', label: 'H2'},
                {cmd: 'formatBlock', value: 'H3', label: 'H3'},
                {cmd: 'formatBlock', value: 'H4', label: 'H4'},
                {cmd: 'formatBlock', value: 'BLOCKQUOTE', label: '❝'},
                {cmd: 'formatBlock', value: 'PRE', label: '</>'},
                {cmd: 'formatBlock', value: 'P', label: 'P'},
                {cmd: 'createLink', label: 'Link'},
                {cmd: 'insertImageByUrl', label: 'Img'},
                {cmd: 'insertHorizontalRule', label: 'HR'},
                {cmd: 'removeFormat', label: 'Clear'},
                {cmd: 'unlink', label: '×Link'}
            ];

            tools.forEach(function (tool) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn ghost small';
                btn.textContent = tool.label;
                btn.addEventListener('click', function () {
                    area.focus();
                    if (tool.cmd === 'createLink') {
                        const url = window.prompt('URL:', 'https://');
                        if (!url) return;
                        document.execCommand(tool.cmd, false, url);
                    } else if (tool.cmd === 'insertImageByUrl') {
                        const imgUrl = window.prompt('Image URL:', 'https://');
                        if (!imgUrl) return;
                        document.execCommand('insertImage', false, imgUrl);
                    } else if (tool.cmd === 'formatBlock') {
                        document.execCommand(tool.cmd, false, tool.value);
                    } else {
                        document.execCommand(tool.cmd, false, null);
                    }
                    sync();
                });
                toolbar.appendChild(btn);
            });

            function sync() {
                textarea.value = area.innerHTML;
            }

            area.addEventListener('input', sync);
            area.addEventListener('focus', function () {
                activeLiteEditorId = textarea.id;
            });

            textarea.classList.add('article-editor-fallback');
            textarea.hidden = true;

            textarea.parentNode.insertBefore(wrap, textarea.nextSibling);
            wrap.appendChild(toolbar);
            wrap.appendChild(area);

            if (textarea.form) {
                textarea.form.addEventListener('submit', sync, {capture: true});
            }

            liteEditors[textarea.id] = {
                sync: sync,
                focus: function () { area.focus(); },
                isFocused: function () { return document.activeElement === area; },
                insertImage: function (url) {
                    area.focus();
                    document.execCommand('insertImage', false, url);
                    sync();
                },
                destroy: function () {
                    sync();
                    wrap.remove();
                    textarea.hidden = false;
                }
            };
        }

        function destroyLiteEditor(id) {
            if (!liteEditors[id]) return;
            liteEditors[id].destroy();
            delete liteEditors[id];
            if (activeLiteEditorId === id) {
                activeLiteEditorId = null;
            }
        }

        function enableLiteEditors() {
            const bodyEn = getBodyTextarea('body_en');
            const bodyRu = getBodyTextarea('body_ru');
            createLiteEditor(bodyEn);
            createLiteEditor(bodyRu);
        }

        function disableLiteEditors() {
            destroyLiteEditor('body_en');
            destroyLiteEditor('body_ru');
        }

        function placeModeControl() {
            if (!editorModeControl) return;
            let target = null;
            if (mdeEn || mdeRu) {
                target = document.querySelector('.EasyMDEContainer .editor-toolbar');
            }
            if (!target) {
                target = document.querySelector('.sr-lite-editor__toolbar');
            }
            if (target && editorModeControl.parentNode !== target) {
                target.appendChild(editorModeControl);
            }
        }

        function enableEditors() {
            disableLiteEditors();
            if (!hasEasyMDE) {
                placeModeControl();
                return;
            }
            if (!mdeEn && getBodyTextarea('body_en')) {
                mdeEn = initMDE('body_en');
            }
            if (!mdeRu && getBodyTextarea('body_ru')) {
                mdeRu = initMDE('body_ru');
            }
            placeModeControl();
        }

        function disableEditors() {
            if (mdeEn && typeof mdeEn.toTextArea === 'function') {
                mdeEn.toTextArea();
            }
            if (mdeRu && typeof mdeRu.toTextArea === 'function') {
                mdeRu.toTextArea();
            }
            mdeEn = null;
            mdeRu = null;
            enableLiteEditors();
            placeModeControl();
        }

        function applyEditorMode(enabled) {
            if (!enabled) {
                disableEditors();
                return;
            }
            enableEditors();
        }

        const savedEditorEnabled = localStorage.getItem(editorPrefKey);
        const editorEnabledByDefault = savedEditorEnabled !== '0';
        if (editorToggle) {
            editorToggle.checked = editorEnabledByDefault && hasEasyMDE;
            editorToggle.disabled = !hasEasyMDE;
            editorToggle.addEventListener('change', function () {
                const enabled = !!editorToggle.checked;
                localStorage.setItem(editorPrefKey, enabled ? '1' : '0');
                applyEditorMode(enabled);
            });
        }

        applyEditorMode(editorEnabledByDefault && hasEasyMDE);

        var _attachTarget = 'image_url';
        var _attachPreview = 'image_preview';
        window.insertAttachmentUrl = function(url){
            var inp = document.getElementById(_attachTarget);
            var prev = document.getElementById(_attachPreview);
            if (inp) {
                inp.value = url;
                if (prev) {
                    prev.style.display = 'block';
                    prev.innerHTML = '<img src="'+url+'" alt="preview">';
                }
            }
            if (mdeEn && mdeEn.codemirror && mdeEn.codemirror.hasFocus()) {
                mdeEn.codemirror.replaceSelection('![]('+url+')');
            } else if (mdeRu && mdeRu.codemirror) {
                mdeRu.codemirror.replaceSelection('![]('+url+')');
            } else if (activeLiteEditorId && liteEditors[activeLiteEditorId]) {
                liteEditors[activeLiteEditorId].insertImage(url);
            } else {
                const bodyEnTextarea = getBodyTextarea('body_en');
                const bodyRuTextarea = getBodyTextarea('body_ru');
                if (bodyEnTextarea) {
                    bodyEnTextarea.value += (bodyEnTextarea.value ? '\n' : '') + '<img src="' + url + '" alt="">';
                } else if (bodyRuTextarea) {
                    bodyRuTextarea.value += (bodyRuTextarea.value ? '\n' : '') + '<img src="' + url + '" alt="">';
                }
            }
        };
        function openAttachmentPicker(targetId, previewId) {
            _attachTarget = targetId || 'image_url';
            _attachPreview = previewId || 'image_preview';
            window.open('<?= htmlspecialchars($ap) ?>/attachments', 'attachments', 'width=1000,height=700');
        }

        if (!hasEasyMDE) {
            console.warn('EasyMDE is not available. Fallback textarea mode is active.');
        }
    </script>
<?php endif; ?>

<?php
$pageTitle = $title ?? __('articles.page_title');
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
