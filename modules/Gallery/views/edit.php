<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$tagNames = $tags ?? [];
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Галерея</p>
            <h3>Редактирование изображения</h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload">К загрузкам</a>
    </div>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/edit/<?= (int)$item['id'] ?>" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <p><img src="<?= htmlspecialchars($item['path']) ?>" alt="" class="thumb"></p>
        <div class="grid two">
            <label class="field">
                <span>Title (EN)</span>
                <input type="text" name="title_en" value="<?= htmlspecialchars($item['title_en'] ?? '') ?>">
            </label>
            <label class="field">
                <span>Title (RU)</span>
                <input type="text" name="title_ru" value="<?= htmlspecialchars($item['title_ru'] ?? '') ?>">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>Description (EN)</span>
                <textarea name="description_en" rows="4"><?= htmlspecialchars($item['description_en'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span>Description (RU)</span>
                <textarea name="description_ru" rows="4"><?= htmlspecialchars($item['description_ru'] ?? '') ?></textarea>
            </label>
        </div>
        <label class="field">
            <span>Tags (comma separated)</span>
            <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', array_column($tagNames, 'name'))) ?>">
        </label>
        <div class="grid two">
            <label class="field">
                <span>Категория</span>
                <input type="text" name="category" value="<?= htmlspecialchars($item['category'] ?? '') ?>">
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary">Сохранить</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload">Отмена</a>
        </div>
    </form>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/gallery/delete/<?= (int)$item['id'] ?>" onsubmit="return confirm('Delete image?')" class="form-actions">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="btn danger">Удалить</button>
    </form>
</div>
<?php
$title = 'Edit Image';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
