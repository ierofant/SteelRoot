<?php use App\Services\PublicImageInfoService; ?>
<?= \Core\Asset::styleTag('/assets/css/gallery.css') ?>
<?php
$enabledCategories = $enabledCategories ?? [];
$currentCategory = $currentCategory ?? null;
$currentCategorySlug = $currentCategory ? ($currentCategory['slug'] ?? '') : ($category ?? '');
$loc = $locale ?? 'en';
$masterLikeState = $masterLikeState ?? ['can_like' => false];
$masterLikeToken = $masterLikeToken ?? '';
$enableLoadMore = !empty($enableLoadMore);
$loadMoreApi = (string)($loadMoreApi ?? '');
$hasMoreItems = (($page ?? 1) * ($perPage ?? 9)) < ($total ?? 0);
$_galleryBase = !empty($currentCategory['slug']) ? '/gallery/category/' . rawurlencode($currentCategory['slug']) : '/gallery';
?>
<div class="gallery-hero">
    <div class="tt-ornament" aria-hidden="true">
        <span class="tt-cross"></span>
        <span class="tt-num">01</span>
    </div>
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? __('gallery.title')) ?></p>
        <h2><?= htmlspecialchars($title ?? __('gallery.title')) ?></h2>
    </div>
    <form method="get" action="<?= htmlspecialchars($_galleryBase ?? '/gallery') ?>" class="gallery-filter">
        <input type="text" name="tag" value="<?= htmlspecialchars($tag ?? '') ?>" placeholder="Искать по тегу">
        <select name="sort">
            <option value="new" <?= ($sort ?? 'new') === 'new' ? 'selected' : '' ?>>По новизне</option>
            <option value="likes" <?= ($sort ?? 'new') === 'likes' ? 'selected' : '' ?>>По лайкам</option>
            <option value="master_likes" <?= ($sort ?? 'new') === 'master_likes' ? 'selected' : '' ?>>По признанию мастеров</option>
            <option value="views" <?= ($sort ?? 'new') === 'views' ? 'selected' : '' ?>>По просмотрам</option>
            <option value="comments" <?= ($sort ?? 'new') === 'comments' ? 'selected' : '' ?>>По комментариям</option>
        </select>
        <button type="submit" class="btn ghost">Применить</button>
    </form>
</div>

<?php if (!empty($enabledCategories)): ?>
<?php
$categoryQuery = array_filter([
    'sort' => ($sort ?? 'new') !== 'new' ? ($sort ?? '') : '',
    'tag' => $tag ?? '',
]);
$categoryQueryString = !empty($categoryQuery) ? ('?' . http_build_query($categoryQuery)) : '';
?>
<div class="gallery-categories-mobile">
    <label class="gallery-categories-mobile__label" for="gallery-category-select">
        <?= $loc === 'ru' ? 'Категория' : 'Category' ?>
    </label>
    <select
        id="gallery-category-select"
        class="gallery-categories-mobile__select"
        onchange="if(this.value){window.location.href=this.value;}"
    >
        <option value="/gallery<?= htmlspecialchars($categoryQueryString) ?>" <?= $currentCategorySlug === '' ? 'selected' : '' ?>>
            <?= $loc === 'ru' ? 'Все категории' : 'All categories' ?>
        </option>
        <?php foreach ($enabledCategories as $ec): ?>
            <?php
            $ecLabel = $loc === 'ru' ? ($ec['name_ru'] ?: $ec['name_en']) : ($ec['name_en'] ?: $ec['name_ru']);
            $ecUrl = '/gallery/category/' . rawurlencode($ec['slug']) . $categoryQueryString;
            ?>
            <option value="<?= htmlspecialchars($ecUrl) ?>" <?= $currentCategorySlug === $ec['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ecLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
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
<script>
(() => {
    const mobile = document.querySelector('.gallery-categories-mobile');
    const desktop = document.querySelector('.gallery-categories');
    if (!mobile || !desktop || typeof window.matchMedia !== 'function') {
        return;
    }
    const media = window.matchMedia('(max-width: 1024px)');
    const sync = () => {
        if (media.matches) {
            mobile.style.setProperty('display', 'block', 'important');
            desktop.style.setProperty('display', 'none', 'important');
        } else {
            mobile.style.setProperty('display', 'none', 'important');
            desktop.style.setProperty('display', 'flex', 'important');
        }
    };
    sync();
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', sync);
    } else if (typeof media.addListener === 'function') {
        media.addListener(sync);
    }
})();
</script>
<?php endif; ?>
<div class="masonry" id="gallery-grid"<?= $enableLoadMore && $loadMoreApi !== '' && $hasMoreItems ? ' data-api="' . htmlspecialchars($loadMoreApi) . '" data-next-page="' . ((int)($page ?? 1) + 1) . '" data-current-page="' . (int)($page ?? 1) . '" data-history-param="page" data-history-enabled="1" data-sort="' . htmlspecialchars((string)($sort ?? 'new')) . '" data-master-like-enabled="' . (!empty($masterLikeState['can_like']) ? '1' : '0') . '" data-master-like-token="' . htmlspecialchars($masterLikeToken) . '" data-render-mode="gallery-list" data-lightbox-enabled="' . ((($openMode ?? 'lightbox') === 'lightbox') ? '1' : '0') . '"' : '' ?>>
    <?php foreach ($items as $idx => $item): ?>
        <?php
            $itemTitle = $locale === 'ru' ? ($item['title_ru'] ?? '') : ($item['title_en'] ?? '');
            $itemDesc = $locale === 'ru' ? ($item['description_ru'] ?? '') : ($item['description_en'] ?? '');
            if ($itemDesc === '') {
                $itemDesc = $locale === 'ru' ? ($item['description_en'] ?? '') : ($item['description_ru'] ?? '');
            }
            $likes = (int)($item['likes'] ?? 0);
            $masterLikes = (int)($item['master_likes_count'] ?? 0);
            $views = (int)($item['views'] ?? 0);
            $slug = $item['slug'] ?? null;
            $thumb = $item['path_thumb'] ?? $item['path_medium'] ?? $item['path'] ?? '';
            $thumbDims = PublicImageInfoService::dimensions($thumb);
            $full = $item['path_medium'] ?? $item['path'] ?? '';
            $lightbox = ($openMode ?? 'lightbox') === 'lightbox';
            $href = $lightbox
                ? $full
                : ($slug ? '/gallery/photo/' . urlencode($slug) : '/gallery?id=' . (int)$item['id']);
            $dataFull = $full ?: $thumb;
            $showMasterBadge = !empty($item['submitted_by_master']) && !empty($item['author_id']) && !empty($item['author_name']);
            $authorInitial = $showMasterBadge ? htmlspecialchars(function_exists('mb_strtoupper') && function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)$item['author_name'], 0, 1)) : strtoupper(substr((string)$item['author_name'], 0, 1))) : '';
        ?>
        <article class="masonry-item">
            <a class="frame<?= $lightbox ? ' lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>"<?= $slug ? ' data-slug="'.htmlspecialchars($slug).'"' : '' ?><?= $lightbox ? ' data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($dataFull).'" data-title="'.htmlspecialchars($itemTitle).'" data-likes="'.$likes.'" data-master-likes="'.$masterLikes.'" data-can-master-like="'.(!empty($item['can_receive_master_like']) ? '1' : '0').'" data-views="'.$views.'"' : '' ?>>
                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($itemTitle) ?>" loading="<?= $idx < 4 ? 'eager' : 'lazy' ?>" decoding="async"<?= $idx < 4 ? ' fetchpriority="high"' : '' ?><?= $thumbDims ? (' width="' . (int)$thumbDims['width'] . '" height="' . (int)$thumbDims['height'] . '"') : '' ?>>
                <?php if ($showMasterBadge): ?>
                    <span class="gallery-master-badge" aria-label="<?= htmlspecialchars((string)$item['author_name']) ?>">
                        <span class="gallery-master-badge__avatar">
                            <?php if (!empty($item['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars((string)$item['author_avatar']) ?>" alt="<?= htmlspecialchars((string)$item['author_name']) ?>">
                            <?php else: ?>
                                <?= $authorInitial ?>
                            <?php endif; ?>
                        </span>
                        <span class="gallery-master-badge__name"><?= htmlspecialchars((string)$item['author_name']) ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($itemTitle): ?><div class="caption"><?= htmlspecialchars($itemTitle) ?></div><?php endif; ?>
            </a>
            <div class="meta-floating">
                <span>👁 <span class="g-views"><?= $views ?></span></span>
                <button type="button" class="like-chip" data-like-type="gallery" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes" data-like-count><?= $likes ?></span></button>
                <?php if (!empty($masterLikeState['can_like']) && !empty($item['can_receive_master_like'])): ?>
                    <button
                        type="button"
                        class="master-like-chip master-like-chip--action"
                        data-id="<?= (int)$item['id'] ?>"
                        data-token="<?= htmlspecialchars($masterLikeToken) ?>"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                        <span class="g-master-likes"><?= $masterLikes ?></span>
                    </button>
                <?php else: ?>
                    <span class="master-like-chip" data-id="<?= (int)$item['id'] ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                        <span class="g-master-likes"><?= $masterLikes ?></span>
                    </span>
                <?php endif; ?>
            </div>
        </article>
<?php endforeach; ?>
</div>
<?php if ($enableLoadMore && $loadMoreApi !== '' && $hasMoreItems): ?>
<div class="gallery-load-more-wrap" id="gallery-more-wrap">
    <button type="button" class="gallery-load-more-btn" id="gallery-load-more" data-idle-label="Ещё фото в галерее" data-loading-label="Подгружаем ещё фото...">
        <span class="gallery-load-more-btn__icon" aria-hidden="true">+</span>
        <span class="gallery-load-more-btn__label">Ещё фото в галерее</span>
    </button>
</div>
<?php else: ?>
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
<?php endif; ?>
<?php
$popularTags = $popularTags ?? [];
$currentTag = trim((string)($tag ?? ''));
?>
<?php if (!empty($popularTags)): ?>
<section class="gallery-tag-row gallery-tag-row--footer" aria-label="Popular gallery tags">
    <div class="gallery-tag-row__meta">
        <span class="gallery-tag-row__label">Top 100 tags</span>
        <a class="gallery-tag-row__action" href="/tags">All tags</a>
    </div>
    <div class="gallery-tag-band__list">
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
        <a class="gallery-tag-chip<?= $isActiveTag ? ' is-active' : '' ?>" href="/tags/<?= rawurlencode($tagSlug) ?>/gallery">
            <span class="gallery-tag-chip__hash">#</span>
            <span class="gallery-tag-chip__label"><?= htmlspecialchars($tagLabel) ?></span>
        </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($items)): ?>
<div class="lightbox" id="lightbox" hidden aria-modal="true" role="dialog" data-master-like-enabled="<?= !empty($masterLikeState['can_like']) ? '1' : '0' ?>" data-master-like-token="<?= htmlspecialchars($masterLikeToken) ?>">
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
            <button class="lightbox__master-like" id="lightbox-master-like-btn" type="button" aria-label="<?= htmlspecialchars(__('gallery.master_like.action')) ?>" hidden>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span id="lightbox-master-likes">0</span>
            </button>
            <a class="lightbox__open-link" id="lightbox-open" href="#" aria-label="Открыть страницу" hidden>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15 3h6v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 14L21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </a>
            <button class="lightbox__comments-btn" id="lightbox-comments-btn" type="button" aria-label="Комментарии" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            </button>
        </div>
    </div>
    <div class="lightbox__comments-panel" id="lightbox-comments-panel" aria-hidden="true">
        <div class="lightbox__comments-head">
            <span class="lightbox__comments-title">Комментарии</span>
            <button class="lightbox__comments-close" id="lightbox-comments-close" type="button" aria-label="Закрыть">
                <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M2 2l14 14M16 2L2 16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
        </div>
        <div class="lightbox__comments-body" id="lightbox-comments-body">
            <p class="lightbox__comments-placeholder">Загрузка…</p>
        </div>
    </div>
</div>
<?= \Core\Asset::scriptTag('/assets/js/gallery-lightbox.min.js') ?>
<?php endif; ?>
<?php if (!empty($display['show_likes']) || !empty($masterLikeState['can_like'])): ?>
<?php $galleryJs = APP_ROOT . '/modules/Gallery/assets/js/gallery.js'; ?>
<?= \Core\Asset::scriptTag('/modules/Gallery/assets/js/gallery.js', ['defer' => true]) ?>
<?php endif; ?>
<?php if ($enableLoadMore && $loadMoreApi !== '' && $hasMoreItems): ?>
<?= \Core\Asset::scriptTag('/modules/Gallery/assets/js/tag-gallery-load-more.min.js', ['defer' => true]) ?>
<?php endif; ?>
