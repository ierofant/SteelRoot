<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$tags = $tags ?? [];
$query = $query ?? '';
$sort = $sort ?? 'usage';
$dir = $dir ?? 'desc';
$page = $page ?? 1;
$total = $total ?? 0;
$perPage = $perPage ?? 50;
$returnParams = array_filter([
    'q' => $query,
    'sort' => $sort !== 'usage' ? $sort : '',
    'dir' => $dir !== 'desc' ? $dir : '',
    'page' => $page > 1 ? (string)$page : '',
], static fn ($value) => $value !== '');
$returnQuery = $returnParams !== [] ? ('?' . http_build_query($returnParams)) : '';
$returnUrl = $ap . '/gallery/tags' . $returnQuery;
ob_start();
?>
<div class="gallery-tags-page stack">
<div class="card stack gallery-tags-shell">
    <div class="card-header gallery-tags-hero">
        <div class="gallery-tags-hero__copy">
            <p class="eyebrow">Gallery</p>
            <h3>Управление тегами</h3>
            <p class="muted">Канонические slug, чистка дублей и быстрый контроль того, как теги работают во всей галерее.</p>
        </div>
        <div class="u-flex u-gap-half u-flex-wrap gallery-tags-hero__actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload">К загрузкам</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/categories">Categories</a>
        </div>
    </div>
    <div class="mini-cards gallery-tags-summary">
        <div class="stat-card">
            <p class="muted">Всего тегов</p>
            <h3><?= (int)$total ?></h3>
        </div>
        <div class="stat-card">
            <p class="muted">На странице</p>
            <h3><?= count($tags) ?></h3>
        </div>
    </div>
    <form method="get" action="<?= htmlspecialchars($ap) ?>/gallery/tags" class="gallery-tags-filters">
        <label class="field u-m-0">
            <span>Поиск</span>
            <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="тигр / tiger / tigr">
        </label>
        <label class="field u-m-0">
            <span>Сортировка</span>
            <select name="sort">
                <option value="usage" <?= $sort === 'usage' ? 'selected' : '' ?>>По использованию</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>По названию</option>
                <option value="slug" <?= $sort === 'slug' ? 'selected' : '' ?>>По slug</option>
                <option value="id" <?= $sort === 'id' ? 'selected' : '' ?>>По ID</option>
            </select>
        </label>
        <label class="field u-m-0">
            <span>Направление</span>
            <select name="dir">
                <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>DESC</option>
                <option value="asc" <?= $dir === 'asc' ? 'selected' : '' ?>>ASC</option>
            </select>
        </label>
        <div class="form-actions gallery-tags-filters__actions">
            <button type="submit" class="btn primary">Найти</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/tags">Сбросить</a>
        </div>
    </form>
</div>

<div class="card gallery-tags-table-card">
    <div class="card-header gallery-tags-table-card__head">
        <div>
            <p class="eyebrow">Canonical</p>
            <h3>Редактор тегов</h3>
            <p class="muted">Меняй имя и canonical slug без переходов. Удаление сразу отвязывает тег от всех работ.</p>
        </div>
    </div>
    <div class="table-wrap gallery-tags-table-wrap">
        <table class="data gallery-tags-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Название</th>
                    <th>Canonical slug</th>
                    <th>Использований</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag): ?>
                    <?php $saveFormId = 'gallery-tag-save-' . (int)($tag['id'] ?? 0); ?>
                    <tr class="gallery-tags-table__row">
                        <td data-label="ID">
                            <span class="gallery-tags-id">#<?= (int)($tag['id'] ?? 0) ?></span>
                        </td>
                        <td data-label="Название">
                            <input type="text"
                                   name="name"
                                   form="<?= htmlspecialchars($saveFormId) ?>"
                                   class="gallery-tags-table__input"
                                   value="<?= htmlspecialchars((string)($tag['name'] ?? '')) ?>">
                        </td>
                        <td data-label="Canonical slug">
                            <input type="text"
                                   name="slug"
                                   form="<?= htmlspecialchars($saveFormId) ?>"
                                   class="gallery-tags-table__input"
                                   value="<?= htmlspecialchars((string)($tag['slug'] ?? '')) ?>"
                                   placeholder="tiger">
                        </td>
                        <td data-label="Использований">
                            <span class="pill gallery-tags-usage"><?= (int)($tag['usage_count'] ?? 0) ?></span>
                        </td>
                        <td data-label="Действия" class="gallery-tags-table__actions">
                            <form method="post"
                                  id="<?= htmlspecialchars($saveFormId) ?>"
                                  action="<?= htmlspecialchars($ap) ?>/gallery/tags/<?= (int)($tag['id'] ?? 0) ?>/save"
                                  class="gallery-tags-table__action-form">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                <button type="submit" class="btn ghost small">Сохранить</button>
                            </form>
                            <form method="post"
                                  action="<?= htmlspecialchars($ap) ?>/gallery/tags/<?= (int)($tag['id'] ?? 0) ?>/save"
                                  onsubmit="return confirm('Удалить тег «<?= htmlspecialchars(addslashes((string)($tag['name'] ?? '')) ) ?>»?') && confirm('Тег будет отвязан от <?= (int)($tag['usage_count'] ?? 0) ?> элементов галереи. Это действие нельзя отменить. Удалить?');"
                                  class="gallery-tags-table__action-form">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>">
                                <input type="hidden" name="delete" value="1">
                                <button type="submit" class="btn ghost danger small">Удалить</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tags === []): ?>
                    <tr><td colspan="5" class="muted gallery-tags-empty">Теги пока не найдены.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$paginationPage = $page;
$paginationTotal = $total;
$paginationPerPage = $perPage;
$paginationParams = array_filter([
    'q' => $query,
    'sort' => $sort !== 'usage' ? $sort : '',
    'dir' => $dir !== 'desc' ? $dir : '',
], static fn ($value) => $value !== '');
$paginationBase = $ap . '/gallery/tags' . ($paginationParams !== [] ? '?' . http_build_query($paginationParams) : '');
include APP_ROOT . '/app/views/partials/pagination.php';
?>
</div>
<?php
$title = 'Gallery Tags';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
