<?php
    $isRu = ($locale ?? 'en') === 'ru';
    $title = trim($isRu ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? ''));
    $altTitle = trim(!$isRu ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? ''));
    if ($title === '') {
        $title = $altTitle;
    }
    $body = $isRu ? ($article['body_ru'] ?? '') : ($article['body_en'] ?? '');
    if ($body === '' || $body === null) {
        $body = !$isRu ? ($article['body_ru'] ?? '') : ($article['body_en'] ?? '');
    }
    $body = (string)$body;
    $bodyHtml = $body;
    if ($bodyHtml === '' || strip_tags($bodyHtml) === $bodyHtml) {
        $bodyHtml = nl2br(htmlspecialchars($bodyHtml));
    }
    $display = $display ?? [
        'show_author' => true,
        'show_date' => true,
        'show_likes' => true,
        'show_views' => true,
        'show_tags' => true,
    ];
    $authorProfileUrl = $authorProfileUrl ?? (!empty($article['author_username']) ? '/users/' . urlencode($article['author_username']) : '/users/' . (int)($article['author_id'] ?? 0));
    $authorSignatureVisible = $authorSignatureVisible ?? false;
    $authorSignature = $authorSignature ?? '';
?>
<article class="article-view">
    <header>
        <?php if (!empty($display['show_date'])): ?>
            <p class="eyebrow"><?= !empty($article['created_at']) ? htmlspecialchars(date('d.m.Y', strtotime($article['created_at']))) : '' ?></p>
        <?php endif; ?>
        <h1><?= htmlspecialchars($title) ?></h1>
        <?php
            $metaParts = [];
            if (!empty($display['show_views'])) {
                $metaParts[] = (int)($article['views'] ?? 0) . '👁';
            }
            if (!empty($display['show_likes'])) {
                $metaParts[] = (int)($article['likes'] ?? 0) . '❤';
            }
        ?>
        <?php if ($metaParts): ?>
            <div class="muted article-meta-line"><?= htmlspecialchars(implode(' · ', $metaParts)) ?></div>
        <?php endif; ?>
        <?php if (!empty($display['show_author']) && !empty($article['author_name']) && !empty($article['author_id'])): ?>
            <div class="article-author-row">
                <span class="article-author-avatar">
                    <?php if (!empty($article['author_avatar'])): ?>
                        <img src="<?= htmlspecialchars($article['author_avatar']) ?>" alt="<?= htmlspecialchars($article['author_name']) ?>">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($article['author_name'],0,1))) ?>
                    <?php endif; ?>
                </span>
                <a class="muted" href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($article['author_name']) ?></a>
            </div>
        <?php endif; ?>
    </header>
    <?php $coverSrc = $article['cover_url'] ?? $article['image_url'] ?? ''; ?>
    <?php if ($coverSrc !== ''): ?>
        <div class="article-cover">
            <img src="<?= htmlspecialchars($coverSrc) ?>" alt="<?= htmlspecialchars($title) ?>">
        </div>
    <?php endif; ?>
    <div class="article-body"><?= $bodyHtml ?></div>
    <?php if (!empty($authorSignatureVisible) && $authorSignature !== ''): ?>
        <div class="muted author-signature"><?= htmlspecialchars($authorSignature) ?></div>
    <?php endif; ?>
    <?php if (!empty($display['show_tags']) && !empty($tags)): ?>
        <div class="article-tags">
            <?php foreach ($tags as $tag): ?>
                <a class="pill" href="/tags/<?= urlencode($tag['slug']) ?>"><?= htmlspecialchars($tag['name']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="form-actions article-actions-row">
        <?php if (!empty($display['show_likes'])): ?>
            <button type="button" class="like-btn" id="like-article" data-id="<?= (int)$article['id'] ?>"><span>❤</span><span id="like-count"><?= (int)($article['likes'] ?? 0) ?></span></button>
        <?php endif; ?>
        <a class="btn ghost" href="/articles">← <?= __('articles.action.back') ?></a>
    </div>
</article>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('like-article');
    if (!btn) return;
    const countEl = document.getElementById('like-count');
    const storageKey = 'liked_article_' + btn.dataset.id;
    if (localStorage.getItem(storageKey) === '1') {
        btn.classList.add('active');
    }
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        try {
            const res = await fetch('/api/v1/like', {
                method: 'POST',
                headers: {'Accept':'application/json'},
                body: new URLSearchParams({type:'article', id})
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
