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
<style>
    .gallery-author {display:flex;align-items:center;gap:8px;font-size:13px;color:#cfd6f3;margin-top:6px;}
    .gallery-author .avatar {width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1c1f2d,#292f4f);background-size:cover;background-position:center;display:grid;place-items:center;color:#dce4ff;font-weight:700;border:1px solid rgba(255,255,255,0.08);}
    .gallery-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;}
    .gallery-card {background:#0f1226;border:1px solid rgba(255,255,255,0.08);border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.35);display:flex;flex-direction:column;}
    .gallery-card .thumb {position:relative;padding-top:62%;background-size:cover;background-position:center;}
    .gallery-card .thumb .pill {position:absolute;top:10px;right:10px;background:rgba(0,0,0,0.55);padding:6px 10px;border-radius:999px;font-size:12px;color:#e3e8ff;}
    .gallery-card .thumb .like-chip {position:absolute;left:10px;top:10px;background:rgba(0,0,0,0.55);padding:6px 10px;border-radius:999px;font-size:12px;color:#e3e8ff;display:flex;align-items:center;gap:6px;}
    .gallery-card .body {padding:14px 14px 16px;display:flex;flex-direction:column;gap:10px;}
    .gallery-card h3 {margin:0;font-size:17px;color:#f2f4ff;}
    .gallery-card p {margin:0;color:#c1c7e6;font-size:14px;}
    .gallery-card .actions {display:flex;align-items:center;justify-content:space-between;}
    .gallery-card .actions a {color:#a0a9d8;font-weight:600;}
    .gallery-card .likes {display:flex;align-items:center;gap:8px;color:#c1c7e6;}
    .gallery-card .likes button {background:none;border:none;color:inherit;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px;}
    .gallery-card .likes button.active {color:#f57a9c;}
    @media(max-width:720px){.gallery-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));}}
</style>
<div class="gallery-grid">
    <?php foreach ($items as $item): ?>
        <?php
            $itemTitle = $locale === 'ru' ? ($item['title_ru'] ?? '') : ($item['title_en'] ?? '');
            $itemDesc = $locale === 'ru' ? ($item['description_ru'] ?? '') : ($item['description_en'] ?? '');
            if ($itemDesc === '') {
                $itemDesc = $locale === 'ru' ? ($item['description_en'] ?? '') : ($item['description_ru'] ?? '');
            }
            $likes = (int)($item['likes'] ?? 0);
            $views = (int)($item['views'] ?? 0);
            $authorName = $item['author_name'] ?? '';
            $authorId = (int)($item['author_id'] ?? 0);
            $authorAvatar = $item['author_avatar'] ?? '';
            $letter = strtoupper(substr($authorName ?: ($itemTitle ?: 'G'), 0, 1));
            $slug = $item['slug'] ?? null;
            $href = $slug ? '/gallery/photo/' . urlencode($slug) : '/gallery?id=' . (int)$item['id'];
        ?>
        <div class="gallery-card">
            <div class="thumb" style="background-image:url('<?= htmlspecialchars($item['path_medium'] ?? $item['path']) ?>')">
                <?php if ($views): ?><span class="pill"><?= $views ?>👁</span><?php endif; ?>
                <?php if ($likes || true): ?>
                    <button class="like-chip" data-id="<?= (int)$item['id'] ?>" data-likes="<?= $likes ?>">
                        <span class="g-like-btn">❤</span>
                        <span class="g-likes"><?= $likes ?></span>
                    </button>
                <?php endif; ?>
            </div>
            <div class="body">
                <div class="gallery-author">
                    <span class="avatar" style="<?= $authorAvatar ? "background-image:url('".htmlspecialchars($authorAvatar)."')" : '' ?>">
                        <?= $authorAvatar ? '' : htmlspecialchars($letter) ?>
                    </span>
                    <span><?= htmlspecialchars($authorName ?: 'Anon') ?></span>
                </div>
                <h3><?= htmlspecialchars($itemTitle ?: 'Image') ?></h3>
                <p><?= htmlspecialchars($itemDesc ?: 'Gallery item') ?></p>
                <div class="actions">
                    <a href="<?= htmlspecialchars($href) ?>"><?= __('gallery.action.view') ?></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
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
