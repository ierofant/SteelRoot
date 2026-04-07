<?php
use App\Services\PublicImageInfoService;
?>
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
    // Добавляем класс bd-postheader-3 ко всем <h4> в теле статьи
    $bodyHtml = preg_replace_callback(
        '/<h4(\s[^>]*)?>/i',
        static function (array $m): string {
            $attrs = $m[1] ?? '';
            if (preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/', $attrs)) {
                $attrs = preg_replace(
                    '/\bclass\s*=\s*["\']([^"\']*)["\']/',
                    'class="$1 bd-postheader-3"',
                    $attrs
                );
            } else {
                $attrs .= ' class="bd-postheader-3"';
            }
            return '<h4' . $attrs . '>';
        },
        $bodyHtml
    );

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
<?php
$coverSrc = trim((string)($article['cover_url'] ?? ''));
if ($coverSrc === '') {
    $coverSrc = trim((string)($article['image_url'] ?? ''));
}
$coverDims = PublicImageInfoService::dimensions($coverSrc);
?>
<article class="article-view">

    <!-- ═══ HERO ═══ -->
    <div class="article-hero <?= $coverSrc ? 'has-cover' : 'no-cover' ?>">
        <?php if ($coverSrc): ?>
            <div class="article-hero-bg">
                <img
                    src="<?= htmlspecialchars($coverSrc) ?>"
                    alt="<?= htmlspecialchars($title) ?>"
                    loading="eager"
                    fetchpriority="high"
                    decoding="async"
                    <?= $coverDims ? ('width="' . (int)$coverDims['width'] . '" height="' . (int)$coverDims['height'] . '"') : '' ?>
                >
            </div>
        <?php endif; ?>
        <div class="article-hero-content">
            <?php if (!empty($display['show_date']) && !empty($article['created_at'])): ?>
                <p class="eyebrow"><?= htmlspecialchars(date('d.m.Y', strtotime($article['created_at']))) ?></p>
            <?php endif; ?>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="article-hero-meta">
                <?php if (!empty($display['show_views'])): ?>
                    <span><?= (int)($article['views'] ?? 0) ?> просм.</span>
                <?php endif; ?>
                <?php if (!empty($display['show_likes'])): ?>
                    <span><?= (int)($article['likes'] ?? 0) ?> ❤</span>
                <?php endif; ?>
                <?php if (!empty($display['show_author']) && !empty($article['author_name'])): ?>
                    <span class="article-hero-author">
                        <span class="article-author-avatar">
                            <?php if (!empty($article['author_avatar'])): ?>
                                <img src="<?= htmlspecialchars($article['author_avatar']) ?>" alt="<?= htmlspecialchars($article['author_name']) ?>">
                            <?php else: ?>
                                <?= htmlspecialchars(function_exists('mb_strtoupper') && function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)$article['author_name'], 0, 1)) : strtoupper(substr((string)$article['author_name'], 0, 1))) ?>
                            <?php endif; ?>
                        </span>
                        <a href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($article['author_name']) ?></a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══ BODY ═══ -->
    <div class="article-content-wrap">
        <div class="article-body"><?= $bodyHtml ?></div>

        <?php if (!empty($display['show_tags']) && !empty($tags)): ?>
            <div class="article-tags">
                <?php foreach ($tags as $tag): ?>
                    <a class="pill" href="/tags/<?= urlencode($tag['slug']) ?>"><?= htmlspecialchars($tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="article-actions">
            <?php if (!empty($display['show_likes'])): ?>
                <button type="button" class="like-btn" id="like-article" data-like-type="article" data-id="<?= (int)$article['id'] ?>">
                    <span>❤</span><span id="like-count" data-like-count><?= (int)($article['likes'] ?? 0) ?></span>
                </button>
            <?php endif; ?>
            <a class="btn ghost" href="/articles">← <?= __('articles.action.back') ?></a>
        </div>

        <?= $commentsHtml ?? '' ?>
    </div>

</article>
