<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$adminBase = $adminBase ?? ($ap . '/articles');
$categoriesBase = $categoriesBase ?? ($adminBase . '/categories');
$settingsBase = $settingsBase ?? null;
$uploadRoot = $uploadRoot ?? '/storage/uploads/articles';
$editorStoragePrefix = preg_replace('/[^a-z0-9_-]+/i', '', (string)($contentType ?? 'articles')) ?: 'articles';
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
$commentPolicy = is_array($commentPolicy ?? null) ? $commentPolicy : [];
$commentGroups = $commentGroups ?? [];
$commentsMode = (string)($commentPolicy['mode'] ?? 'default');
$commentsGroupIds = array_map('intval', (array)($commentPolicy['group_ids'] ?? []));
$commentsUi = is_array($commentsUi ?? null) ? $commentsUi : ['available' => true, 'enabled' => true, 'entity_enabled' => true, 'settings_url' => $ap . '/comments/settings'];
$returnUrl = $return ?? '';
$uploadFolder = $uploadFolder ?? '';
$ui = array_merge([
    'eyebrow' => __('articles.title'),
    'list_title' => __('articles.subtitle'),
    'settings_label' => 'Settings',
    'categories_label' => 'Categories',
    'create_label' => __('articles.action.create'),
    'edit_eyebrow' => __('articles.form.editing'),
    'new_eyebrow' => __('articles.form.new'),
    'edit_title' => __('articles.form.edit_title'),
    'create_title' => __('articles.form.create_title'),
    'comments_eyebrow' => 'Комментарии',
    'comments_title' => 'Доступ к комментированию',
    'comments_help' => 'Отдельные правила для этой статьи.',
    'upload_folder_label' => 'Папка загрузки',
    'upload_folder_help' => 'Подпапка внутри',
    'upload_folder_help_suffix' => 'Оставьте пустым для корня.',
    'image_hint' => 'превью в списке',
    'cover_label' => 'Обложка статьи',
    'cover_hint' => 'большое фото в статье',
    'cover_placeholder' => 'URL обложки',
    'cover_pick' => 'Выбрать обложку',
    'identity_eyebrow' => 'Identity',
    'identity_title' => 'Title and routing',
    'seo_eyebrow' => 'SEO',
    'seo_title' => 'Preview and descriptions',
    'taxonomy_eyebrow' => 'Taxonomy',
    'taxonomy_title' => 'Category, author and tags',
    'empty_categories' => 'No categories yet.',
    'manage_categories' => 'Manage categories',
], $ui ?? []);
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

$buildUrl = static function(array $extra) use ($adminBase, $categoryFilterId, $authorFilterId, $queryFilter): string {
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
    return htmlspecialchars($adminBase . (!empty($query) ? ('?' . http_build_query($query)) : ''));
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
                <p class="eyebrow"><?= htmlspecialchars((string)$ui['eyebrow']) ?></p>
                <h3><?= htmlspecialchars((string)$ui['list_title']) ?></h3>
            </div>
            <div class="u-flex u-gap-half">
                <?php if (is_string($settingsBase) && $settingsBase !== ''): ?>
                    <a class="btn ghost" href="<?= htmlspecialchars($settingsBase) ?>"><?= htmlspecialchars((string)$ui['settings_label']) ?></a>
                <?php endif; ?>
                <a class="btn ghost" href="<?= htmlspecialchars($categoriesBase) ?>"><?= htmlspecialchars((string)$ui['categories_label']) ?></a>
                <a class="btn primary" href="<?= htmlspecialchars($adminBase) ?>/create?return=<?= rawurlencode($listReturn) ?>"><?= htmlspecialchars((string)$ui['create_label']) ?></a>
            </div>
        </div>
        <form method="get" action="<?= htmlspecialchars($adminBase) ?>" class="stack">
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
                <a class="btn ghost" href="<?= htmlspecialchars($adminBase) ?>"><?= __('articles.filter.reset') ?></a>
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
                                <a class="btn ghost small" href="<?= htmlspecialchars($adminBase) ?>/edit/<?= urlencode($a['slug']) ?>?return=<?= rawurlencode($listReturn) ?>"><?= __('articles.action.edit') ?></a>
                                <form method="post" action="<?= htmlspecialchars($adminBase) ?>/delete/<?= urlencode($a['slug']) ?>" onsubmit="return confirm('<?= __('articles.action.confirm_delete') ?>');">
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
            <p class="eyebrow"><?= htmlspecialchars($isEdit ? (string)$ui['edit_eyebrow'] : (string)$ui['new_eyebrow']) ?></p>
            <h3><?= htmlspecialchars($isEdit ? (string)$ui['edit_title'] : (string)$ui['create_title']) ?></h3>
                </div>
            <a class="btn ghost" href="<?= htmlspecialchars($adminBase) ?>"><?= __('articles.action.back') ?></a>
        </div>
            <form method="post" action="<?= $isEdit ? ($adminBase . '/edit/' . urlencode($article['slug'] ?? '')) : ($adminBase . '/create') ?>" class="stack article-editor-form" enctype="multipart/form-data" id="article-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="_upload_token" value="<?= htmlspecialchars($uploadCsrf ?? '') ?>">
                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                <div class="tabs article-editor-tabs">
                    <button type="button" class="btn ghost small tab-btn active" data-tab="content"><?= __('articles.tab.content') ?></button>
                    <button type="button" class="btn ghost small tab-btn" data-tab="photos"><?= __('articles.tab.photos') ?></button>
                    <button type="button" class="btn ghost small tab-btn" data-tab="meta"><?= __('articles.tab.meta') ?></button>
                </div>
                <div class="form-actions article-edit-actions">
                    <button type="submit" class="btn ghost" name="save_mode" value="stay"><?= __('articles.action.save_stay') ?></button>
                    <button type="submit" class="btn primary" name="save_mode" value="close"><?= __('articles.action.save_and_close') ?></button>
                    <a class="btn ghost" href="<?= htmlspecialchars($adminBase) ?>"><?= __('articles.action.cancel') ?></a>
                </div>
                <div class="tab-pane article-editor-pane is-active" data-pane="content">
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
                    $replaceScopeOptions = [];
                    if ($showEn && $showRu) {
                        $replaceScopeOptions[] = ['value' => 'current', 'label' => __('articles.editor.scope_current')];
                        $replaceScopeOptions[] = ['value' => 'both', 'label' => __('articles.editor.scope_both')];
                        $replaceScopeOptions[] = ['value' => 'body_en', 'label' => __('articles.editor.field_en')];
                        $replaceScopeOptions[] = ['value' => 'body_ru', 'label' => __('articles.editor.field_ru')];
                    } elseif ($showEn) {
                        $replaceScopeOptions[] = ['value' => 'body_en', 'label' => __('articles.editor.field_en')];
                    } else {
                        $replaceScopeOptions[] = ['value' => 'body_ru', 'label' => __('articles.editor.field_ru')];
                    }
                ?>
                <div class="card stack article-find-replace">
                    <div class="card-header">
                        <div>
                            <p class="eyebrow"><?= __('articles.editor.tools') ?></p>
                            <h4><?= __('articles.editor.find_replace_title') ?></h4>
                        </div>
                    </div>
                    <p class="muted"><?= __('articles.editor.find_replace_description') ?></p>
                    <div class="grid two">
                        <label class="field">
                            <span><?= __('articles.editor.search') ?></span>
                            <input type="text" id="article_find_query" autocomplete="off">
                        </label>
                        <label class="field">
                            <span><?= __('articles.editor.replace') ?></span>
                            <input type="text" id="article_replace_value" autocomplete="off">
                        </label>
                    </div>
                    <div class="grid two article-find-replace__controls">
                        <label class="field">
                            <span><?= __('articles.editor.scope') ?></span>
                            <select id="article_replace_scope">
                                <?php foreach ($replaceScopeOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option['value']) ?>"><?= htmlspecialchars($option['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="article-find-replace__toggles">
                            <label class="field checkbox">
                                <input type="checkbox" id="article_replace_regex" value="1">
                                <span><?= __('articles.editor.regex') ?></span>
                            </label>
                            <label class="field checkbox">
                                <input type="checkbox" id="article_replace_case" value="1">
                                <span><?= __('articles.editor.case_sensitive') ?></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-actions article-find-replace__actions">
                        <button type="button" class="btn ghost" id="article_find_next"><?= __('articles.editor.find_next') ?></button>
                        <button type="button" class="btn ghost" id="article_replace_next"><?= __('articles.editor.replace_next') ?></button>
                        <button type="button" class="btn primary" id="article_replace_all"><?= __('articles.editor.replace_all') ?></button>
                    </div>
                    <p class="muted article-find-replace__status" id="article_find_replace_status"></p>
                </div>
                <div id="editor_mode_control" class="editor-mode-control" title="Вкл — EasyMDE, выкл — визуальный HTML">
                    <input type="checkbox" id="toggle_editor_mode" checked>
                    <span>EasyMDE</span>
                </div>
                <?php if (!empty($commentsUi['available'])): ?>
                    <div class="card stack article-comments-card">
                        <div class="article-comments-card__header">
                            <p class="eyebrow"><?= htmlspecialchars((string)$ui['comments_eyebrow']) ?></p>
                            <h4><?= htmlspecialchars((string)$ui['comments_title']) ?></h4>
                            <p class="muted"><?= htmlspecialchars((string)$ui['comments_help']) ?></p>
                        </div>
                        <label class="field">
                            <span>Режим комментариев</span>
                            <select name="comments_mode">
                                <option value="default" <?= $commentsMode === 'default' ? 'selected' : '' ?>>По умолчанию модуля</option>
                                <option value="enabled" <?= $commentsMode === 'enabled' ? 'selected' : '' ?>>Явно включены</option>
                                <option value="disabled" <?= $commentsMode === 'disabled' ? 'selected' : '' ?>>Отключены</option>
                            </select>
                        </label>
                        <?php if (!empty($commentGroups)): ?>
                            <div class="field article-comment-groups-field">
                                <span>Разрешённые группы</span>
                                <div class="article-comment-groups-grid">
                                    <?php foreach ($commentGroups as $group): ?>
                                        <?php $groupId = (int)($group['id'] ?? 0); ?>
                                        <label class="article-comment-group-chip">
                                            <input type="checkbox" name="comments_group_ids[]" value="<?= $groupId ?>" <?= in_array($groupId, $commentsGroupIds, true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars((string)($group['name'] ?? ('#' . $groupId))) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="muted">Если список пустой, действуют обычные правила комментариев.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card stack article-comments-card">
                        <div class="article-comments-card__header">
                            <p class="eyebrow"><?= htmlspecialchars((string)$ui['comments_eyebrow']) ?></p>
                            <h4><?= htmlspecialchars((string)$ui['comments_title']) ?></h4>
                            <p class="muted">
                                <?php if (empty($commentsUi['enabled'])): ?>
                                    Комментарии глобально выключены.
                                <?php else: ?>
                                    Для этого типа контента комментарии выключены в глобальных настройках.
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars((string)($commentsUi['settings_url'] ?? ($ap . '/comments/settings'))) ?>">/admin/comments/settings</a>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
                <div class="tab-pane article-editor-pane" data-pane="photos">
                <label class="field">
                    <span><?= htmlspecialchars((string)$ui['upload_folder_label']) ?></span>
                    <input type="text" name="upload_folder" value="<?= htmlspecialchars((string)$uploadFolder) ?>" placeholder="например: masters/moscow">
                    <small class="muted"><?= htmlspecialchars((string)$ui['upload_folder_help']) ?> `<?= htmlspecialchars($uploadRoot) ?>`. <?= htmlspecialchars((string)$ui['upload_folder_help_suffix']) ?></small>
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('articles.form.image') ?> <span class="muted u-font-08em">(<?= htmlspecialchars((string)$ui['image_hint']) ?>)</span></span>
                        <input type="file" name="image" accept="image/*">
                        <input type="text" name="image_url" id="image_url" placeholder="<?= __('articles.placeholder.image_url') ?>" value="<?= htmlspecialchars($imageUrl) ?>">
                        <div class="thumb-preview <?= $imageUrl ? '' : 'u-hide' ?>" id="image_preview">
                            <?php if ($imageUrl): ?><img src="<?= htmlspecialchars($imageUrl) ?>" alt="preview"><?php endif; ?>
                        </div>
                        <button type="button" class="btn ghost small" onclick="openAttachmentPicker('image_url', 'image_preview')"><?= __('articles.action.pick_attachment') ?></button>
                    </label>
                    <label class="field">
                        <span><?= htmlspecialchars((string)$ui['cover_label']) ?> <span class="muted u-font-08em">(<?= htmlspecialchars((string)$ui['cover_hint']) ?>)</span></span>
                        <input type="file" name="cover" accept="image/*">
                        <input type="text" name="cover_url" id="cover_url" placeholder="<?= htmlspecialchars((string)$ui['cover_placeholder']) ?>" value="<?= htmlspecialchars($coverUrl) ?>">
                        <div class="thumb-preview <?= $coverUrl ? '' : 'u-hide' ?>" id="cover_preview">
                            <?php if ($coverUrl): ?><img src="<?= htmlspecialchars($coverUrl) ?>" alt="cover preview"><?php endif; ?>
                        </div>
                        <button type="button" class="btn ghost small" onclick="openAttachmentPicker('cover_url', 'cover_preview')"><?= htmlspecialchars((string)$ui['cover_pick']) ?></button>
                    </label>
                </div>
                </div>
                <div class="tab-pane article-editor-pane" data-pane="meta">
                <div class="article-meta-sections">
                <div class="card stack article-meta-card">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars((string)$ui['identity_eyebrow']) ?></p>
                        <h4><?= htmlspecialchars((string)$ui['identity_title']) ?></h4>
                    </div>
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
                </div>
                <div class="card stack article-meta-card">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars((string)$ui['seo_eyebrow']) ?></p>
                        <h4><?= htmlspecialchars((string)$ui['seo_title']) ?></h4>
                    </div>
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
                </div>
                <div class="card stack article-meta-card">
                    <div>
                        <p class="eyebrow"><?= htmlspecialchars((string)$ui['taxonomy_eyebrow']) ?></p>
                        <h4><?= htmlspecialchars((string)$ui['taxonomy_title']) ?></h4>
                    </div>
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
                        <?= htmlspecialchars((string)$ui['empty_categories']) ?> <a href="<?= htmlspecialchars($categoriesBase) ?>"><?= htmlspecialchars((string)$ui['manage_categories']) ?></a>
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
            <?php
                $tagsInput = $tagsInput ?? '';
                $tagNames = [];
                if (!empty($tags)) {
                    foreach ($tags as $t) { $tagNames[] = $t['name']; }
                } elseif (!empty($article['tags'])) {
                    $tagNames = $article['tags'];
                }
                if ($tagsInput === '' && $tagNames !== []) {
                    $tagsInput = implode(' ', array_map(static fn ($name) => '#' . ltrim((string)$name, '#'), $tagNames));
                }
            ?>
            <div class="article-edit-meta-grid">
                <div class="card stack article-tags-card">
                    <label class="field article-tags-field">
                        <span><?= __('articles.form.tags') ?> (#tag #tag-two)</span>
                        <input
                            type="text"
                            name="tags"
                            value="<?= htmlspecialchars($tagsInput) ?>"
                            placeholder="#tattoo #blackwork #tiger"
                        >
                    </label>
                </div>
            </div>
            </div>
            </div>
        </form>
    </div>
    <script>
        const hasEasyMDE = typeof window.EasyMDE === 'function';
        const editorToggle = document.getElementById('toggle_editor_mode');
        const editorModeControl = document.getElementById('editor_mode_control');
        const editorPrefKey = <?= json_encode($editorStoragePrefix . '_editor_enabled') ?>;
        const articleTabPrefKey = <?= json_encode($editorStoragePrefix . '_editor_active_tab') ?>;
        const findInput = document.getElementById('article_find_query');
        const replaceInput = document.getElementById('article_replace_value');
        const scopeInput = document.getElementById('article_replace_scope');
        const regexInput = document.getElementById('article_replace_regex');
        const caseInput = document.getElementById('article_replace_case');
        const statusNode = document.getElementById('article_find_replace_status');
        const activeEditorDefault = <?= json_encode($showEn ? 'body_en' : 'body_ru') ?>;
        const replaceI18n = {
            searchEmpty: <?= json_encode(__('articles.editor.search_empty')) ?>,
            regexInvalid: <?= json_encode(__('articles.editor.regex_invalid')) ?>,
            matchNotFound: <?= json_encode(__('articles.editor.match_not_found')) ?>,
            matchFound: <?= json_encode(__('articles.editor.match_found')) ?>,
            replacedOne: <?= json_encode(__('articles.editor.replaced_one')) ?>,
            replacedMany: <?= json_encode(__('articles.editor.replaced_many')) ?>,
            fieldEn: <?= json_encode(__('articles.editor.field_en')) ?>,
            fieldRu: <?= json_encode(__('articles.editor.field_ru')) ?>
        };
        let mdeEn = null;
        let mdeRu = null;
        let activeBodyEditorId = activeEditorDefault;
        const replaceCursorState = {};

        function initMDE(id) {
            if (!hasEasyMDE) {
                return null;
            }
            const instance = new EasyMDE({
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
            if (instance && instance.codemirror) {
                instance.codemirror.on('focus', function () {
                    activeBodyEditorId = id;
                });
                addPostheaderBtns(instance);
            }
            return instance;
        }

        function addPostheaderBtns(instance) {
            const toolbarEl = instance.element.closest('.EasyMDEContainer')
                ? instance.element.closest('.EasyMDEContainer').querySelector('.editor-toolbar')
                : null;
            if (!toolbarEl) return;
            const sep = document.createElement('i');
            sep.className = 'separator';
            toolbarEl.appendChild(sep);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mde-btn-ph3';
            btn.title = 'bd-postheader-3';
            btn.textContent = '§';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const tag = (window.prompt('Tag (h1 / h2 / h3 / h4 / p):', 'h4') || '').trim().toLowerCase();
                if (!tag || !/^(h[1-6]|p)$/.test(tag)) return;
                const cm = instance.codemirror;
                const sel = cm.getSelection();
                cm.replaceSelection('<' + tag + ' class="bd-postheader-3">' + sel + '</' + tag + '>');
                cm.focus();
            });
            toolbarEl.appendChild(btn);
        }

        function getBodyTextarea(id) {
            return document.getElementById(id);
        }

        function getEditorApi(id) {
            const textarea = getBodyTextarea(id);
            if (!textarea) {
                return null;
            }

            const mde = id === 'body_en' ? mdeEn : mdeRu;
            if (mde && mde.codemirror) {
                return {
                    getText: function () { return mde.value(); },
                    setText: function (value) { mde.value(value); },
                    focus: function () { mde.codemirror.focus(); },
                    getSelectionEnd: function () {
                        const doc = mde.codemirror.getDoc();
                        return mde.codemirror.indexFromPos(doc.getCursor('to'));
                    },
                    selectRange: function (start, end) {
                        const doc = mde.codemirror.getDoc();
                        doc.setSelection(mde.codemirror.posFromIndex(start), mde.codemirror.posFromIndex(end));
                        mde.codemirror.focus();
                    }
                };
            }

            if (liteEditors[id]) {
                return liteEditors[id];
            }

            return {
                getText: function () { return textarea.value; },
                setText: function (value) { textarea.value = value; },
                focus: function () { textarea.focus(); },
                getSelectionEnd: function () { return textarea.selectionEnd || 0; },
                selectRange: function (start, end) {
                    textarea.focus();
                    if (typeof textarea.setSelectionRange === 'function') {
                        textarea.setSelectionRange(start, end);
                    }
                }
            };
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
                {cmd: 'unlink', label: '×Link'},
                {cmd: 'postheader3', label: '§', title: 'bd-postheader-3'}
            ];

            tools.forEach(function (tool) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn ghost small';
                btn.textContent = tool.label;
                btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
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
                    } else if (tool.cmd === 'postheader3') {
                        const sel = window.getSelection();
                        if (sel && sel.rangeCount) {
                            let node = sel.getRangeAt(0).commonAncestorContainer;
                            if (node.nodeType === 3) node = node.parentNode;
                            const blocks = new Set(['H1','H2','H3','H4','H5','H6','P','DIV','BLOCKQUOTE','LI','PRE']);
                            while (node && node !== area && !blocks.has(node.nodeName)) {
                                node = node.parentNode;
                            }
                            if (node && node !== area) {
                                node.classList.toggle('bd-postheader-3');
                                sync();
                            }
                        }
                    } else {
                        document.execCommand(tool.cmd, false, null);
                    }
                    sync();
                });
                if (tool.title) btn.title = tool.title;
                toolbar.appendChild(btn);
            });

            function sync() {
                textarea.value = area.innerHTML;
            }

            area.addEventListener('input', sync);
            area.addEventListener('focus', function () {
                activeLiteEditorId = textarea.id;
                activeBodyEditorId = textarea.id;
            });

            textarea.addEventListener('focus', function () {
                activeBodyEditorId = textarea.id;
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
                getText: function () { return area.innerHTML; },
                setText: function (value) {
                    area.innerHTML = value;
                    sync();
                },
                getSelectionEnd: function () {
                    return replaceCursorState[textarea.id] || 0;
                },
                selectRange: function (start, end) {
                    replaceCursorState[textarea.id] = end;
                    area.focus();
                },
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

        function replaceStatus(message, isError) {
            if (!statusNode) {
                return;
            }
            statusNode.textContent = message || '';
            statusNode.classList.toggle('is-error', !!isError);
        }

        function replaceFieldLabel(id) {
            return id === 'body_ru' ? replaceI18n.fieldRu : replaceI18n.fieldEn;
        }

        function replaceScopeIds() {
            if (!scopeInput) {
                return [activeEditorDefault];
            }
            if (scopeInput.value === 'both') {
                return ['body_en', 'body_ru'].filter(function (id) { return !!getBodyTextarea(id); });
            }
            if (scopeInput.value === 'current') {
                return [activeBodyEditorId || activeEditorDefault].filter(function (id) { return !!getBodyTextarea(id); });
            }
            return [scopeInput.value].filter(function (id) { return !!getBodyTextarea(id); });
        }

        function replacePatternConfig() {
            const query = findInput ? findInput.value : '';
            if (!query) {
                replaceStatus(replaceI18n.searchEmpty, true);
                return null;
            }

            const caseSensitive = !!(caseInput && caseInput.checked);
            return {
                query: query,
                useRegex: !!(regexInput && regexInput.checked),
                flags: caseSensitive ? 'g' : 'gi'
            };
        }

        function findInText(text, config, startIndex) {
            if (config.useRegex) {
                let regex;
                try {
                    regex = new RegExp(config.query, config.flags);
                } catch (error) {
                    replaceStatus(replaceI18n.regexInvalid, true);
                    return null;
                }
                regex.lastIndex = startIndex;
                let match = regex.exec(text);
                if (!match && startIndex > 0) {
                    regex.lastIndex = 0;
                    match = regex.exec(text);
                }
                if (!match || match[0] === '') {
                    return null;
                }
                return {
                    start: match.index,
                    end: match.index + match[0].length,
                    match: match[0]
                };
            }

            const haystack = config.flags === 'g' ? text : text.toLowerCase();
            const needle = config.flags === 'g' ? config.query : config.query.toLowerCase();
            let index = haystack.indexOf(needle, startIndex);
            if (index === -1 && startIndex > 0) {
                index = haystack.indexOf(needle, 0);
            }
            if (index === -1) {
                return null;
            }
            return {
                start: index,
                end: index + config.query.length,
                match: text.slice(index, index + config.query.length)
            };
        }

        function replaceOneInText(text, config, replaceValue, startIndex) {
            const found = findInText(text, config, startIndex);
            if (!found) {
                return null;
            }

            let replacement = replaceValue;
            if (config.useRegex) {
                try {
                    replacement = found.match.replace(new RegExp(config.query, config.flags.replace('g', '')), replaceValue);
                } catch (error) {
                    replaceStatus(replaceI18n.regexInvalid, true);
                    return null;
                }
            }

            return {
                start: found.start,
                end: found.start + replacement.length,
                text: text.slice(0, found.start) + replacement + text.slice(found.end)
            };
        }

        function replaceAllInText(text, config, replaceValue) {
            let count = 0;
            if (config.useRegex) {
                try {
                    const regex = new RegExp(config.query, config.flags);
                    return {
                        text: text.replace(regex, function () {
                            count += 1;
                            return replaceValue;
                        }),
                        count: count
                    };
                } catch (error) {
                    replaceStatus(replaceI18n.regexInvalid, true);
                    return null;
                }
            }

            const escaped = config.query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(escaped, config.flags);
            return {
                text: text.replace(regex, function () {
                    count += 1;
                    return replaceValue;
                }),
                count: count
            };
        }

        function runFindNext() {
            const config = replacePatternConfig();
            if (!config) {
                return;
            }

            const ids = replaceScopeIds();
            for (let i = 0; i < ids.length; i += 1) {
                const id = ids[i];
                const editor = getEditorApi(id);
                if (!editor) {
                    continue;
                }
                const found = findInText(editor.getText(), config, editor.getSelectionEnd());
                if (!found) {
                    continue;
                }
                activeBodyEditorId = id;
                replaceCursorState[id] = found.end;
                editor.selectRange(found.start, found.end);
                replaceStatus(replaceI18n.matchFound.replace('{field}', replaceFieldLabel(id)), false);
                return;
            }

            replaceStatus(replaceI18n.matchNotFound, true);
        }

        function runReplaceNext() {
            const config = replacePatternConfig();
            if (!config) {
                return;
            }

            const replacement = replaceInput ? replaceInput.value : '';
            const ids = replaceScopeIds();
            for (let i = 0; i < ids.length; i += 1) {
                const id = ids[i];
                const editor = getEditorApi(id);
                if (!editor) {
                    continue;
                }
                const result = replaceOneInText(editor.getText(), config, replacement, editor.getSelectionEnd());
                if (!result) {
                    continue;
                }
                activeBodyEditorId = id;
                replaceCursorState[id] = result.end;
                editor.setText(result.text);
                editor.selectRange(result.start, result.end);
                replaceStatus(replaceI18n.replacedOne.replace('{field}', replaceFieldLabel(id)), false);
                return;
            }

            replaceStatus(replaceI18n.matchNotFound, true);
        }

        function runReplaceAll() {
            const config = replacePatternConfig();
            if (!config) {
                return;
            }

            const replacement = replaceInput ? replaceInput.value : '';
            let total = 0;
            replaceScopeIds().forEach(function (id) {
                const editor = getEditorApi(id);
                if (!editor) {
                    return;
                }
                const result = replaceAllInText(editor.getText(), config, replacement);
                if (!result || result.count < 1) {
                    return;
                }
                total += result.count;
                replaceCursorState[id] = 0;
                editor.setText(result.text);
            });

            if (total < 1) {
                replaceStatus(replaceI18n.matchNotFound, true);
                return;
            }

            replaceStatus(replaceI18n.replacedMany.replace('{count}', String(total)), false);
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

        document.getElementById('article_find_next')?.addEventListener('click', runFindNext);
        document.getElementById('article_replace_next')?.addEventListener('click', runReplaceNext);
        document.getElementById('article_replace_all')?.addEventListener('click', runReplaceAll);
        findInput?.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                runFindNext();
            }
        });

        function activateArticleTab(target) {
            const buttons = document.querySelectorAll('.article-editor-tabs .tab-btn');
            const panes = document.querySelectorAll('.article-editor-pane');
            let matched = false;

            buttons.forEach(function (item) {
                const active = item.dataset.tab === target;
                item.classList.toggle('active', active);
                if (active) {
                    matched = true;
                }
            });

            panes.forEach(function (pane) {
                pane.classList.toggle('is-active', pane.dataset.pane === target);
            });

            if (matched) {
                localStorage.setItem(articleTabPrefKey, target);
            }
            placeModeControl();
        }

        document.querySelectorAll('.article-editor-tabs .tab-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                activateArticleTab(button.dataset.tab);
            });
        });

        const savedArticleTab = localStorage.getItem(articleTabPrefKey);
        if (savedArticleTab && document.querySelector('.article-editor-pane[data-pane="' + savedArticleTab + '"]')) {
            activateArticleTab(savedArticleTab);
        } else {
            activateArticleTab('content');
        }

        var _attachTarget = 'image_url';
        var _attachPreview = 'image_preview';
        window.insertAttachmentUrl = function(url){
            var inp = document.getElementById(_attachTarget);
            var prev = document.getElementById(_attachPreview);
            if (inp) {
                inp.value = url;
                if (prev) {
                    prev.classList.remove('u-hide');
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
