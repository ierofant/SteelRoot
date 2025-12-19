<div class="gallery-hero">
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? __('gallery.title')) ?></p>
        <h2><?= htmlspecialchars($title ?? __('gallery.title')) ?></h2>
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
        <a class="masonry-item<?= $lightbox ? ' lightbox-trigger' : '' ?>" href="<?= htmlspecialchars($href) ?>" data-id="<?= (int)$item['id'] ?>" <?= $lightbox ? 'data-index="'.(int)$idx.'" data-full="'.htmlspecialchars($dataFull).'" data-title="'.htmlspecialchars($itemTitle).'"' : '' ?>>
            <div class="frame">
                <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($itemTitle) ?>">
                <div class="meta-floating">
                    <?php if ($views): ?><span>👁 <span class="g-views"><?= $views ?></span></span><?php endif; ?>
                    <button class="like-chip" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">❤ <span class="g-likes"><?= $likes ?></span></button>
                </div>
                <?php if ($itemTitle): ?><div class="caption"><?= htmlspecialchars($itemTitle) ?></div><?php endif; ?>
            </div>
        </a>
<?php endforeach; ?>
</div>
<?php if (($openMode ?? 'lightbox') === 'lightbox' && !empty($items)): ?>
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
