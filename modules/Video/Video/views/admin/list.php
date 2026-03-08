<?php
$ap  = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$sort = $sort ?? 'created_at';
$dir  = $dir  ?? 'desc';
$lm   = $localeMode ?? 'multi';
$categoryFilter = (int)($categoryFilter ?? 0);
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

$page    = $page ?? 1;
$sortUrl = static function(string $col) use ($ap, $sort, $dir, $page, $categoryFilter): string {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = $ap . '/videos?sort=' . $col . '&dir=' . $newDir . '&page=' . $page;
    if ($categoryFilter > 0) {
        $url .= '&category_id=' . $categoryFilter;
    }
    return htmlspecialchars($url);
};
$sortIcon = static function(string $col) use ($sort, $dir): string {
    if ($sort !== $col) return '<span class="sort-icon muted">↕</span>';
    return '<span class="sort-icon">' . ($dir === 'asc' ? '↑' : '↓') . '</span>';
};

ob_start();
?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= htmlspecialchars($i18n('Видеогалерея / Video Gallery', 'Video Gallery', 'Видеогалерея')) ?></p>
            <h3><?= htmlspecialchars($i18n('Видео / Videos', 'Videos', 'Видео')) ?></h3>
        </div>
        <div class="form-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos/categories"><?= htmlspecialchars($i18n('Категории / Categories', 'Categories', 'Категории')) ?></a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/videos/create"><?= htmlspecialchars($i18n('Добавить видео / Add Video', 'Add Video', 'Добавить видео')) ?></a>
        </div>
    </div>
    <form method="get" action="<?= htmlspecialchars($ap) ?>/videos" class="form-actions">
        <label class="field">
            <span><?= htmlspecialchars($i18n('Категория / Category', 'Category', 'Категория')) ?></span>
            <select name="category_id">
                <option value="0"><?= htmlspecialchars($i18n('Все категории / All categories', 'All categories', 'Все категории')) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $catLabel = $lm === 'ru'
                        ? (($cat['name_ru'] ?? '') ?: ($cat['name_en'] ?? ''))
                        : (($cat['name_en'] ?? '') ?: ($cat['name_ru'] ?? ''));
                    ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $categoryFilter === (int)$cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($catLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn ghost"><?= htmlspecialchars($i18n('Применить / Apply', 'Apply', 'Применить')) ?></button>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/videos"><?= htmlspecialchars($i18n('Сброс / Reset', 'Reset', 'Сброс')) ?></a>
    </form>
    <div class="table-wrap">
        <table class="table data">
            <thead>
                <tr>
                    <th><?= htmlspecialchars($i18n('Превью / Thumbnail', 'Thumbnail', 'Превью')) ?></th>
                    <th><a class="sort-link" href="<?= $sortUrl('title_en') ?>"><?= htmlspecialchars($i18n('Заголовок EN / Title EN', 'Title EN', 'Заголовок EN')) ?> <?= $sortIcon('title_en') ?></a></th>
                    <th><a class="sort-link" href="<?= $sortUrl('title_ru') ?>"><?= htmlspecialchars($i18n('Заголовок RU / Title RU', 'Title RU', 'Заголовок RU')) ?> <?= $sortIcon('title_ru') ?></a></th>
                    <th><a class="sort-link" href="<?= $sortUrl('category') ?>"><?= htmlspecialchars($i18n('Категория / Category', 'Category', 'Категория')) ?> <?= $sortIcon('category') ?></a></th>
                    <th><?= htmlspecialchars($i18n('Тип / Type', 'Type', 'Тип')) ?></th>
                    <th><a class="sort-link" href="<?= $sortUrl('views') ?>"><?= htmlspecialchars($i18n('Просмотры / Views', 'Views', 'Просмотры')) ?> <?= $sortIcon('views') ?></a></th>
                    <th><a class="sort-link" href="<?= $sortUrl('likes') ?>"><?= htmlspecialchars($i18n('Лайки / Likes', 'Likes', 'Лайки')) ?> <?= $sortIcon('likes') ?></a></th>
                    <th><a class="sort-link" href="<?= $sortUrl('created_at') ?>"><?= htmlspecialchars($i18n('Создано / Created', 'Created', 'Создано')) ?> <?= $sortIcon('created_at') ?></a></th>
                    <th><?= htmlspecialchars($i18n('Вкл / En', 'En', 'Вкл')) ?></th>
                    <th><?= htmlspecialchars($i18n('Действия / Actions', 'Actions', 'Действия')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $v): ?>
                    <?php
                    $thumb = !empty($v['thumbnail_url'])
                        ? $v['thumbnail_url']
                        : ($v['video_type'] === 'youtube' && !empty($v['video_id'])
                            ? 'https://img.youtube.com/vi/' . rawurlencode($v['video_id']) . '/mqdefault.jpg'
                            : null);
                    ?>
                    <tr>
                        <td>
                            <?php if ($thumb): ?>
                                <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="video-admin-thumb">
                            <?php else: ?>
                                <div class="video-admin-thumb-empty">▶</div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(mb_strimwidth($v['title_en'] ?? '', 0, 50, '…')) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($v['title_ru'] ?? '', 0, 50, '…')) ?></td>
                        <td>
                            <?php
                            $catName = $lm === 'ru'
                                ? (($v['category_name_ru'] ?? '') ?: ($v['category_name_en'] ?? ''))
                                : (($v['category_name_en'] ?? '') ?: ($v['category_name_ru'] ?? ''));
                            ?>
                            <?= htmlspecialchars($catName ?: '—') ?>
                        </td>
                        <td><span class="pill"><?= htmlspecialchars($v['video_type']) ?></span></td>
                        <td><?= (int)($v['views'] ?? 0) ?></td>
                        <td><?= (int)($v['likes'] ?? 0) ?></td>
                        <td><?= htmlspecialchars(substr($v['created_at'] ?? '', 0, 10)) ?></td>
                        <td><?= $v['enabled'] ? '✓' : '—' ?></td>
                        <td class="actions">
                            <a class="btn ghost small" href="<?= htmlspecialchars($ap) ?>/videos/edit/<?= (int)$v['id'] ?>"><?= htmlspecialchars($i18n('Редактировать / Edit', 'Edit', 'Редактировать')) ?></a>
                            <form method="post" action="<?= htmlspecialchars($ap) ?>/videos/delete/<?= (int)$v['id'] ?>" onsubmit="return confirm('<?= htmlspecialchars($i18n('Удалить это видео? / Delete this video?', 'Delete this video?', 'Удалить это видео?')) ?>');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('video_admin')) ?>">
                                <button type="submit" class="btn danger small"><?= htmlspecialchars($i18n('Удалить / Delete', 'Delete', 'Удалить')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10" class="muted"><?= htmlspecialchars($i18n('Видео пока нет. / No videos yet.', 'No videos yet.', 'Видео пока нет.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $paginationPage    = $page ?? 1;
    $paginationTotal   = $total ?? 0;
    $paginationPerPage = $perPage ?? 20;
    $paginationBase    = $ap . '/videos?sort=' . urlencode($sort) . '&dir=' . urlencode($dir);
    if ($categoryFilter > 0) {
        $paginationBase .= '&category_id=' . $categoryFilter;
    }
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</div>
