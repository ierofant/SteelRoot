<?php
/**
 * Pagination partial.
 *   $paginationPage    int    - current page
 *   $paginationTotal   int    - total items
 *   $paginationPerPage int    - items per page
 *   $paginationBase    string - base URL, e.g. "/articles" or "/gallery/category/foo"
 *   $paginationChpu    bool   - use /page/N style (default false = ?page=N)
 *   $paginationQuery   string - extra query string for CHPU mode, e.g. "?sort=new" (optional)
 */
$_totalPages = (int)ceil($paginationTotal / $paginationPerPage);
if ($_totalPages <= 1) {
    return;
}

$_cur   = (int)$paginationPage;
$_chpu  = $paginationChpu ?? false;
$_base  = rtrim($paginationBase, '/');
$_query = $paginationQuery ?? '';

if ($_chpu) {
    $_url = static fn(int $p) => $p === 1
        ? htmlspecialchars($_base . $_query)
        : htmlspecialchars($_base . '/page/' . $p . $_query);
} else {
    $_param = $paginationParam ?? 'page';
    $_sep   = str_contains($paginationBase, '?') ? '&amp;' : '?';
    $_url   = static fn(int $p) => htmlspecialchars($paginationBase) . $_sep . $_param . '=' . $p;
}

$_start = max(1, $_cur - 2);
$_end   = min($_totalPages, $_cur + 2);
?>
<nav class="pagination" aria-label="Страницы">
    <?php if ($_cur > 1): ?>
        <a class="btn ghost small" href="<?= $_url($_cur - 1) ?>" aria-label="Назад">&laquo;</a>
    <?php endif; ?>

    <?php if ($_start > 1): ?>
        <a class="btn ghost small" href="<?= $_url(1) ?>">1</a>
        <?php if ($_start > 2): ?><span class="pagination__ellipsis">…</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $_start; $i <= $_end; $i++): ?>
        <a class="btn <?= $i === $_cur ? 'primary' : 'ghost' ?> small"
           href="<?= $_url($i) ?>"
           <?= $i === $_cur ? 'aria-current="page"' : '' ?>><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($_end < $_totalPages): ?>
        <?php if ($_end < $_totalPages - 1): ?><span class="pagination__ellipsis">…</span><?php endif; ?>
        <a class="btn ghost small" href="<?= $_url($_totalPages) ?>"><?= $_totalPages ?></a>
    <?php endif; ?>

    <?php if ($_cur < $_totalPages): ?>
        <a class="btn ghost small" href="<?= $_url($_cur + 1) ?>" aria-label="Вперёд">&raquo;</a>
    <?php endif; ?>
</nav>
