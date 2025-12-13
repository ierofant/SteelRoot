<?php
ob_start();
$q = $query ?? '';
$articles = $results['articles'] ?? [];
$gallery = $results['gallery'] ?? [];
$tags = $results['tags'] ?? [];
$loc = $locale ?? 'en';
?>
<section class="search-hero">
    <div>
        <p class="eyebrow">Поиск</p>
        <h1>Найдите нужное</h1>
        <p class="muted">Статьи и галерея по вашему запросу.</p>
    </div>
    <?php $sourcesSel = $selectedSources ?? ['articles','gallery','tags']; ?>
    <form method="get" action="/search" class="search-box" style="flex-wrap:wrap;gap:10px;">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Введите запрос" style="flex:1;min-width:200px;">
        <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
            <label class="pill" style="display:inline-flex;gap:6px;align-items:center;cursor:pointer;padding:8px 10px;font-size:13px;">
                <input type="checkbox" name="sources[]" value="articles" <?= in_array('articles',$sourcesSel,true)?'checked':'' ?> style="margin:0;"> Статьи
            </label>
            <label class="pill" style="display:inline-flex;gap:6px;align-items:center;cursor:pointer;padding:8px 10px;font-size:13px;">
                <input type="checkbox" name="sources[]" value="gallery" <?= in_array('gallery',$sourcesSel,true)?'checked':'' ?> style="margin:0;"> Галерея
            </label>
            <label class="pill" style="display:inline-flex;gap:6px;align-items:center;cursor:pointer;padding:8px 10px;font-size:13px;">
                <input type="checkbox" name="sources[]" value="tags" <?= in_array('tags',$sourcesSel,true)?'checked':'' ?> style="margin:0;"> Теги
            </label>
        </div>
        <button type="submit" class="btn primary" style="white-space:nowrap;">Искать</button>
    </form>
</section>

<?php if ($q !== ''): ?>
<section class="search-results">
    <div class="results-block">
        <div class="block-head">
            <h2>Статьи</h2>
            <span class="pill"><?= count($articles) ?></span>
        </div>
        <div class="cards">
            <?php foreach ($articles as $a): ?>
                <?php $title = $loc === 'ru' ? ($a['title_ru'] ?? '') : ($a['title_en'] ?? ''); ?>
                <?php $href = !empty($a['url']) ? $a['url'] : '/articles/' . urlencode($a['slug']); ?>
                <a class="card-tile" href="<?= htmlspecialchars($href) ?>">
                    <p class="eyebrow"><?= htmlspecialchars($a['created_at'] ?? '') ?></p>
                    <h3><?= htmlspecialchars($title) ?></h3>
                    <?php if (!empty($a['preview_en']) || !empty($a['preview_ru'])): ?>
                        <p class="muted"><?= htmlspecialchars($loc === 'ru' ? ($a['preview_ru'] ?? '') : ($a['preview_en'] ?? '')) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (empty($articles)): ?>
                <div class="empty">Нет статей</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="results-block">
        <div class="block-head">
            <h2>Галерея</h2>
            <span class="pill"><?= count($gallery) ?></span>
        </div>
        <div class="masonry">
            <?php foreach ($gallery as $idx => $g): ?>
                <?php $tKey = $loc === 'ru' ? 'title_ru' : 'title_en'; ?>
                <?php $title = $g[$tKey] ?? ''; ?>
                <?php $slug = $g['slug'] ?? null; ?>
                <?php $href = !empty($g['url'])
                    ? $g['url']
                    : (($galleryMode ?? 'lightbox') === 'page'
                        ? ($slug ? '/gallery/photo/' . urlencode($slug) : '/gallery/view?id=' . (int)($g['id'] ?? 0))
                        : ($g['path_medium'] ?? $g['path_thumb'])); ?>
                <a class="masonry-item <?= ($galleryMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" <?= ($galleryMode ?? 'lightbox') === 'lightbox' ? 'data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($g['path_medium'] ?? $g['path_thumb']).'" data-title="'.htmlspecialchars($title).'"' : '' ?>>
                    <img src="<?= htmlspecialchars($g['path_thumb'] ?? $g['path_medium'] ?? '') ?>" alt="<?= htmlspecialchars($title) ?>">
                    <?php if ($title): ?><div class="caption"><?= htmlspecialchars($title) ?></div><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if (empty($gallery)): ?>
                <div class="empty">Нет изображений</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($tags)): ?>
    <div class="results-block">
        <div class="block-head">
            <h2>Теги</h2>
            <span class="pill"><?= count($tags) ?></span>
        </div>
        <div class="link-list">
            <?php foreach ($tags as $t): ?>
                <a class="link" href="<?= htmlspecialchars($t['url'] ?? ('/tags/' . $t['slug'])) ?>"><?= htmlspecialchars($t['name'] ?? $t['slug']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
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
});
</script>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
