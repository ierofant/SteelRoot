<?php
$loc = $locale ?? 'en';
$enabledCategories = $enabledCategories ?? [];
$currentCategory = $category ?? null;
$currentSort = $sort ?? 'new';
$articlesBaseUrl = isset($currentCategory['slug']) ? '/articles/category/' . rawurlencode((string)$currentCategory['slug']) : '/articles';
?>
<section class="articles-hero">
    <div class="tt-ornament" aria-hidden="true">
        <span class="tt-cross"></span>
        <span class="tt-num">01</span>
    </div>
    <div>
        <p class="eyebrow"><?= htmlspecialchars($title ?? __('articles.title')) ?></p>
        <h1><?= htmlspecialchars($title ?? __('articles.title')) ?></h1>
        <p class="muted"><?= htmlspecialchars($description ?? __('articles.list.subtitle')) ?></p>
    </div>
    <form method="get" action="<?= htmlspecialchars($articlesBaseUrl) ?>" class="articles-filter">
        <select name="sort">
            <option value="new" <?= $currentSort === 'new' ? 'selected' : '' ?>><?= __('articles.filter.sort_new') ?></option>
            <option value="views" <?= $currentSort === 'views' ? 'selected' : '' ?>><?= __('articles.filter.sort_popular') ?></option>
            <option value="likes" <?= $currentSort === 'likes' ? 'selected' : '' ?>><?= __('articles.filter.sort_likes') ?></option>
        </select>
        <button type="submit" class="btn ghost"><?= __('articles.filter.apply') ?></button>
    </form>
</section>

<?php if (!empty($enabledCategories)): ?>
<div class="articles-categories-mobile">
    <label class="articles-categories-mobile__label" for="articles-category-select">
        <?= $loc === 'ru' ? 'Категория' : 'Category' ?>
    </label>
    <select
        id="articles-category-select"
        class="articles-categories-mobile__select"
        onchange="if(this.value){window.location.href=this.value;}"
    >
        <option value="/articles" <?= $currentCategory === null ? 'selected' : '' ?>>
            <?= $loc === 'ru' ? 'Все категории' : 'All categories' ?>
        </option>
        <?php foreach ($enabledCategories as $ec): ?>
            <?php
            $ecLabel = $loc === 'ru' ? ($ec['name_ru'] ?: $ec['name_en']) : ($ec['name_en'] ?: $ec['name_ru']);
            $isSelected = $currentCategory && (int)($currentCategory['id'] ?? 0) === (int)$ec['id'];
            ?>
            <option value="/articles/category/<?= rawurlencode($ec['slug']) ?><?= $currentSort !== 'new' ? ('?sort=' . rawurlencode($currentSort)) : '' ?>" <?= $isSelected ? 'selected' : '' ?>>
                <?= htmlspecialchars($ecLabel) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<nav class="articles-categories">
    <a class="pill <?= $currentCategory === null ? 'active' : '' ?>" href="/articles">
        <?= $loc === 'ru' ? 'Все' : 'All' ?>
    </a>
    <?php foreach ($enabledCategories as $ec): ?>
        <?php
        $ecLabel = $loc === 'ru' ? ($ec['name_ru'] ?: $ec['name_en']) : ($ec['name_en'] ?: $ec['name_ru']);
        $isActive = $currentCategory && (int)($currentCategory['id'] ?? 0) === (int)$ec['id'];
        ?>
        <a class="pill <?= $isActive ? 'active' : '' ?>" href="/articles/category/<?= rawurlencode($ec['slug']) ?><?= $currentSort !== 'new' ? ('?sort=' . rawurlencode($currentSort)) : '' ?>">
            <?= htmlspecialchars($ecLabel) ?>
        </a>
    <?php endforeach; ?>
</nav>
<script>
(() => {
    const mobile = document.querySelector('.articles-categories-mobile');
    const desktop = document.querySelector('.articles-categories');
    if (!mobile || !desktop || typeof window.matchMedia !== 'function') {
        return;
    }
    const media = window.matchMedia('(max-width: 1024px)');
    const sync = () => {
        if (media.matches) {
            mobile.style.setProperty('display', 'block', 'important');
            desktop.style.setProperty('display', 'none', 'important');
        } else {
            mobile.style.setProperty('display', 'none', 'important');
            desktop.style.setProperty('display', 'flex', 'important');
        }
    };
    sync();
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', sync);
    } else if (typeof media.addListener === 'function') {
        media.addListener(sync);
    }
})();
</script>
<?php endif; ?>

<?php $gridCols = max(1, min(6, (int)($gridCols ?? 3))); ?>
<section class="articles-grid articles-grid-cols-<?= $gridCols ?>">
    <?php foreach ($articles as $article): ?>
        <?php
            $itemTitle = $loc === 'ru' ? ($article['title_ru'] ?? '') : ($article['title_en'] ?? '');
            $date = !empty($article['created_at']) ? date('d.m.Y', strtotime($article['created_at'])) : '';
            $excerpt = $loc === 'ru' ? ($article['preview_ru'] ?? '') : ($article['preview_en'] ?? '');
            $views = $article['views'] ?? null;
            $likes = $article['likes'] ?? null;
            $bg = !empty($article['image_url']) ? "url('" . htmlspecialchars($article['image_url']) . "')" : '';
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
            <?php if (!empty($article['image_url'])): ?>
            <div class="article-card-bg"><img src="<?= htmlspecialchars($article['image_url']) ?>" alt=""></div>
            <?php endif; ?>
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
                <?php if ($catSlug && $catLabel): ?>
                    <a class="pill article-card__cat" href="/articles/category/<?= rawurlencode($catSlug) ?>" onclick="event.stopPropagation()">
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
    ? '/articles/category/' . rawurlencode($category['slug'])
    : '/articles';
$paginationChpu = true;
$paginationQuery = $currentSort !== 'new' ? ('?sort=' . rawurlencode($currentSort)) : '';
include APP_ROOT . '/app/views/partials/pagination.php';
?>
