<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$tagNames = $tags ?? [];
$tagsInput = $tagsInput ?? '';
$suggestedTags = $suggestedTags ?? [];
$categories = $categories ?? [];
$selectedCategoryIds = array_map('intval', $selectedCategoryIds ?? []);
$returnUrl = $returnUrl ?? ($ap . '/gallery/upload');
$previewSrc = (string)($item['path_thumb'] ?? ($item['path_medium'] ?? ($item['path'] ?? '')));
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Галерея</p>
            <h3>Редактирование изображения</h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($returnUrl) ?>">К загрузкам</a>
    </div>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/edit/<?= (int)$item['id'] ?>" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
        <p><img src="<?= htmlspecialchars($previewSrc) ?>" alt="" class="thumb"></p>
        <div class="grid two">
            <label class="field locale-en">
                <span>Title (EN)</span>
                <input type="text" name="title_en" value="<?= htmlspecialchars($item['title_en'] ?? '') ?>">
            </label>
            <label class="field locale-ru">
                <span>Title (RU)</span>
                <input type="text" name="title_ru" value="<?= htmlspecialchars($item['title_ru'] ?? '') ?>">
            </label>
        </div>
        <div class="grid two">
            <label class="field locale-en">
                <span>Description (EN)</span>
                <textarea name="description_en" rows="4"><?= htmlspecialchars($item['description_en'] ?? '') ?></textarea>
            </label>
            <label class="field locale-ru">
                <span>Description (RU)</span>
                <textarea name="description_ru" rows="4"><?= htmlspecialchars($item['description_ru'] ?? '') ?></textarea>
            </label>
        </div>
        <label class="field">
            <span>Slug</span>
            <input type="text" name="slug" value="<?= htmlspecialchars((string)($item['slug'] ?? '')) ?>" placeholder="optional-custom-slug">
            <span class="muted u-font-085em">Slug больше не синхронизируется автоматически от title при редактировании. Меняется только если вы поменяете его здесь вручную.</span>
        </label>
        <label class="field">
            <span>Tags (#tag #tag-two)</span>
            <input type="text" name="tags" list="gallery-tags-list" value="<?= htmlspecialchars($tagsInput !== '' ? $tagsInput : implode(' ', array_map(static fn(array $tag): string => '#' . ltrim((string)($tag['name'] ?? ''), '#'), $tagNames))) ?>">
            <span class="muted u-font-085em">Если нужен уже существующий slug, выбирайте canonical тег из подсказок.</span>
        </label>
        <?php if ($suggestedTags !== []): ?>
            <datalist id="gallery-tags-list">
                <?php foreach ($suggestedTags as $tag): ?>
                    <?php $tagName = ltrim((string)($tag['name'] ?? $tag['slug'] ?? ''), '#'); ?>
                    <?php if ($tagName === '') { continue; } ?>
                    <option value="#<?= htmlspecialchars($tagName) ?>">
                <?php endforeach; ?>
            </datalist>
        <?php endif; ?>
        <label class="field">
            <span>Категории (multi)</span>
            <select name="category_ids[]" multiple size="<?= max(4, min(10, count($categories))) ?>">
                <?php foreach ($categories as $cat): ?>
                    <?php $catId = (int)$cat['id']; ?>
                    <option value="<?= $catId ?>" <?= in_array($catId, $selectedCategoryIds, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name_ru'] ?: $cat['name_en']) ?>
                        — /<?= htmlspecialchars($cat['slug']) ?>/
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="muted u-font-085em">Можно выбрать несколько категорий (Ctrl/Cmd + click). Файлы не перемещаются, меняется только принадлежность.</span>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary">Сохранить</button>
            <a class="btn ghost" href="<?= htmlspecialchars($returnUrl) ?>">Отмена</a>
        </div>
    </form>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/delete/<?= (int)$item['id'] ?>" onsubmit="return confirm('Delete image?')" class="form-actions">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
        <button type="submit" class="btn danger">Удалить</button>
    </form>
</div>
<?php
$title = 'Edit Image';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
