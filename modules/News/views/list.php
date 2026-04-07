<?php
use App\Services\PublicImageInfoService;

$loc = $locale ?? 'en';
$enabledCategories = $enabledCategories ?? [];
$currentCategory = $category ?? null;
$listBaseUrl = $listBaseUrl ?? '/news';
$categoryBaseUrl = $categoryBaseUrl ?? '/news/category';
$itemBaseUrl = $itemBaseUrl ?? '/news';
?>
<section class="articles-hero">
    <div class="tt-ornament" aria-hidden="true"><span class="tt-cross"></span><span class="tt-num">01</span></div>
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? 'News') ?></p>
        <h1><?= htmlspecialchars($title ?? 'News') ?></h1>
        <p class="muted"><?= htmlspecialchars($description ?? '') ?></p>
    </div>
</section>

<?php if (!empty($enabledCategories)): ?>
<nav class="articles-categories">
    <a class="pill <?= $currentCategory === null ? 'active' : '' ?>" href="<?= htmlspecialchars($listBaseUrl) ?>">
        <?= $loc === 'ru' ? 'Все' : 'All' ?>
    </a>
    <?php foreach ($enabledCategories as $ec): ?>
        <?php
        $ecLabel = $loc === 'ru' ? ($ec['name_ru'] ?: $ec['name_en']) : ($ec['name_en'] ?: $ec['name_ru']);
        $isActive = $currentCategory && (int)($currentCategory['id'] ?? 0) === (int)$ec['id'];
        ?>
        <a class="pill <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($categoryBaseUrl) ?>/<?= rawurlencode($ec['slug']) ?>">
            <?= htmlspecialchars($ecLabel) ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php $gridCols = max(1, min(6, (int)($gridCols ?? 3))); ?>
<section class="articles-grid articles-grid-cols-<?= $gridCols ?>">
    <?php foreach ($articles as $idx => $article): ?>
        <?php
            $itemTitle = $loc === 'ru' ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? '');
            $date = !empty($article['created_at']) ? date('d.m.Y', strtotime($article['created_at'])) : '';
            $excerpt = $loc === 'ru' ? ($article['preview_ru'] ?? '') : ($article['preview_en'] ?? '');
            if (empty($display['description_enabled'])) {
                $excerpt = '';
            }
            $views = $article['views'] ?? null;
            $likes = $article['likes'] ?? null;
            $cardImage = !empty($article['image_url']) ? (string)$article['image_url'] : (string)($article['cover_url'] ?? '');
            $cardImageDims = PublicImageInfoService::dimensions($cardImage);
            $bg = $cardImage !== '' ? "url('" . htmlspecialchars($cardImage) . "')" : '';
            $classes = 'article-card';
            if (!$bg) {
                $classes .= ' no-image';
            }
            $authorName = $article['author_name'] ?? '';
            $authorId = (int)($article['author_id'] ?? 0);
            $authorUsername = $article['author_username'] ?? '';
            $profileUrl = $authorUsername ? '/users/' . urlencode($authorUsername) : '/users/' . $authorId;
            $authorAvatar = $article['author_avatar'] ?? '';
            $letterSource = (string)($authorName ?: ($itemTitle ?: 'A'));
            $letter = function_exists('mb_strtoupper') && function_exists('mb_substr')
                ? mb_strtoupper(mb_substr($letterSource, 0, 1))
                : strtoupper(substr($letterSource, 0, 1));
            $catSlug = $article['category_slug'] ?? '';
            $catLabel = $loc === 'ru'
                ? ($article['category_name_ru'] ?? ($article['category_name_en'] ?? ''))
                : ($article['category_name_en'] ?? ($article['category_name_ru'] ?? ''));
        ?>
        <article class="<?= $classes ?>">
            <?php if ($cardImage !== ''): ?>
            <div class="article-card-bg"><img src="<?= htmlspecialchars($cardImage) ?>" alt="<?= htmlspecialchars($itemTitle) ?>" loading="<?= $idx < 2 ? 'eager' : 'lazy' ?>" decoding="async"<?= $idx < 2 ? ' fetchpriority="high"' : '' ?><?= $cardImageDims ? (' width="' . (int)$cardImageDims['width'] . '" height="' . (int)$cardImageDims['height'] . '"') : '' ?>></div>
            <?php endif; ?>
            <a class="article-card__link" href="<?= htmlspecialchars($itemBaseUrl) ?>/<?= urlencode($article['slug']) ?>">
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
                <?php if ($catSlug && $catLabel): ?>
                    <a class="pill article-card__cat" href="<?= htmlspecialchars($categoryBaseUrl) ?>/<?= rawurlencode($catSlug) ?>" onclick="event.stopPropagation()">
                        <?= htmlspecialchars($catLabel) ?>
                    </a>
                <?php endif; ?>
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
<?php
$paginationPage    = $page ?? 1;
$paginationTotal   = $total ?? 0;
$paginationPerPage = $perPage ?? 6;
$paginationBase    = isset($category['slug'])
    ? ($categoryBaseUrl . '/' . rawurlencode($category['slug']))
    : $listBaseUrl;
$paginationChpu = true;
include APP_ROOT . '/app/views/partials/pagination.php';
?>