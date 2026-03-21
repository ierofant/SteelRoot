<?php
$loc          = $locale ?? 'en';
$tagNameClean = ltrim($tagName ?? $slug ?? '', "# \t\n\r\0\x0B");
$aPage        = $aPage   ?? 1;
$gPage        = $gPage   ?? 1;
$aTotal       = $aTotal  ?? count($articles ?? []);
$gTotal       = $gTotal  ?? count($gallery  ?? []);
$perPage      = $perPage ?? 12;
$tagBase      = $tagBase ?? ('/tags/' . rawurlencode($slug ?? ''));
$articleCount = $aTotal;
$galleryCount = $gTotal;
?>
<section class="tag-hero">
    <div class="tag-hero__bg"></div>
    <div class="tag-hero__grid">
        <div class="tag-hero__text">
            <p class="eyebrow">Тег</p>
            <h1>#<?= htmlspecialchars($tagNameClean) ?></h1>
            <p class="muted">Материалы и изображения с этим тегом.</p>
            <div class="tag-hero__chips">
                <span class="stat-pill"><span class="dot dot-green"></span><?= $articleCount ?> статей</span>
                <span class="stat-pill"><span class="dot dot-blue"></span><?= $galleryCount ?> изображений</span>
            </div>
        </div>
        <div class="tag-hero__card">
            <div class="card-row">
                <div>
                    <p class="eyebrow">Тренд</p>
                    <strong><?= htmlspecialchars(ucfirst($tagName ?? $slug ?? '')) ?></strong>
                </div>
                <div class="pulse-badge">live</div>
            </div>
            <p class="muted">Собрали лучшие материалы, чтобы не потеряться в потоке.</p>
            <div class="tag-hero__cta">
                <a class="btn primary" href="#articles">Статьи</a>
                <a class="btn ghost" href="#gallery">Галерея</a>
            </div>
        </div>
    </div>
</section>

<section class="tag-section" id="articles">
    <div class="tag-section__head">
        <div>
            <p class="eyebrow">Контент</p>
            <h2>Статьи по тегу</h2>
        </div>
        <span class="pill"><?= $articleCount ?></span>
    </div>
    <div class="articles-grid tag-articles">
        <?php foreach ($articles as $a): ?>
            <?php $title = $loc === 'ru' ? ($a['title_ru'] ?? '') : ($a['title_en'] ?? ''); ?>
            <a class="article-card no-image tag-article-card" href="/articles/<?= urlencode($a['slug'] ?? '') ?>">
                <div class="card-meta">
                    <span class="eyebrow"><?= !empty($a['created_at']) ? htmlspecialchars(date('d.m.Y', strtotime($a['created_at']))) : '' ?></span>
                    <span class="pill subtle">#<?= htmlspecialchars($tagNameClean) ?></span>
                </div>
                <h3><?= htmlspecialchars($title) ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($articles)): ?>
        <div class="empty-state">
            <h3>Пока нет статей</h3>
            <p class="muted">Добавьте материалы с этим тегом — они появятся здесь.</p>
        </div>
    <?php endif; ?>
    <?php
    $paginationPage    = $aPage;
    $paginationTotal   = $aTotal;
    $paginationPerPage = $perPage;
    $paginationBase    = $tagBase . '?gp=' . $gPage;
    $paginationParam   = 'ap';
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</section>

<section class="tag-section" id="gallery">
    <div class="tag-section__head">
        <div>
            <p class="eyebrow">Визуалы</p>
            <h2>Галерея</h2>
        </div>
        <span class="pill"><?= $galleryCount ?></span>
    </div>
    <div class="masonry tag-gallery" id="gallery-grid">
        <?php foreach ($gallery as $idx => $item): ?>
            <?php $thumb = $item['path_thumb'] ?? $item['path']; ?>
            <?php $full = $item['path_medium'] ?? $item['path']; ?>
            <?php $tKey = $loc === 'ru' ? 'title_ru' : 'title_en'; ?>
            <?php $title = $item[$tKey] ?? ''; ?>
            <?php $slugG = $item['slug'] ?? null; ?>
            <?php $views = (int)($item['views'] ?? 0); ?>
            <?php $likes = (int)($item['likes'] ?? 0); ?>
            <?php $href = ($openMode ?? 'lightbox') === 'page'
                ? ($slugG ? '/gallery/photo/' . urlencode($slugG) : '/gallery/view?id=' . (int)$item['id'])
                : $full; ?>
            <a class="masonry-item <?= ($openMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>"<?= $slugG ? ' data-slug="'.htmlspecialchars($slugG).'"' : '' ?><?= ($openMode ?? 'lightbox') === 'lightbox' ? ' data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($full).'" data-title="'.htmlspecialchars($title).'" data-likes="'.$likes.'" data-views="'.$views.'"' : '' ?>>
                <div class="frame">
                    <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>">
                    <div class="meta-floating">
                        <span>👁 <span class="g-views"><?= $views ?></span></span>
                        <button type="button" class="like-chip" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes"><?= $likes ?></span></button>
                    </div>
                    <?php if ($title): ?><div class="caption"><?= htmlspecialchars($title) ?></div><?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if (empty($gallery)): ?>
            <div class="empty-state">
                <h3>Нет изображений</h3>
                <p class="muted">Как только появятся фотографии с этим тегом, они появятся здесь.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $paginationPage    = $gPage;
    $paginationTotal   = $gTotal;
    $paginationPerPage = $perPage;
    $paginationBase    = $tagBase . '?ap=' . $aPage;
    $paginationParam   = 'gp';
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</section>

<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($gallery)): ?>
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
