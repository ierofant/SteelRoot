<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$lm = $localeMode ?? 'multi';
$isEdit = ($mode ?? 'create') === 'edit';
$cat = $cat ?? null;
$categories = $categories ?? [];

$i18n = static function (string $multi, string $en, string $ru) use ($lm): string {
    if ($lm === 'ru') {
        return $ru;
    }
    if ($lm === 'en') {
        return $en;
    }
    return $multi;
};
?>
<div class="grid two">
    <section class="card stack">
        <div class="card-header">
            <div>
                <p class="eyebrow"><?= htmlspecialchars($i18n('Видео / Videos', 'Videos', 'Видео')) ?></p>
                <h3><?= htmlspecialchars($i18n('Категории видео / Video Categories', 'Video Categories', 'Категории видео')) ?></h3>
            </div>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos">← <?= htmlspecialchars($i18n('К видео / To videos', 'To videos', 'К видео')) ?></a>
        </div>
        <div class="table-wrap">
            <table class="table data">
                <thead>
                <tr>
                    <th>ID</th>
                    <th><?= htmlspecialchars($i18n('Превью / Preview', 'Preview', 'Превью')) ?></th>
                    <th>Name EN</th>
                    <th>Name RU</th>
                    <th>Slug</th>
                    <th><?= htmlspecialchars($i18n('Порядок / Sort', 'Sort', 'Порядок')) ?></th>
                    <th><?= htmlspecialchars($i18n('Вкл / En', 'En', 'Вкл')) ?></th>
                    <th><?= htmlspecialchars($i18n('Действия / Actions', 'Actions', 'Действия')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td>
                            <div class="video-cat-thumb-box">
                                <?php if (!empty($row['image_url'])): ?>
                                    <img src="<?= htmlspecialchars((string)$row['image_url']) ?>" alt="" class="video-admin-thumb video-admin-thumb--category">
                                <?php else: ?>
                                    <div class="video-admin-thumb-empty video-admin-thumb-empty--category">🖼</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars((string)($row['name_en'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['name_ru'] ?? '')) ?></td>
                        <td><?= htmlspecialchars((string)($row['slug'] ?? '')) ?></td>
                        <td><?= (int)($row['sort_order'] ?? 0) ?></td>
                        <td><?= !empty($row['enabled']) ? '✓' : '—' ?></td>
                        <td class="actions">
                            <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/videos/categories/edit/<?= (int)$row['id'] ?>"><?= htmlspecialchars($i18n('Редактировать / Edit', 'Edit', 'Редактировать')) ?></a>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/videos/categories/delete/<?= (int)$row['id'] ?>" onsubmit="return confirm('<?= htmlspecialchars($i18n('Удалить категорию? / Delete category?', 'Delete category?', 'Удалить категорию?')) ?>');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('video_admin')) ?>">
                                <button type="submit" class="btn danger small"><?= htmlspecialchars($i18n('Удалить / Delete', 'Delete', 'Удалить')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="8" class="muted"><?= htmlspecialchars($i18n('Категорий пока нет. / No categories yet.', 'No categories yet.', 'Категорий пока нет.')) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card stack">
        <div class="card-header">
            <div>
                <p class="eyebrow"><?= htmlspecialchars($i18n('Видео / Videos', 'Videos', 'Видео')) ?></p>
                <h3><?= htmlspecialchars($isEdit
                    ? $i18n('Редактировать категорию / Edit category', 'Edit category', 'Редактировать категорию')
                    : $i18n('Новая категория / New category', 'New category', 'Новая категория')) ?></h3>
            </div>
            <?php if ($isEdit): ?>
                <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos/categories"><?= htmlspecialchars($i18n('Отмена / Cancel', 'Cancel', 'Отмена')) ?></a>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= $isEdit ? ($ap . '/videos/categories/edit/' . (int)($cat['id'] ?? 0)) : ($ap . '/videos/categories/create') ?>" class="stack" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('video_admin')) ?>">
            <label class="field">
                <span>Name EN</span>
                <input type="text" name="name_en" value="<?= htmlspecialchars((string)($cat['name_en'] ?? '')) ?>">
            </label>
            <label class="field">
                <span>Name RU</span>
                <input type="text" name="name_ru" value="<?= htmlspecialchars((string)($cat['name_ru'] ?? '')) ?>">
            </label>
            <label class="field">
                <span>Slug</span>
                <input type="text" name="slug" value="<?= htmlspecialchars((string)($cat['slug'] ?? '')) ?>" placeholder="tattoo-reviews">
            </label>
            <label class="field">
                <span><?= htmlspecialchars($i18n('Картинка категории / Category image', 'Category image', 'Картинка категории')) ?></span>
                <input type="text" name="image_url" value="<?= htmlspecialchars((string)($cat['image_url'] ?? '')) ?>" placeholder="/storage/uploads/...">
            </label>
            <label class="field">
                <span><?= htmlspecialchars($i18n('Или загрузить файл / Or upload file', 'Or upload file', 'Или загрузить файл')) ?></span>
                <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif">
            </label>
            <?php if (!empty($cat['image_url'])): ?>
                <div class="video-cat-thumb-box">
                    <img src="<?= htmlspecialchars((string)$cat['image_url']) ?>" alt="" class="video-admin-thumb video-admin-thumb--category">
                </div>
            <?php endif; ?>
            <label class="field">
                <span><?= htmlspecialchars($i18n('Порядок сортировки / Sort order', 'Sort order', 'Порядок сортировки')) ?></span>
                <input type="number" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>">
            </label>
            <label class="field">
                <input type="checkbox" name="enabled" value="1" <?= isset($cat) ? (!empty($cat['enabled']) ? 'checked' : '') : 'checked' ?>>
                <span><?= htmlspecialchars($i18n('Включена / Enabled', 'Enabled', 'Включена')) ?></span>
            </label>
            <div class="form-actions">
                <button class="btn primary" type="submit"><?= htmlspecialchars($i18n('Сохранить / Save', 'Save', 'Сохранить')) ?></button>
            </div>
        </form>
    </section>
</div>
