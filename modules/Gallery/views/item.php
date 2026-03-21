<?php
    $tKey = $locale === 'ru' ? 'title_ru' : 'title_en';
    $dKey = $locale === 'ru' ? 'description_ru' : 'description_en';
    $showTitle       = !empty($display['show_title']);
    $showDescription = !empty($display['show_description']);
    $showLikes       = !empty($display['show_likes']);
    $showViews       = !empty($display['show_views']);
    $showTags        = !empty($display['show_tags']);
    $tags            = $tags ?? [];
    $authorProfileUrl = $authorProfileUrl ?? (!empty($item['author_username'])
        ? '/users/' . urlencode($item['author_username'])
        : '/users/' . (int)($item['author_id'] ?? 0));
    $authorSignatureVisible = $authorSignatureVisible ?? false;
    $authorSignature        = $authorSignature ?? '';
    $imgSrc  = htmlspecialchars($item['path_medium'] ?? $item['path'] ?? '');
    $imgAlt  = htmlspecialchars($item[$tKey] ?? 'Image');
?>
<div class="gallery-photo-hero">
    <div class="gallery-photo-bg" aria-hidden="true">
        <img src="<?= $imgSrc ?>" alt="">
    </div>
    <div class="gallery-photo-stage">
        <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>">
    </div>
</div>

<div class="gallery-photo-body">

    <?php if ($showTitle && !empty($item[$tKey])): ?>
    <div class="gallery-photo-headline">
        <h2><?= htmlspecialchars($item[$tKey]) ?></h2>
        <?php if ($showViews || $showLikes): ?>
        <div class="gallery-photo-stats">
            <?php if ($showViews): ?>
            <span class="gallery-stat">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="12" rx="11" ry="8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
                <span id="g-view-count"><?= (int)($item['views'] ?? 0) ?></span>
            </span>
            <?php endif; ?>
            <?php if ($showLikes): ?>
            <span class="gallery-stat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                <span id="g-like-count"><?= (int)($item['likes'] ?? 0) ?></span>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($item['author_name']) && !empty($item['author_id'])): ?>
    <div class="gallery-author-row">
        <?php $authorInitial = htmlspecialchars(strtoupper(substr($item['author_name'], 0, 1))); ?>
        <span class="gallery-author-avatar">
            <?php if (!empty($item['author_avatar'])): ?>
                <img src="<?= htmlspecialchars($item['author_avatar']) ?>" alt="<?= htmlspecialchars($item['author_name']) ?>">
            <?php else: ?>
                <?= $authorInitial ?>
            <?php endif; ?>
        </span>
        <a class="muted" href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($item['author_name']) ?></a>
    </div>
    <?php endif; ?>

    <?php if ($showDescription && !empty($item[$dKey])): ?>
    <p class="gallery-photo-desc"><?= nl2br(htmlspecialchars($item[$dKey])) ?></p>
    <?php endif; ?>

    <?php if (!empty($authorSignatureVisible) && $authorSignature !== ''): ?>
    <div class="muted author-signature"><?= htmlspecialchars($authorSignature) ?></div>
    <?php endif; ?>

    <?php if ($showTags && !empty($tags)): ?>
    <div class="tags">
        <?php foreach ($tags as $tag): ?>
            <a class="pill ghost" href="/tags/<?= urlencode($tag['slug'] ?? '') ?>">#<?= htmlspecialchars(ltrim($tag['name'] ?? $tag['slug'] ?? '', "# \t\n\r\0\x0B")) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="gallery-photo-actions">
        <?php if ($showLikes): ?>
        <button type="button" class="like-btn gallery-like-btn" id="like-gallery" data-id="<?= (int)$item['id'] ?>">
            <svg class="like-btn-heart" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            <span id="like-count-inline"><?= (int)($item['likes'] ?? 0) ?></span>
        </button>
        <?php endif; ?>
        <a class="btn ghost" href="/gallery">← Галерея</a>
    </div>

</div>
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
                headers: {'Accept': 'application/json'},
                body: new URLSearchParams({type: 'gallery', id})
            });
            if (!res.ok) throw new Error('bad');
            const data = await res.json();
            if (data.likes !== undefined && countEl) {
                countEl.textContent = data.likes;
                const globalCount = document.getElementById('g-like-count');
                if (globalCount) globalCount.textContent = data.likes;
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
