<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">News</p>
            <h3>Настройки новостей</h3>
        </div>
    </div>

    <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert success">Настройки сохранены</div>
    <?php endif; ?>

    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <label class="field">
            <span>Новостей на странице</span>
            <input type="number" name="news_per_page" min="1" max="500" step="1" value="<?= (int)($settings['per_page'] ?? 9) ?>">
            <small class="muted">Любое число от 1 до 500.</small>
        </label>
        <div class="grid two">
            <label class="field">
                <span>SEO title (RU) for /news</span>
                <input type="text" name="news_seo_title_ru" value="<?= htmlspecialchars((string)($settings['seo_title_ru'] ?? '')) ?>" placeholder="Новости — ваш проект">
            </label>
            <label class="field">
                <span>SEO title (EN) for /news</span>
                <input type="text" name="news_seo_title_en" value="<?= htmlspecialchars((string)($settings['seo_title_en'] ?? '')) ?>" placeholder="News — your project">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>SEO description (RU) for /news</span>
                <textarea name="news_seo_desc_ru" rows="3" placeholder="Краткое описание страницы новостей"><?= htmlspecialchars((string)($settings['seo_desc_ru'] ?? '')) ?></textarea>
            </label>
            <label class="field">
                <span>SEO description (EN) for /news</span>
                <textarea name="news_seo_desc_en" rows="3" placeholder="Short description for news page"><?= htmlspecialchars((string)($settings['seo_desc_en'] ?? '')) ?></textarea>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary">Сохранить</button>
            <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/news">Назад к новостям</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$title = $title ?? 'News Settings';
include APP_ROOT . '/modules/Admin/views/layout.php';
