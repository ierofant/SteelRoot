<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">News</p>
            <h3>Настройки модуля новостей</h3>
        </div>
    </div>

    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="muted">Управление карточками списка, SEO страницы `/news` и поведением редактора.</div>
        <div class="module-settings-grid">
            <label class="field checkbox">
                <input type="checkbox" name="news_show_author" value="1" <?= !empty($settings['show_author']) ? 'checked' : '' ?>>
                <span>Показывать автора</span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="news_show_date" value="1" <?= !empty($settings['show_date']) ? 'checked' : '' ?>>
                <span>Показывать дату</span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="news_show_likes" value="1" <?= !empty($settings['show_likes']) ? 'checked' : '' ?>>
                <span>Показывать лайки</span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="news_show_views" value="1" <?= !empty($settings['show_views']) ? 'checked' : '' ?>>
                <span>Показывать просмотры</span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="news_show_tags" value="1" <?= !empty($settings['show_tags']) ? 'checked' : '' ?>>
                <span>Показывать теги</span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="news_description_enabled" value="1" <?= !empty($settings['description_enabled']) ? 'checked' : '' ?>>
                <span>Показывать meta description в карточках</span>
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>Колонок в сетке</span>
                <select name="news_grid_cols">
                    <?php foreach ([1,2,3,4,5,6] as $n): ?>
                        <option value="<?= $n ?>" <?= (int)($settings['grid_cols'] ?? 3) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Элементов на странице</span>
                <select name="news_per_page">
                    <?php foreach ([3,6,9,12,18,24,36] as $n): ?>
                        <option value="<?= $n ?>" <?= (int)($settings['per_page'] ?? 9) === $n ? 'selected' : '' ?>><?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO title (RU) для `/news`</span>
                <input type="text" name="news_seo_title_ru" value="<?= htmlspecialchars((string)($settings['seo_title_ru'] ?? '')) ?>" placeholder="Новости — ваш проект">
            </label>
            <label class="field">
                <span>SEO title (EN) для `/news`</span>
                <input type="text" name="news_seo_title_en" value="<?= htmlspecialchars((string)($settings['seo_title_en'] ?? '')) ?>" placeholder="News — your project">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO description (RU) для `/news`</span>
                <textarea name="news_seo_desc_ru" rows="3" placeholder="Краткое описание страницы новостей"><?= htmlspecialchars((string)($settings['seo_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field">
                <span>SEO description (EN) для `/news`</span>
                <textarea name="news_seo_desc_en" rows="3" placeholder="Short description for news page"><?= htmlspecialchars((string)($settings['seo_desc_en'] ?? '')) ?></textarea>
            </label>
        </div>
        <label class="field">
            <span>Папка загрузки по умолчанию</span>
            <input type="text" name="news_default_upload_folder" value="<?= htmlspecialchars((string)($settings['default_upload_folder'] ?? '')) ?>" placeholder="например: masters/moscow">
            <small class="muted">Подпапка внутри `/storage/uploads/news`, которая будет подставляться в форму новости автоматически.</small>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn primary">Сохранить</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/news">Назад к новостям</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = $title ?? 'News Settings';
include APP_ROOT . '/modules/Admin/views/layout.php';
