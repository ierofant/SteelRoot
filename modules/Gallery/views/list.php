<?php
$enabledCategories = $enabledCategories ?? [];
$currentCategory = $currentCategory ?? null;
$currentCategorySlug = $currentCategory ? ($currentCategory['slug'] ?? '') : ($category ?? '');
$loc = $locale ?? 'en';
?>
<div class="gallery-hero">
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? __('gallery.title')) ?></p>
        <h2><?= htmlspecialchars($title ?? __('gallery.title')) ?></h2>
    </div>
    <form method="get" action="/gallery" class="gallery-filter">
        <input type="text" name="tag" value="<?= htmlspecialchars($tag ?? '') ?>" placeholder="Искать по тегу">
        <select name="sort">
            <option value="new" <?= ($sort ?? 'new') === 'new' ? 'selected' : '' ?>>По новизне</option>
            <option value="likes" <?= ($sort ?? 'new') === 'likes' ? 'selected' : '' ?>>По лайкам</option>
            <option value="views" <?= ($sort ?? 'new') === 'views' ? 'selected' : '' ?>>По просмотрам</option>
        </select>
        <button type="submit" class="btn ghost">Применить</button>
    </form>
</div>

<?php if (!empty($enabledCategories)): ?>
<nav class="gallery-categories">
    <a class="pill <?= $currentCategorySlug === '' ? 'active' : '' ?>" href="/gallery">
        <?= $loc === 'ru' ? 'Все' : 'All' ?>
    </a>
    <?php foreach ($enabledCategories as $ec): ?>
        <?php
        $ecLabel = $loc === 'ru' ? ($ec['name_ru'] ?: $ec['name_en']) : ($ec['name_en'] ?: $ec['name_ru']);
        $isActive = $currentCategorySlug === $ec['slug'];
        ?>
        <a class="pill <?= $isActive ? 'active' : '' ?>" href="/gallery/category/<?= rawurlencode($ec['slug']) ?>">
            <?= htmlspecialchars($ecLabel) ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<div class="masonry" id="gallery-grid">
    <?php foreach ($items as $idx => $item): ?>
        <?php
            $itemTitle = $locale === 'ru' ? ($item['title_ru'] ?? '') : ($item['title_en'] ?? '');
            $itemDesc = $locale === 'ru' ? ($item['description_ru'] ?? '') : ($item['description_en'] ?? '');
            if ($itemDesc === '') {
                $itemDesc = $locale === 'ru' ? ($item['description_en'] ?? '') : ($item['description_ru'] ?? '');
            }
            $likes = (int)($item['likes'] ?? 0);
            $views = (int)($item['views'] ?? 0);
            $slug = $item['slug'] ?? null;
            $thumb = $item['path_thumb'] ?? $item['path_medium'] ?? $item['path'] ?? '';
            $full = $item['path_medium'] ?? $item['path'] ?? '';
            $lightbox = ($openMode ?? 'lightbox') === 'lightbox';
            $href = $lightbox
                ? $full
                : ($slug ? '/gallery/photo/' . urlencode($slug) : '/gallery?id=' . (int)$item['id']);
            $dataFull = $full ?: $thumb;
        ?>
        <a class="masonry-item<?= $lightbox ? ' lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>"<?= $slug ? ' data-slug="'.htmlspecialchars($slug).'"' : '' ?><?= $lightbox ? ' data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($dataFull).'" data-title="'.htmlspecialchars($itemTitle).'" data-likes="'.$likes.'" data-views="'.$views.'"' : '' ?>>
            <div class="frame">
                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($itemTitle) ?>">
                <div class="meta-floating">
                    <span>👁 <span class="g-views"><?= $views ?></span></span>
                    <button class="like-chip" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes"><?= $likes ?></span></button>
                </div>
                <?php if ($itemTitle): ?><div class="caption"><?= htmlspecialchars($itemTitle) ?></div><?php endif; ?>
            </div>
        </a>
<?php endforeach; ?>
</div>
<?php
$paginationPage    = $page ?? 1;
$paginationTotal   = $total ?? 0;
$paginationPerPage = $perPage ?? 9;
$_galleryBase = '/gallery';
if (!empty($currentCategory['slug'])) {
    $_galleryBase = '/gallery/category/' . rawurlencode($currentCategory['slug']);
}
$qs = array_filter(['sort' => ($sort ?? 'new') !== 'new' ? ($sort ?? '') : '', 'tag' => $tag ?? '']);
$paginationBase  = $_galleryBase;
$paginationChpu  = true;
$paginationQuery = !empty($qs) ? '?' . http_build_query($qs) : '';
include APP_ROOT . '/app/views/partials/pagination.php';
?>
<?php
$popularTags = $popularTags ?? [];
$currentTag = trim((string)($tag ?? ''));
?>
<?php if (!empty($popularTags)): ?>
<div class="tags gallery-tags-footer">
    <?php foreach ($popularTags as $pt): ?>
        <?php
        $tagSlug = (string)($pt['slug'] ?? '');
        $tagName = (string)($pt['name'] ?? $tagSlug);
        $tagLabel = ltrim($tagName, "# \t\n\r\0\x0B");
        if ($tagSlug === '') {
            continue;
        }
        $isActiveTag = ($currentTag !== '' && $currentTag === $tagSlug);
        ?>
        <a class="pill ghost<?= $isActiveTag ? ' active' : '' ?>" href="/tags/<?= rawurlencode($tagSlug) ?>/gallery">
            #<?= htmlspecialchars($tagLabel) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($items)): ?>
<div class="lightbox" id="lightbox" hidden aria-modal="true" role="dialog">
    <div class="lightbox__backdrop"></div>
    <button class="lightbox__close" id="lightbox-close" aria-label="Закрыть">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M2 2l14 14M16 2L2 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>
    <div class="lightbox__stage">
        <button class="lightbox__nav lightbox__prev" id="lightbox-prev" aria-label="Предыдущее">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="lightbox__nav lightbox__next" id="lightbox-next" aria-label="Следующее">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <img src="" alt="" id="lightbox-image" draggable="false">
    </div>
    <div class="lightbox__bar">
        <div class="lightbox__bar-left">
            <p class="lightbox__caption" id="lightbox-caption"></p>
            <span class="lightbox__counter" id="lightbox-counter"></span>
        </div>
        <div class="lightbox__bar-right">
            <span class="lightbox__stat">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="12" rx="11" ry="8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
                <span id="lightbox-views">0</span>
            </span>
            <button class="lightbox__like-btn" id="lightbox-like" aria-label="Лайк">
                <svg class="lightbox__heart" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                <span id="lightbox-likes">0</span>
            </button>
            <a class="lightbox__open-link" id="lightbox-open" href="#" aria-label="Открыть страницу" hidden>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 3h6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </a>
        </div>
    </div>
</div>
<script src="/assets/js/gallery-lightbox.js"></script>
<?php endif; ?>
<?php if (!empty($display['show_likes'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.like-chip').forEach(btn => {
        const id = btn.dataset.id;
        const key = 'liked_gallery_' + id;
        if (localStorage.getItem(key) === '1') {
            btn.classList.add('active');
        }
        btn.addEventListener('click', async () => {
            const currentId = btn.dataset.id;
            const likeCount = btn.querySelector('.g-likes');
            const likeBtn = btn.querySelector('.g-like-btn');
            if (!currentId) return;
            try {
                const res = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {'Accept':'application/json'},
                    body: new URLSearchParams({type:'gallery', id: currentId})
                });
                if (!res.ok) throw new Error('bad');
                const data = await res.json();
                const likes = data.likes ?? parseInt(btn.dataset.likes || '0', 10);
                document.querySelectorAll(`.like-chip[data-id="${currentId}"] .g-likes`).forEach(el => el.textContent = likes);
                if (likeCount && likeBtn && likeBtn.dataset.id === currentId) likeCount.textContent = likes;
                btn.classList.add('active');
                localStorage.setItem(key, '1');
                if (window.showToast) {
                    window.showToast(data.already ? 'Уже лайкнули' : 'Лайк засчитан', 'success');
                }
            } catch (_) {
                if (window.showToast) window.showToast('Не удалось поставить лайк', 'danger');
            }
        });
    });
});
</script>
<?php endif; ?>
