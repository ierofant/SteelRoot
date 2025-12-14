<?php
$loc = $locale ?? 'en';
ob_start();
?>
<section class="articles-hero">
    <div>
        <p class="eyebrow"><?= __('articles.title') ?></p>
        <h1><?= __('articles.title') ?></h1>
        <p class="muted"><?= __('articles.list.subtitle') ?></p>
    </div>
</section>

<section class="articles-grid">
    <?php foreach ($articles as $article): ?>
        <?php
            $title = $loc === 'ru' ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? '');
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
            $authorAvatar = $article['author_avatar'] ?? '';
            $letter = strtoupper(substr($authorName ?: ($article['title_en'] ?? 'A'), 0, 1));
        ?>
        <a class="<?= $classes ?>" href="/articles/<?= urlencode($article['slug']) ?>" <?= $bg ? "style=\"{$bg}\"" : '' ?>>
            <div class="card-meta">
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
            <h3><?= htmlspecialchars($title) ?></h3>
            <?php if ($excerpt): ?><p class="muted"><?= htmlspecialchars($excerpt) ?></p><?php endif; ?>
            <?php if (!empty($display['show_author']) && $authorName && $authorId > 0): ?>
                <div class="author-chip">
                 <span class="avatar"
                      style="<?= $authorAvatar ? "background-image:url('".htmlspecialchars($authorAvatar)."')" : '' ?>">
                      <?= $authorAvatar ? '' : htmlspecialchars($letter) ?>
                 </span>
                <span class="muted"><?= htmlspecialchars($authorName) ?></span>
               </div>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>
<?php $content = ob_get_clean(); include APP_ROOT . '/app/views/layout.php'; ?>
