<?php
$ap      = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$isEdit  = ($mode ?? 'create') === 'edit';
$item    = $item ?? null;
$categories = $categories ?? [];
$lm      = $localeMode ?? 'multi';
$showEn  = ($lm !== 'ru');
$showRu  = ($lm !== 'en');

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
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= htmlspecialchars($i18n('Видеогалерея / Video Gallery', 'Video Gallery', 'Видеогалерея')) ?></p>
            <h3><?= htmlspecialchars($isEdit
                ? $i18n('Редактировать видео / Edit Video', 'Edit Video', 'Редактировать видео')
                : $i18n('Добавить видео / Add Video', 'Add Video', 'Добавить видео')) ?></h3>
        </div>
        <div class="form-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos/categories"><?= htmlspecialchars($i18n('Категории / Categories', 'Categories', 'Категории')) ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos">← <?= htmlspecialchars($i18n('Назад / Back', 'Back', 'Назад')) ?></a>
        </div>
    </div>

    <form method="post"
          action="<?= $isEdit ? $ap . '/videos/edit/' . (int)($item['id'] ?? 0) : $ap . '/videos/create' ?>"
          enctype="multipart/form-data"
          class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('video_admin')) ?>">

        <label class="field">
            <span><?= htmlspecialchars($i18n('Ссылка на видео / Video URL', 'Video URL', 'Ссылка на видео')) ?> <span class="muted"><?= htmlspecialchars($i18n('(YouTube, Vimeo, MP4 или embed) / (YouTube, Vimeo, MP4, or embed)', '(YouTube, Vimeo, MP4, or embed)', '(YouTube, Vimeo, MP4 или embed)')) ?></span></span>
            <input type="url" name="video_url" value="<?= htmlspecialchars($item['video_url'] ?? '') ?>"
                   placeholder="https://www.youtube.com/watch?v=...">
        </label>

        <label class="field">
            <span><?= htmlspecialchars($i18n('Или загрузить файл на сервер / Or upload file to server', 'Or upload file to server', 'Или загрузить файл на сервер')) ?> <span class="muted"><?= htmlspecialchars($i18n('(mp4, webm, ogg, mov) / (mp4, webm, ogg, mov)', '(mp4, webm, ogg, mov)', '(mp4, webm, ogg, mov)')) ?></span></span>
            <input type="file" name="video_file" accept="video/mp4,video/webm,video/ogg,video/quicktime,.mp4,.webm,.ogg,.mov,.m4v">
            <small class="muted"><?= htmlspecialchars($i18n('Если файл выбран, он приоритетнее URL. / If file is selected, it overrides URL.', 'If file is selected, it overrides URL.', 'Если файл выбран, он приоритетнее URL.')) ?></small>
        </label>

        <?php if ($showEn && $showRu): ?>
        <div class="grid two">
            <label class="field locale-en">
                <span>Title EN</span>
                <input type="text" name="title_en" value="<?= htmlspecialchars($item['title_en'] ?? '') ?>">
            </label>
            <label class="field locale-ru">
                <span>Title RU</span>
                <input type="text" name="title_ru" value="<?= htmlspecialchars($item['title_ru'] ?? '') ?>">
            </label>
        </div>
        <?php elseif ($showEn): ?>
        <label class="field">
            <span><?= htmlspecialchars($i18n('Title', 'Title', 'Название')) ?></span>
            <input type="text" name="title_en" value="<?= htmlspecialchars($item['title_en'] ?? '') ?>" required>
        </label>
        <?php else: ?>
        <label class="field">
            <span>Название</span>
            <input type="text" name="title_ru" value="<?= htmlspecialchars($item['title_ru'] ?? '') ?>" required>
        </label>
        <?php endif; ?>

        <?php if ($showEn && $showRu): ?>
        <div class="grid two">
            <label class="field locale-en">
                <span>Description EN</span>
                <textarea name="description_en" rows="4"><?= htmlspecialchars($item['description_en'] ?? '') ?></textarea>
            </label>
            <label class="field locale-ru">
                <span>Description RU</span>
                <textarea name="description_ru" rows="4"><?= htmlspecialchars($item['description_ru'] ?? '') ?></textarea>
            </label>
        </div>
        <?php elseif ($showEn): ?>
        <label class="field">
            <span><?= htmlspecialchars($i18n('Description', 'Description', 'Описание')) ?></span>
            <textarea name="description_en" rows="4"><?= htmlspecialchars($item['description_en'] ?? '') ?></textarea>
        </label>
        <?php else: ?>
        <label class="field">
            <span>Описание</span>
            <textarea name="description_ru" rows="4"><?= htmlspecialchars($item['description_ru'] ?? '') ?></textarea>
        </label>
        <?php endif; ?>

        <div class="grid two">
            <label class="field">
                <span><?= htmlspecialchars($i18n('Категория / Category', 'Category', 'Категория')) ?></span>
                <?php $selectedCategoryId = (int)($item['category_id'] ?? 0); ?>
                <select name="category_id">
                    <option value="0"><?= htmlspecialchars($i18n('Без категории / No category', 'No category', 'Без категории')) ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $catLabel = $lm === 'ru'
                            ? (($cat['name_ru'] ?? '') ?: ($cat['name_en'] ?? ''))
                            : (($cat['name_en'] ?? '') ?: ($cat['name_ru'] ?? ''));
                        ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $selectedCategoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($catLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span><?= htmlspecialchars($i18n('Слаг / Slug', 'Slug', 'Слаг')) ?> <span class="muted"><?= htmlspecialchars($i18n('(автогенерация, если пусто) / (auto-generated if empty)', '(auto-generated if empty)', '(автогенерация, если пусто)')) ?></span></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($item['slug'] ?? '') ?>" placeholder="my-video">
            </label>
            <label class="field">
                <span><?= htmlspecialchars($i18n('Длительность / Duration', 'Duration', 'Длительность')) ?> <span class="muted"><?= htmlspecialchars($i18n('(например, 12:34) / (e.g. 12:34)', '(e.g. 12:34)', '(например, 12:34)')) ?></span></span>
                <input type="text" name="duration" value="<?= htmlspecialchars($item['duration'] ?? '') ?>" placeholder="12:34">
            </label>
        </div>

        <label class="field">
            <span><?= htmlspecialchars($i18n('Ссылка на превью / Thumbnail URL', 'Thumbnail URL', 'Ссылка на превью')) ?> <span class="muted"><?= htmlspecialchars($i18n('(необязательно, для YouTube подставляется автоматически) / (optional, auto-resolved for YouTube)', '(optional, auto-resolved for YouTube)', '(необязательно, для YouTube подставляется автоматически)')) ?></span></span>
            <input type="url" name="thumbnail_url" value="<?= htmlspecialchars($item['thumbnail_url'] ?? '') ?>"
                   placeholder="https://...">
        </label>

        <label class="field video-enabled-field">
            <input type="checkbox" name="enabled" value="1" <?= !empty($item['enabled']) ? 'checked' : '' ?>>
            <span><?= htmlspecialchars($i18n('Включено (видно на сайте) / Enabled (visible on site)', 'Enabled (visible on site)', 'Включено (видно на сайте)')) ?></span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= htmlspecialchars($i18n('Сохранить / Save', 'Save', 'Сохранить')) ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos"><?= htmlspecialchars($i18n('Отмена / Cancel', 'Cancel', 'Отмена')) ?></a>
        </div>
    </form>
</div>
