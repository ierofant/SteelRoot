<?php
    $tKey = $locale === 'ru' ? 'title_ru' : 'title_en';
    $dKey = $locale === 'ru' ? 'description_ru' : 'description_en';
    $showTitle = !empty($display['show_title']);
    $showDescription = !empty($display['show_description']);
    $showLikes = !empty($display['show_likes']);
    $showViews = !empty($display['show_views']);
    $showTags = !empty($display['show_tags']);
    $tags = $tags ?? [];
    $authorProfileUrl = $authorProfileUrl ?? (!empty($item['author_username']) ? '/users/' . urlencode($item['author_username']) : '/users/' . (int)($item['author_id'] ?? 0));
    $authorSignatureVisible = $authorSignatureVisible ?? false;
    $authorSignature = $authorSignature ?? '';
?>
<?php if ($showTitle && !empty($item[$tKey])): ?>
    <h2><?= htmlspecialchars($item[$tKey]) ?></h2>
<?php endif; ?>
<?php if ($showViews || $showLikes): ?>
    <p class="muted">
        <?php if ($showViews): ?>👁 <?= (int)($item['views'] ?? 0) ?><?php endif; ?>
        <?php if ($showViews && $showLikes): ?> · <?php endif; ?>
        <?php if ($showLikes): ?>❤ <span id="g-like-count"><?= (int)($item['likes'] ?? 0) ?></span><?php endif; ?>
    </p>
<?php endif; ?>
<?php if (!empty($item['author_name']) && !empty($item['author_id'])): ?>
    <div style="display:flex;align-items:center;gap:10px;margin:8px 0;">
        <?php
            $avatarStyle = "width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1c1f2d,#292f4f);background-size:cover;background-position:center;display:grid;place-items:center;color:#dce4ff;font-weight:700;border:1px solid rgba(255,255,255,0.08);";
            if (!empty($item['author_avatar'])) {
                $avatarStyle .= "background-image:url('" . htmlspecialchars($item['author_avatar']) . "')";
            }
        ?>
        <span style="<?= $avatarStyle ?>">
            <?= !empty($item['author_avatar']) ? '' : htmlspecialchars(strtoupper(substr($item['author_name'],0,1))) ?>
        </span>
        <a class="muted" href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($item['author_name']) ?></a>
    </div>
<?php endif; ?>
<div class="form-actions" style="flex-direction:row; align-items:center; gap:12px;">
    <?php if ($showLikes): ?>
        <button type="button" class="like-btn" id="like-gallery" data-id="<?= (int)$item['id'] ?>"><span>❤</span><span id="like-count-inline"><?= (int)($item['likes'] ?? 0) ?></span></button>
    <?php endif; ?>
    <a class="btn ghost" href="/gallery">Back to list</a>
</div>
<img src="<?= htmlspecialchars($item['path_medium'] ?? $item['path']) ?>" alt="<?= htmlspecialchars($item[$tKey] ?? 'Image') ?>" style="max-width:100%;">
<?php if ($showDescription && !empty($item[$dKey])): ?>
    <p><?= nl2br(htmlspecialchars($item[$dKey] ?? '')) ?></p>
<?php endif; ?>
<?php if (!empty($authorSignatureVisible) && $authorSignature !== ''): ?>
    <div class="muted author-signature"><?= htmlspecialchars($authorSignature) ?></div>
<?php endif; ?>
<?php if ($showTags && !empty($tags)): ?>
    <div class="tags">
        <?php foreach ($tags as $tag): ?>
            <a class="pill ghost" href="/tags/<?= urlencode($tag['slug'] ?? '') ?>"><?= htmlspecialchars($tag['name'] ?? ($tag['slug'] ?? '')) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($showLikes): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('like-gallery');
    if (!btn) return;
    const countEl = document.getElementById('like-count-inline');
    const storageKey = 'liked_gallery_' + btn.dataset.id;
    if (localStorage.getItem(storageKey) === '1') {
        btn.classList.add('active');
    }
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        try {
            const res = await fetch('/api/v1/like', {
                method: 'POST',
                headers: {'Accept':'application/json'},
                body: new URLSearchParams({type:'gallery', id})
            });
            if (!res.ok) throw new Error('bad');
            const data = await res.json();
            if (data.likes !== undefined) {
                countEl.textContent = data.likes;
            }
            btn.classList.add('active');
            localStorage.setItem(storageKey, '1');
            if (window.showToast) window.showToast(data.already ? 'Уже лайкали' : 'Лайк засчитан', 'success');
        } catch (e) {
            if (window.showToast) window.showToast('Не удалось поставить лайк', 'danger');
        }
    });
});
</script>
<?php endif; ?>
