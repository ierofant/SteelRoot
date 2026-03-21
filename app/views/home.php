<?php
$home = $home ?? [];
$gallery = $gallery ?? [];
$articles = $articles ?? [];
$sections = $sections ?? [];
$loc = $locale ?? 'en';
$locKey = $loc === 'ru' ? 'ru' : 'en';
$pageTitle = $home['page_title_' . $locKey] ?? ($locKey === 'ru' ? 'SteelRoot' : 'SteelRoot');
$pageDescription = $home['page_description_' . $locKey] ?? ($locKey === 'ru' ? 'Лёгкий старт для вашего сайта.' : 'Easy start for your site.');
$heroEyebrow = $home['hero_eyebrow_' . $locKey] ?? $home['hero_eyebrow_ru'] ?? 'Главная';
$statGalleryLabel = $home['stats_gallery_label_' . $locKey] ?? ($locKey === 'ru' ? 'Галерея' : 'Gallery');
$statArticlesLabel = $home['stats_articles_label_' . $locKey] ?? ($locKey === 'ru' ? 'Статьи' : 'Articles');
$galleryTitle = $home['gallery_title_' . $locKey] ?? ($locKey === 'ru' ? 'Галерея' : 'Gallery');
$gallerySlogan = $home['gallery_slogan_' . $locKey] ?? '';
$galleryCta = $home['gallery_cta_' . $locKey] ?? ($locKey === 'ru' ? 'Смотреть все →' : 'See all →');
$articlesTitle = $home['articles_title_' . $locKey] ?? ($locKey === 'ru' ? 'Статьи' : 'Articles');
$articlesSlogan = $home['articles_slogan_' . $locKey] ?? '';
$articlesCta = $home['articles_cta_' . $locKey] ?? ($locKey === 'ru' ? 'Все статьи →' : 'All articles →');
$customBlocksTitle = $home['custom_blocks_title_' . $locKey] ?? ($locKey === 'ru' ? 'Кастомные блоки' : 'Custom blocks');
$customBlockCta = $home['custom_block_cta_' . $locKey] ?? ($locKey === 'ru' ? 'Подробнее' : 'Read more');
$layoutClass = ($home['layout_mode'] ?? 'wide') === 'boxed' ? 'layout-boxed' : '';
$sectionPadding = (int)($home['section_padding'] ?? 80);
$sectionPadding = max(0, min(240, $sectionPadding));
?>
<section class="hero enhanced <?= $layoutClass ?>">
    <div class="hero-copy">
        <?php if (!empty($home['hero_badge'])): ?><span class="pill"><?= htmlspecialchars($home['hero_badge']) ?></span><?php endif; ?>
        <p class="eyebrow"><?= htmlspecialchars($heroEyebrow) ?></p>
        <h1><?= htmlspecialchars($home['hero_title'] ?? 'SteelRoot') ?></h1>
        <p class="lead"><?= htmlspecialchars($home['hero_subtitle'] ?? '') ?></p>
        <div class="cta-row">
            <?php if (!empty($home['hero_cta_text'])): ?>
                <a class="btn primary" href="<?= htmlspecialchars($home['hero_cta_url'] ?? '/contact') ?>"><?= htmlspecialchars($home['hero_cta_text']) ?></a>
            <?php endif; ?>
            <?php if (!empty($home['show_secondary_cta']) && !empty($home['secondary_cta_text'])): ?>
                <a class="btn ghost" href="<?= htmlspecialchars($home['secondary_cta_url'] ?? '#') ?>"><?= htmlspecialchars($home['secondary_cta_text']) ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($home['show_stats'])): ?>
        <div class="hero-card">
            <div class="orb"></div>
            <div class="stat">
                <span><?= htmlspecialchars($statGalleryLabel) ?></span>
                <strong><?= count($gallery) ?></strong>
            </div>
            <div class="stat">
                <span><?= htmlspecialchars($statArticlesLabel) ?></span>
                <strong><?= count($articles) ?></strong>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php foreach ($sections as $section): ?>
    <?php if ($section['type'] === 'gallery' && $gallery): ?>
        <section class="block home-section-padding <?= $layoutClass ?>">
            <div class="block-head">
                <div>
                    <h2><?= htmlspecialchars($galleryTitle) ?></h2>
                    <?php if ($gallerySlogan): ?><p class="block-slogan"><?= htmlspecialchars($gallerySlogan) ?></p><?php endif; ?>
                </div>
                <a class="link" href="/gallery"><?= htmlspecialchars($galleryCta) ?></a>
            </div>
            <div class="masonry">
                <?php foreach ($gallery as $idx => $g): ?>
                    <?php $tKey = $loc === 'ru' ? 'title_ru' : 'title_en'; ?>
                    <?php $title = $g[$tKey] ?? ''; ?>
                    <?php $slug = $g['slug'] ?? null; ?>
                    <?php $views = (int)($g['views'] ?? 0); ?>
                    <?php $likes = (int)($g['likes'] ?? 0); ?>
                    <?php $href = ($galleryMode ?? 'lightbox') === 'page'
                        ? ($slug ? '/gallery/photo/' . urlencode($slug) : '/gallery/view?id=' . (int)$g['id'])
                        : ($g['path_medium'] ?? $g['path']); ?>
                    <a class="masonry-item <?= ($galleryMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$g['id'] ?>"<?= $slug ? ' data-slug="'.htmlspecialchars($slug).'"' : '' ?><?= ($galleryMode ?? 'lightbox') === 'lightbox' ? ' data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($g['path_medium'] ?? $g['path']).'" data-title="'.htmlspecialchars($title).'" data-likes="'.$likes.'" data-views="'.$views.'"' : '' ?>>
                        <img src="<?= htmlspecialchars($g['path_thumb'] ?? $g['path_medium'] ?? '') ?>" alt="<?= htmlspecialchars($title) ?>">
                        <div class="meta-floating">
                            <span>👁 <?= $views ?></span>
                            <button type="button" class="like-chip" data-id="<?= (int)$g['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes"><?= $likes ?></span></button>
                        </div>
                        <?php if ($title): ?><div class="caption"><?= htmlspecialchars($title) ?></div><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($section['type'] === 'articles' && $articles): ?>
        <section class="block home-section-padding <?= $layoutClass ?>">
            <div class="block-head">
                <div>
                    <h2><?= htmlspecialchars($articlesTitle) ?></h2>
                    <?php if ($articlesSlogan): ?><p class="block-slogan"><?= htmlspecialchars($articlesSlogan) ?></p><?php endif; ?>
                </div>
                <a class="link" href="/articles"><?= htmlspecialchars($articlesCta) ?></a>
            </div>
            <section class="articles-grid articles-grid-cols-3">
                <?php foreach ($articles as $a): ?>
                    <?php
                        $tKey    = $loc === 'ru' ? 'title_ru' : 'title_en';
                        $aTitle  = $a[$tKey] ?? ($a['title_en'] ?? '');
                        $aDate   = !empty($a['created_at']) ? date('d.m.Y', strtotime($a['created_at'])) : '';
                        $aViews  = (int)($a['views'] ?? 0);
                        $aLikes  = (int)($a['likes'] ?? 0);
                        $aExcerpt = $loc === 'ru' ? ($a['preview_ru'] ?? '') : ($a['preview_en'] ?? '');
                        $aClass  = 'article-card' . (empty($a['image_url']) ? ' no-image' : '');
                    ?>
                    <article class="<?= $aClass ?>">
                        <?php if (!empty($a['image_url'])): ?>
                            <div class="article-card-bg"><img src="<?= htmlspecialchars($a['image_url']) ?>" alt=""></div>
                        <?php endif; ?>
                        <a class="article-card__link" href="/articles/<?= urlencode($a['slug']) ?>">
                            <div class="card-meta article-card__meta">
                                <?php if ($aDate): ?><span class="eyebrow"><?= htmlspecialchars($aDate) ?></span><?php endif; ?>
                                <?php
                                    $pieces = [];
                                    if ($aViews) $pieces[] = $aViews . '👁';
                                    if ($aLikes) $pieces[] = $aLikes . '❤';
                                    if ($pieces): ?>
                                    <span class="pill"><?= htmlspecialchars(implode(' · ', $pieces)) ?></span>
                                <?php endif; ?>
                            </div>
                            <h3><?= htmlspecialchars($aTitle) ?></h3>
                            <?php if ($aExcerpt): ?><p class="muted"><?= htmlspecialchars($aExcerpt) ?></p><?php endif; ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </section>
        </section>
    <?php elseif ($section['type'] === '__block' && !empty($section['_block']['view'])): ?>
        <?php
            $__blockData = $section['_block']['data'];
            $__blockView = $section['_block']['view'];
            if (file_exists($__blockView)) {
                include $__blockView;
            }
        ?>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($home['custom_blocks']) && is_array($home['custom_blocks'])): ?>
    <section class="block home-section-padding <?= $layoutClass ?>">
        <div class="block-head">
            <h2><?= htmlspecialchars($customBlocksTitle) ?></h2>
        </div>
        <div class="cards">
            <?php foreach ($home['custom_blocks'] as $blk): ?>
                <div class="card-tile">
                    <?php if (!empty($blk['icon'])): ?><p class="eyebrow"><?= htmlspecialchars($blk['icon']) ?></p><?php endif; ?>
                    <?php if (!empty($blk['title'])): ?><h3><?= htmlspecialchars($blk['title']) ?></h3><?php endif; ?>
                    <?php if (!empty($blk['text'])): ?><p class="muted"><?= htmlspecialchars($blk['text']) ?></p><?php endif; ?>
                    <?php if (!empty($blk['link'])): ?><a class="btn ghost small" href="<?= htmlspecialchars($blk['link']) ?>"><?= htmlspecialchars($customBlockCta) ?></a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php if (($galleryMode ?? 'lightbox') === 'lightbox' && !empty($gallery)): ?>
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
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.like-chip').forEach(btn => {
        const id = btn.dataset.id;
        const key = 'liked_gallery_' + id;
        if (localStorage.getItem(key) === '1') btn.classList.add('active');
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                const res = await fetch('/api/v1/like', {method:'POST', headers:{'Accept':'application/json'}, body: new URLSearchParams({type:'gallery', id})});
                if (!res.ok) throw new Error('bad');
                const data = await res.json();
                document.querySelectorAll('.like-chip[data-id="' + id + '"] .g-likes').forEach(el => el.textContent = data.likes ?? 0);
                btn.classList.add('active');
                localStorage.setItem(key, '1');
                if (window.showToast) window.showToast(data.already ? 'Уже лайкнули' : 'Лайк засчитан', 'success');
            } catch (_) {
                if (window.showToast) window.showToast('Не удалось поставить лайк', 'danger');
            }
        });
    });
});
</script>
<?php endif; ?>
