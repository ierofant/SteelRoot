<?php $loc = $locale ?? 'en'; ?>
<section class="articles-hero">
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? __('articles.title')) ?></p>
        <h1><?= htmlspecialchars($title ?? __('articles.title')) ?></h1>
        <p class="muted"><?= htmlspecialchars($description ?? __('articles.list.subtitle')) ?></p>
    </div>
</section>

<section class="articles-grid">
    <?php foreach ($articles as $article): ?>
        <?php
            $itemTitle = $loc === 'ru' ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? '');
            $date = !empty($article['created_at']) ? date('d.m.Y', strtotime($article['created_at'])) : '';
            $excerpt = $loc === 'ru' ? ($article['preview_ru'] ?? '') : ($article['preview_en'] ?? '');
            $views = $article['views'] ?? null;
            $likes = $article['likes'] ?? null;
            $bg = !empty($article['image_url']) ? "background-image:url('" . htmlspecialchars($article['image_url']) . "')" : '';
            $classes = 'article-card';
            if (!$bg) {
                $classes .= ' no-image';
            }
            $authorName = $article['author_name'] ?? '';
            $authorId = (int)($article['author_id'] ?? 0);
            $authorUsername = $article['author_username'] ?? '';
            $profileUrl = $authorUsername ? '/users/' . urlencode($authorUsername) : '/users/' . $authorId;
            $authorAvatar = $article['author_avatar'] ?? '';
            $letter = strtoupper(substr($authorName ?: ($itemTitle ?: 'A'), 0, 1));
        ?>
        <article class="<?= $classes ?>" <?= $bg ? "style=\"--article-bg: {$bg}\"" : '' ?>>
            <a class="article-card__link" href="/articles/<?= urlencode($article['slug']) ?>">
                <div class="card-meta article-card__meta">
                    <?php if (!empty($display['show_date']) && $date): ?>
                        <span class="eyebrow"><?= htmlspecialchars($date) ?></span>
                    <?php endif; ?>
                    <?php
                        $pieces = [];
                    if (!empty($display['show_views']) && $views !== null) {
                        $pieces[] = $views . '👁';
                    }
                    if (!empty($display['show_likes']) && $likes !== null) {
                        $pieces[] = $likes . '❤';
                    }
                ?>
                <?php if ($pieces): ?>
                    <span class="pill"><?= htmlspecialchars(implode(' · ', $pieces)) ?></span>
                <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($itemTitle) ?></h3>
                <?php if ($excerpt): ?><p class="muted"><?= htmlspecialchars($excerpt) ?></p><?php endif; ?>
            </a>
            <?php if (!empty($display['show_author']) && $authorName && $authorId > 0): ?>
                <div class="author-chip">
                    <?php if ($authorAvatar): ?>
                        <img class="avatar" src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorName) ?>">
                    <?php else: ?>
                        <div class="avatar"><?= htmlspecialchars($letter) ?></div>
                    <?php endif; ?>
                    <a class="muted" href="<?= htmlspecialchars($profileUrl) ?>"><?= htmlspecialchars($authorName) ?></a>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
