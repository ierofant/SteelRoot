<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Поиск</p>
            <h3>Настройки поиска</h3>
            <p class="muted">Управляйте лимитами, кешем и источниками выдачи.</p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') ?>">Админка</a>
    </div>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?><div class="alert success"><?= htmlspecialchars($_GET['msg']) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field">
                <span>Rate limit search (/min)</span>
                <input type="number" name="rl_search" value="<?= htmlspecialchars($settings['rl_search'] ?? 30) ?>">
            </label>
            <label class="field">
                <span>Rate limit autocomplete (/min)</span>
                <input type="number" name="rl_autocomplete" value="<?= htmlspecialchars($settings['rl_autocomplete'] ?? 60) ?>">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span>Search cache TTL (мин)</span>
                <input type="number" name="search_cache_ttl" value="<?= htmlspecialchars($settings['search_cache_ttl'] ?? 10) ?>" min="0" max="1440">
                <span class="muted">0 — отключить кеш результатов поиска.</span>
            </label>
            <label class="field">
                <span>Search max results</span>
                <input type="number" name="search_max_results" value="<?= htmlspecialchars($settings['search_max_results'] ?? 20) ?>" min="1" max="200">
            </label>
        </div>
        <div class="grid three mini-cards">
            <label class="field checkbox card" style="padding:10px;">
                <input type="checkbox" name="search_include_articles" value="1" <?= (($settings['search_include_articles'] ?? '1') === '1') ? 'checked' : '' ?>>
                <span>Искать в статьях</span>
            </label>
            <label class="field checkbox card" style="padding:10px;">
                <input type="checkbox" name="search_include_gallery" value="1" <?= (($settings['search_include_gallery'] ?? '1') === '1') ? 'checked' : '' ?>>
                <span>Искать в галерее</span>
            </label>
            <label class="field checkbox card" style="padding:10px;">
                <input type="checkbox" name="search_include_tags" value="1" <?= !empty($settings['search_include_tags']) ? 'checked' : '' ?>>
                <span>Искать теги</span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary">Сохранить</button>
        </div>
    </form>
    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/search/rebuild') ?>" class="form-actions">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="btn ghost">Пересоздать индекс</button>
        <span class="muted">Полная пересборка search_index и очистка кеша поиска.</span>
    </form>
</div>
<?php
$title = 'Search settings';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
