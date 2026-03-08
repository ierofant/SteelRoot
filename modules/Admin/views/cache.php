<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="stack">

<?php if (!empty($message)): ?>
    <div class="alert success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Настройки кэширования -->
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('cache.settings.title') ?></p>
            <h3><?= __('cache.settings.title') ?></h3>
        </div>
    </div>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/cache/settings" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="grid two">
            <!-- Статьи -->
            <div class="card stack">
                <label class="data" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="cache_articles" value="1"
                        <?= ($settings->get('cache_articles', '0') === '1') ? 'checked' : '' ?>>
                    <span><?= __('cache.settings.articles') ?></span>
                </label>
                <div class="field">
                    <span><?= __('cache.settings.articles_ttl') ?></span>
                    <input type="number" name="cache_articles_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('cache_articles_ttl', '60') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
            </div>

            <!-- Новости -->
            <div class="card stack">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="cache_news" value="1"
                        <?= ($settings->get('cache_news', '0') === '1') ? 'checked' : '' ?>>
                    <span>Кэшировать новости</span>
                </label>
                <div class="field">
                    <span>TTL новостей (мин)</span>
                    <input type="number" name="cache_news_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('cache_news_ttl', '60') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
            </div>

            <!-- Галерея -->
            <div class="card stack">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="cache_gallery" value="1"
                        <?= ($settings->get('cache_gallery', '0') === '1') ? 'checked' : '' ?>>
                    <span><?= __('cache.settings.gallery') ?></span>
                </label>
                <div class="field">
                    <span><?= __('cache.settings.gallery_ttl') ?></span>
                    <input type="number" name="cache_gallery_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('cache_gallery_ttl', '60') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
            </div>

            <!-- Главная -->
            <div class="card stack">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="cache_home" value="1"
                        <?= ($settings->get('cache_home', '0') === '1') ? 'checked' : '' ?>>
                    <span><?= __('cache.settings.home') ?></span>
                </label>
                <div class="field">
                    <span><?= __('cache.settings.home_ttl') ?></span>
                    <input type="number" name="cache_home_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('cache_home_ttl', '10') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
            </div>

            <!-- Поиск + Sitemap -->
            <div class="card stack">
                <div class="field">
                    <span><?= __('cache.settings.search_ttl') ?></span>
                    <input type="number" name="search_cache_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('search_cache_ttl', '10') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
                <div class="field">
                    <span><?= __('cache.settings.sitemap_ttl') ?></span>
                    <input type="number" name="sitemap_cache_ttl" min="0" max="10080"
                        value="<?= (int)$settings->get('sitemap_cache_ttl', '10') ?>">
                    <small class="muted"><?= __('cache.settings.ttl_hint') ?></small>
                </div>
            </div>
        </div>

        <!-- Минификация HTML -->
        <div class="card stack">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                <input type="checkbox" name="minify_html" value="1"
                    <?= ($settings->get('minify_html', '0') === '1') ? 'checked' : '' ?>>
                <span><?= __('cache.settings.minify') ?></span>
            </label>
            <small class="muted"><?= __('cache.settings.minify_hint') ?></small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('cache.settings.save') ?></button>
        </div>
    </form>
</div>

<!-- Управление кэшем -->
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('cache.title') ?></p>
            <h3><?= __('cache.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('cache.action.back_admin') ?></a>
    </div>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span class="muted" style="font-size:.85rem;">
            <?= (int)($stats['files'] ?? 0) ?> файлов · <?= number_format(($stats['size'] ?? 0) / 1024, 1) ?> KB
            · <code class="muted" style="font-size:.8rem;"><?= htmlspecialchars($stats['path'] ?? '') ?></code>
        </span>
        <form method="post" action="<?= htmlspecialchars($ap) ?>/cache/clear" style="margin-left:auto;">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <button type="submit" class="btn danger small"><?= __('cache.action.clear') ?></button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table data">
            <thead>
                <tr>
                    <th>Тип</th>
                    <th>Ключ</th>
                    <th>Тип данных</th>
                    <th>Размер</th>
                    <th>Истекает</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries ?? [] as $entry):
                    $key = $entry['key'];
                    if (str_starts_with($key, 'article_'))       $label = '📄 Статья';
                    elseif (str_starts_with($key, 'news_'))      $label = '📰 Новости';
                    elseif (str_starts_with($key, 'gallery_'))   $label = '🖼 Галерея';
                    elseif (str_starts_with($key, 'home_'))      $label = '🏠 Главная';
                    elseif (str_starts_with($key, 'search_'))    $label = '🔍 Поиск';
                    elseif ($key === 'sitemap')                   $label = '🗺 Sitemap';
                    elseif (str_starts_with($key, 'redirects'))  $label = '↪ Редиректы';
                    else                                         $label = '📦 Прочее';
                ?>
                <tr>
                    <td><span class="pill small"><?= $label ?></span></td>
                    <td><code style="font-size:.8rem;"><?= htmlspecialchars($key) ?></code></td>
                    <td><span class="pill"><?= htmlspecialchars($entry['type']) ?></span></td>
                    <td><?= number_format($entry['size'] / 1024, 1) ?> KB</td>
                    <td class="muted" style="font-size:.82rem;">
                        <?php
                        $diff = $entry['expires'] - time();
                        if ($entry['expires'] === 0) echo '∞';
                        elseif ($diff < 0) echo '<span style="color:#f87171">истёк</span>';
                        else echo htmlspecialchars(gmdate('H:i:s', $diff)) . ' осталось';
                        ?>
                    </td>
                    <td class="actions">
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/cache/delete">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
                            <button type="submit" class="btn ghost small">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="muted">Кэш пуст.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>
<?php
$title = __('cache.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
