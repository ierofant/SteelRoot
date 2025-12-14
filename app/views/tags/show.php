<?php
$loc = $locale ?? 'en';
ob_start();
$articleCount = count($articles ?? []);
$galleryCount = count($gallery ?? []);
?>
<section class="tag-hero">
    <div class="tag-hero__bg"></div>
    <div class="tag-hero__grid">
        <div class="tag-hero__text">
            <p class="eyebrow">Тег</p>
            <h1>#<?= htmlspecialchars($tagName ?? $slug ?? '') ?></h1>
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
                    <span class="pill subtle">#<?= htmlspecialchars($tagName ?? $slug ?? '') ?></span>
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
            <a class="masonry-item <?= ($openMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>" <?= ($openMode ?? 'lightbox') === 'lightbox' ? 'data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($full).'" data-title="'.htmlspecialchars($title).'"' : '' ?>>
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
</section>

<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($gallery)): ?>
<div class="lightbox" id="lightbox" hidden>
    <div class="lightbox__backdrop"></div>
    <div class="lightbox__dialog">
        <button class="lightbox__close" aria-label="Закрыть">×</button>
        <button class="lightbox__nav lightbox__prev" aria-label="Предыдущее">‹</button>
        <button class="lightbox__nav lightbox__next" aria-label="Следующее">›</button>
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
    const viewed = new Set();
    async function sendView(id) {
        if (!id || viewed.has(id)) return;
        viewed.add(id);
        try {
            const res = await fetch('/api/v1/view', {
                method: 'POST',
                headers: {'Accept':'application/json'},
                body: new URLSearchParams({type:'gallery', id})
            });
            if (res.ok) {
                const data = await res.json();
                document.querySelectorAll(`a[data-id="${id}"] .g-views`).forEach(el => { if (data.views !== undefined) el.textContent = data.views; });
            }
        } catch (_) {}
    }
    function openAt(idx) {
        const link = items[idx];
        if (!link) return;
        current = idx;
        img.src = link.dataset.full;
        img.alt = link.dataset.title || '';
        cap.textContent = link.dataset.title || '';
        box.hidden = false;
        document.body.classList.add('no-scroll');
        const id = link.closest('a')?.dataset.id;
        if (id) sendView(id);
    }
    function close() {
        box.hidden = true;
        img.src = '';
        cap.textContent = '';
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
    box?.querySelector('.lightbox__prev')?.addEventListener('click', (e) => { e.preventDefault(); prev(); });
    box?.querySelector('.lightbox__next')?.addEventListener('click', (e) => { e.preventDefault(); next(); });
    items.forEach((link, i) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            openAt(i);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !box.hidden) close();
        if (e.key === 'ArrowRight' && !box.hidden) next();
        if (e.key === 'ArrowLeft' && !box.hidden) prev();
    });
    let touchStartX = 0, touchStartY = 0;
    box?.addEventListener('touchstart', (e) => {
        const t = e.changedTouches[0];
        touchStartX = t.clientX;
        touchStartY = t.clientY;
    }, { passive: true });
    box?.addEventListener('touchmove', (e) => {
        if (Math.abs((e.changedTouches[0].clientX - touchStartX)) > Math.abs((e.changedTouches[0].clientY - touchStartY))) {
            e.preventDefault();
        }
    }, { passive: false });
    box?.addEventListener('touchend', (e) => {
        const t = e.changedTouches[0];
        const dx = t.clientX - touchStartX;
        const dy = t.clientY - touchStartY;
        if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
            if (dx < 0) next(); else prev();
        }
    });
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
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
