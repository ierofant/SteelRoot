<?php
$home = $home ?? [];
$gallery = $gallery ?? [];
$articles = $articles ?? [];
$sections = $sections ?? [];
$loc = $locale ?? 'en';
$layoutClass = ($home['layout_mode'] ?? 'wide') === 'boxed' ? 'layout-boxed' : '';
$sectionPadding = (int)($home['section_padding'] ?? 80);
ob_start();
?>
<?php if (!empty($home['custom_css'])): ?>
    <style><?= $home['custom_css'] ?></style>
<?php endif; ?>

<section class="hero enhanced <?= $layoutClass ?>" style="<?= !empty($home['hero_background']) ? 'background:' . htmlspecialchars($home['hero_background']) . ';' : '' ?><?= isset($home['hero_overlay']) ? '--hero-overlay:' . max(0, min(1, (float)$home['hero_overlay'])) . ';' : '' ?><?= isset($home['hero_align']) ? '--hero-align:' . htmlspecialchars($home['hero_align']) . ';' : '' ?>">
    <div class="hero-copy">
        <?php if (!empty($home['hero_badge'])): ?><span class="pill"><?= htmlspecialchars($home['hero_badge']) ?></span><?php endif; ?>
        <p class="eyebrow">Главная</p>
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
                <span>Галерея</span>
                <strong><?= count($gallery) ?></strong>
            </div>
            <div class="stat">
                <span>Статьи</span>
                <strong><?= count($articles) ?></strong>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php foreach ($sections as $section): ?>
    <?php if ($section['type'] === 'gallery' && $gallery): ?>
        <section class="block <?= $layoutClass ?>" style="padding-top: <?= $sectionPadding ?>px; padding-bottom: <?= $sectionPadding ?>px;">
            <div class="block-head">
                <h2>Галерея</h2>
                <a class="link" href="/gallery">Смотреть все →</a>
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
                    <a class="masonry-item <?= ($galleryMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$g['id'] ?>" <?= ($galleryMode ?? 'lightbox') === 'lightbox' ? 'data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($g['path_medium'] ?? $g['path']).'" data-title="'.htmlspecialchars($title).'"' : '' ?>>
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
        <section class="block <?= $layoutClass ?>" style="padding-top: <?= $sectionPadding ?>px; padding-bottom: <?= $sectionPadding ?>px;">
            <div class="block-head">
                <h2>Статьи</h2>
                <a class="link" href="/articles">Все статьи →</a>
            </div>
            <div class="cards">
                <?php foreach ($articles as $a): ?>
                    <?php $tKey = $loc === 'ru' ? 'title_ru' : 'title_en'; ?>
                    <?php $views = (int)($a['views'] ?? 0); ?>
                    <?php $likes = (int)($a['likes'] ?? 0); ?>
                    <a class="card-tile" href="/articles/<?= urlencode($a['slug']) ?>">
                        <p class="eyebrow"><?= htmlspecialchars(date('d.m.Y', strtotime($a['created_at'] ?? 'now'))) ?></p>
                        <h3><?= htmlspecialchars($a[$tKey] ?? '') ?></h3>
                        <p class="muted">👁 <?= $views ?> · ❤ <?= $likes ?></p>
                        <?php if (!empty($a['image_url'])): ?>
                            <div class="tile-cover"><img src="<?= htmlspecialchars($a['image_url']) ?>" alt=""></div>
                        <?php endif; ?>
                        <?php if (!empty($a['preview_en']) || !empty($a['preview_ru'])): ?>
                            <p class="muted"><?= htmlspecialchars($loc === 'ru' ? ($a['preview_ru'] ?? '') : ($a['preview_en'] ?? '')) ?></p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>
<?php if (!empty($home['custom_blocks']) && is_array($home['custom_blocks'])): ?>
    <section class="block <?= $layoutClass ?>" style="padding-top: <?= $sectionPadding ?>px; padding-bottom: <?= $sectionPadding ?>px;">
        <div class="block-head">
            <h2>Кастомные блоки</h2>
        </div>
        <div class="cards">
            <?php foreach ($home['custom_blocks'] as $blk): ?>
                <div class="card-tile">
                    <?php if (!empty($blk['icon'])): ?><p class="eyebrow"><?= htmlspecialchars($blk['icon']) ?></p><?php endif; ?>
                    <?php if (!empty($blk['title'])): ?><h3><?= htmlspecialchars($blk['title']) ?></h3><?php endif; ?>
                    <?php if (!empty($blk['text'])): ?><p class="muted"><?= htmlspecialchars($blk['text']) ?></p><?php endif; ?>
                    <?php if (!empty($blk['link'])): ?><a class="btn ghost small" href="<?= htmlspecialchars($blk['link']) ?>">Подробнее</a><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php if (($galleryMode ?? 'lightbox') === 'lightbox' && !empty($gallery)): ?>
<div class="lightbox" id="lightbox" hidden>
    <div class="lightbox__backdrop"></div>
    <div class="lightbox__dialog">
        <button class="lightbox__close" aria-label="Закрыть">×</button>
        <img src="" alt="" id="lightbox-image">
        <p class="lightbox__caption" id="lightbox-caption"></p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-image');
    const cap = document.getElementById('lightbox-caption');
    const items = Array.from(document.querySelectorAll('.lightbox-trigger'));
    let current = -1;
    function openAt(idx) {
        const link = items[idx];
        if (!link) return;
        current = idx;
        const src = link.dataset.full;
        const title = link.dataset.title || '';
        img.src = src;
        img.alt = title;
        cap.textContent = title;
        box.hidden = false;
        document.body.classList.add('no-scroll');
    }
    function close() {
        box.hidden = true;
        img.src = '';
        current = -1;
        document.body.classList.remove('no-scroll');
    }
    function next() {
        if (!items.length) return;
        current = (current + 1) % items.length;
        openAt(current);
    }
    function prev() {
        if (!items.length) return;
        current = (current - 1 + items.length) % items.length;
        openAt(current);
    }
    box?.addEventListener('click', (e) => {
        if (e.target === box || e.target.classList.contains('lightbox__backdrop')) close();
    });
    box?.querySelector('.lightbox__close')?.addEventListener('click', close);
    items.forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openAt(items.indexOf(link));
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !box.hidden) close();
        if (e.key === 'ArrowRight' && !box.hidden) next();
        if (e.key === 'ArrowLeft' && !box.hidden) prev();
    });
    let touchStartX = 0;
    let touchStartY = 0;
    box?.addEventListener('touchstart', (e) => {
        const t = e.changedTouches[0];
        touchStartX = t.clientX;
        touchStartY = t.clientY;
    }, { passive: true });
    box?.addEventListener('touchend', (e) => {
        const t = e.changedTouches[0];
        const dx = t.clientX - touchStartX;
        const dy = t.clientY - touchStartY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            if (dx < 0) next(); else prev();
        }
    });
    // Likes inside home gallery
    document.querySelectorAll('.like-chip').forEach(btn => {
        const id = btn.dataset.id;
        const storageKey = 'liked_gallery_' + id;
        if (localStorage.getItem(storageKey) === '1') {
            btn.classList.add('active');
        }
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                const res = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {'Accept':'application/json'},
                    body: new URLSearchParams({type:'gallery', id})
                });
                if (!res.ok) throw new Error('bad');
                const data = await res.json();
                const likes = data.likes ?? parseInt(btn.dataset.likes || '0', 10);
                document.querySelectorAll(`.like-chip[data-id="${id}"] .g-likes`).forEach(el => el.textContent = likes);
                btn.classList.add('active');
                localStorage.setItem(storageKey, '1');
                if (window.showToast) window.showToast(data.already ? 'Уже лайкнули' : 'Лайк засчитан', 'success');
            } catch (_) {
                if (window.showToast) window.showToast('Не удалось поставить лайк', 'danger');
            }
        });
    });
});
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
