<?php ob_start(); ?>
<div class="gallery-hero">
    <div>
        <p class="eyebrow"><?= __('gallery.title') ?></p>
        <h2><?= __('gallery.title') ?></h2>
    </div>
    <form method="get" action="/gallery" class="gallery-filter">
        <input type="text" name="tag" value="<?= htmlspecialchars($tag ?? '') ?>" placeholder="Искать по тегу">
        <input type="text" name="cat" value="<?= htmlspecialchars($category ?? '') ?>" placeholder="Категория">
        <select name="sort">
            <option value="new" <?= ($sort ?? 'new') === 'new' ? 'selected' : '' ?>>По новизне</option>
            <option value="likes" <?= ($sort ?? 'new') === 'likes' ? 'selected' : '' ?>>По лайкам</option>
            <option value="views" <?= ($sort ?? 'new') === 'views' ? 'selected' : '' ?>>По просмотрам</option>
        </select>
        <button type="submit" class="btn ghost">Применить</button>
    </form>
</div>
<style>
    .gallery-author {display:flex;align-items:center;gap:8px;font-size:13px;color:#cfd6f3;margin-top:6px;}
    .gallery-author .avatar {width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1c1f2d,#292f4f);background-size:cover;background-position:center;display:grid;place-items:center;color:#dce4ff;font-weight:700;border:1px solid rgba(255,255,255,0.08);}
</style>

<div class="masonry" id="gallery-grid">
    <?php foreach ($items as $idx => $item): ?>
        <?php $thumb = $item['path_thumb'] ?? $item['path']; ?>
        <?php $full = $item['path_medium'] ?? $item['path']; ?>
        <?php $tKey = $locale === 'ru' ? 'title_ru' : 'title_en'; ?>
        <?php $title = $item[$tKey] ?? ''; ?>
        <?php $slug = $item['slug'] ?? null; ?>
        <?php $views = (int)($item['views'] ?? 0); ?>
        <?php $likes = (int)($item['likes'] ?? 0); ?>
        <?php $cat = $item['category'] ?? ''; ?>
        <?php $href = ($openMode ?? 'lightbox') === 'page'
            ? ($slug ? '/gallery/photo/' . urlencode($slug) : '/gallery/view?id=' . (int)$item['id'])
            : $full; ?>
        <?php $authorName = $item['author_name'] ?? ''; ?>
        <?php $authorId = (int)($item['author_id'] ?? 0); ?>
        <?php $authorAvatar = $item['author_avatar'] ?? ''; ?>
        <?php $letter = strtoupper(substr($authorName ?: ($title ?: 'A'), 0, 1)); ?>
        <a class="masonry-item <?= ($openMode ?? 'lightbox') === 'lightbox' ? 'lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>" <?= ($openMode ?? 'lightbox') === 'lightbox' ? 'data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($full).'" data-title="'.htmlspecialchars($title).'"'.(!empty($display['show_likes']) ? ' data-likes="'.$likes.'"' : '') : '' ?>>
            <div class="frame">
                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($title) ?>">
                <?php if (!empty($display['show_views']) || !empty($display['show_likes'])): ?>
                    <div class="meta-floating">
                        <?php if (!empty($display['show_views'])): ?>
                            <span><?= $cat ? htmlspecialchars($cat) . ' • ' : '' ?>👁 <span class="g-views"><?= $views ?></span></span>
                        <?php endif; ?>
                        <?php if (!empty($display['show_likes'])): ?>
                            <button type="button" class="like-chip" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes"><?= $likes ?></span></button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($display['show_title']) && $title): ?><div class="caption"><?= htmlspecialchars($title) ?></div><?php endif; ?>
                <?php if ($authorName && $authorId > 0): ?>
                    <div class="gallery-author">
                        <span class="avatar" style="<?= $authorAvatar ? "background-image:url('".htmlspecialchars($authorAvatar)."')" : '' ?>"><?= $authorAvatar ? '' : htmlspecialchars($letter) ?></span>
                        <a href="/users/<?= $authorId ?>" class="muted"><?= htmlspecialchars($authorName) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php $pages = (int)ceil($total / $perPage); ?>
<?php if ($pages > 1): ?>
    <div class="gallery-pages">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="btn ghost small <?= $i === (int)($page ?? 1) ? 'active' : '' ?>" href="/gallery?page=<?= $i ?><?= $tag ? '&tag=' . urlencode($tag) : '' ?><?= $category ? '&cat=' . urlencode($category) : '' ?><?= $sort ? '&sort=' . urlencode($sort) : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($display['enable_lightbox'])): ?>
<div class="lightbox" id="lightbox" hidden>
    <div class="lightbox__backdrop"></div>
    <div class="lightbox__dialog">
        <button class="lightbox__close" aria-label="Закрыть">×</button>
        <button class="lightbox__nav lightbox__prev" aria-label="Предыдущее">‹</button>
        <button class="lightbox__nav lightbox__next" aria-label="Следующее">›</button>
        <img src="" alt="" id="lightbox-image">
        <?php if (!empty($display['show_likes']) && !empty($display['lightbox_likes'])): ?>
            <button class="like-chip" id="lightbox-like" data-id="" style="margin-top:8px;">❤ <span id="lightbox-like-count">0</span></button>
        <?php endif; ?>
        <p class="lightbox__caption" id="lightbox-caption"></p>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-image');
    const cap = document.getElementById('lightbox-caption');
    const items = Array.from(document.querySelectorAll('.lightbox-trigger'));
    const likeBtn = document.getElementById('lightbox-like');
    const likeCount = document.getElementById('lightbox-like-count');
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
        const src = link.dataset.full;
        const title = link.dataset.title || '';
        img.src = src;
        img.alt = title;
        cap.textContent = title;
        if (likeBtn && likeCount) {
            const likes = link.dataset.likes || '0';
            const id = link.dataset.id;
            likeCount.textContent = likes;
            likeBtn.dataset.id = id;
            const storageKey = 'liked_gallery_' + id;
            likeBtn.classList.toggle('active', localStorage.getItem(storageKey) === '1');
        }
        box.hidden = false;
        document.body.classList.add('no-scroll');
        const id = link.closest('a')?.dataset.id;
        if (id) sendView(id);
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
    box?.querySelector('.lightbox__prev')?.addEventListener('click', (e) => { e.preventDefault(); prev(); });
    box?.querySelector('.lightbox__next')?.addEventListener('click', (e) => { e.preventDefault(); next(); });
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
    box?.addEventListener('touchmove', (e) => {
        // prevent scroll while swiping horizontally
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
    // Likes in grid and lightbox
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
                if (likeCount && likeBtn && likeBtn.dataset.id === id) likeCount.textContent = likes;
                btn.classList.add('active');
                localStorage.setItem(storageKey, '1');
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
<?php $content = ob_get_clean(); include APP_ROOT . '/app/views/layout.php'; ?>
