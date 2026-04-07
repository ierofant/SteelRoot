<?php
use App\Services\PublicImageInfoService;

$_loc    = $loc ?? ($GLOBALS['currentLocale'] ?? 'en');
$_locKey = $_loc === 'ru' ? 'ru' : 'en';
$_layout = $layoutClass ?? '';
$_s      = $settings ?? ($home ?? []);

$_title = !empty($_s['home_news_title_' . $_locKey])
    ? $_s['home_news_title_' . $_locKey]
    : ($_locKey === 'ru' ? 'Новости' : 'News');

$_cta = !empty($_s['home_news_cta_' . $_locKey])
    ? $_s['home_news_cta_' . $_locKey]
    : ($_locKey === 'ru' ? 'Все новости →' : 'All news →');
?>
<section class="block home-section-padding <?= $_layout ?>">
    <div class="block-head">
        <h2><?= htmlspecialchars($_title) ?></h2>
        <a class="link" href="/news" aria-label="<?= htmlspecialchars($_loc === 'ru' ? 'Открыть все новости' : 'Open all news') ?>"><?= htmlspecialchars($_cta) ?></a>
    </div>
    <section class="articles-grid articles-grid-cols-3">
        <?php foreach ($__blockData as $idx => $a): ?>
            <?php
                $aTitle = $_loc === 'ru'
                    ? (($a['title_ru'] ?? '') ?: ($a['title_en'] ?? ''))
                    : (($a['title_en'] ?? '') ?: ($a['title_ru'] ?? ''));
                $aDate = !empty($a['created_at']) ? date('d.m.Y', strtotime($a['created_at'])) : '';
                $aViews = (int)($a['views'] ?? 0);
                $aLikes = (int)($a['likes'] ?? 0);
                $aExcerpt = $_loc === 'ru' ? ($a['preview_ru'] ?? '') : ($a['preview_en'] ?? '');
                $aClass = 'article-card' . (empty($a['image_url']) ? ' no-image' : '');
                $authorName = $a['author_name'] ?? '';
                $authorId = (int)($a['author_id'] ?? 0);
                $authorUsername = $a['author_username'] ?? '';
                $authorProfileUrl = $authorUsername ? '/users/' . urlencode($authorUsername) : '/users/' . $authorId;
                $authorAvatar = $a['author_avatar'] ?? '';
                $newsImage = (string)($a['image_url'] ?? '');
                $newsImageDims = PublicImageInfoService::dimensions($newsImage);
                $authorLetterSource = (string)($authorName ?: ($aTitle ?: 'N'));
                $authorLetter = function_exists('mb_strtoupper') && function_exists('mb_substr')
                    ? mb_strtoupper(mb_substr($authorLetterSource, 0, 1))
                    : strtoupper(substr($authorLetterSource, 0, 1));
            ?>
            <article class="<?= $aClass ?>">
                <?php if (!empty($a['image_url'])): ?>
                    <div class="article-card-bg"><img src="<?= htmlspecialchars($a['image_url']) ?>" alt="<?= htmlspecialchars($aTitle) ?>" loading="<?= $idx < 2 ? 'eager' : 'lazy' ?>" decoding="async"<?= $idx < 2 ? ' fetchpriority="high"' : '' ?><?= $newsImageDims ? (' width="' . (int)$newsImageDims['width'] . '" height="' . (int)$newsImageDims['height'] . '"') : '' ?>></div>
                <?php endif; ?>
                <a class="article-card__link" href="/news/<?= urlencode($a['slug']) ?>">
                    <div class="card-meta article-card__meta">
                        <?php if ($aDate): ?><span class="eyebrow"><?= htmlspecialchars($aDate) ?></span><?php endif; ?>
                        <?php
                            $pieces = [];
                            if ($aViews) { $pieces[] = $aViews . '👁'; }
                            if ($aLikes) { $pieces[] = $aLikes . '❤'; }
                            if ($pieces):
                        ?>
                            <span class="pill"><?= htmlspecialchars(implode(' · ', $pieces)) ?></span>
                        <?php endif; ?>
                    </div>
                    <h3><?= htmlspecialchars($aTitle) ?></h3>
                    <?php if ($aExcerpt): ?><p class="muted"><?= htmlspecialchars($aExcerpt) ?></p><?php endif; ?>
                </a>
                <?php if ($authorName && $authorId > 0): ?>
                    <div class="author-chip">
                        <?php if ($authorAvatar): ?>
                            <img class="avatar" src="<?= htmlspecialchars($authorAvatar) ?>" alt="<?= htmlspecialchars($authorName) ?>">
                        <?php else: ?>
                            <div class="avatar"><?= htmlspecialchars($authorLetter) ?></div>
                        <?php endif; ?>
                        <a class="muted" href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($authorName) ?></a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</section>