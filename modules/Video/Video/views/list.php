<?php
use Modules\Video\Controllers\VideoController;
$loc = $locale ?? 'en';
$isRu = $loc === 'ru';
?>
<section class="videos-hero">
    <p class="eyebrow"><?= htmlspecialchars($title ?? 'Videos') ?></p>
    <h1><?= htmlspecialchars($title ?? 'Videos') ?></h1>
    <p class="muted"><?= htmlspecialchars($description ?? '') ?></p>
</section>

<?php if (!empty($categories) && is_array($categories)): ?>
    <div class="video-categories-grid">
        <a class="video-category-card <?= empty($activeCategorySlug) ? 'active' : '' ?>" href="/videos">
            <div class="video-category-card__media video-category-card__media--empty">▶</div>
            <span class="video-category-card__name"><?= $isRu ? 'Все видео' : 'All videos' ?></span>
        </a>
        <?php foreach ($categories as $cat): ?>
            <?php
            $slug = (string)($cat['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $name = $isRu
                ? ((string)($cat['name_ru'] ?: $cat['name_en']))
                : ((string)($cat['name_en'] ?: $cat['name_ru']));
            $img = (string)($cat['image_url'] ?? '');
            ?>
            <a class="video-category-card <?= ($activeCategorySlug ?? '') === $slug ? 'active' : '' ?>"
               href="/videos/category/<?= rawurlencode($slug) ?>" aria-label="<?= htmlspecialchars($name) ?>">
                <?php if ($img !== ''): ?>
                    <img class="video-category-card__media" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
                <?php else: ?>
                    <div class="video-category-card__media video-category-card__media--empty">▶</div>
                <?php endif; ?>
                <span class="video-category-card__name"><?= htmlspecialchars($name) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2><?= htmlspecialchars($sectionTitle ?? ($isRu ? 'Топ видео' : 'Top videos')) ?></h2>

<?php if (empty($items)): ?>
    <p class="muted"><?= $loc === 'ru' ? 'Видео пока нет.' : 'No videos yet.' ?></p>
<?php else: ?>
<div class="videos-grid">
    <?php foreach ($items as $item): ?>
        <?php
        $videoId    = (int)($item['id'] ?? 0);
        $itemTitle = $loc === 'ru'
            ? ($item['title_ru'] ?: $item['title_en'])
            : ($item['title_en'] ?: $item['title_ru']);
        $thumb     = VideoController::resolveThumbnail($item);
        $duration  = $item['duration'] ?? '';
        $views     = (int)($item['views'] ?? 0);
        $likes     = (int)($item['likes'] ?? 0);
        $type      = $item['video_type'] ?? 'youtube';
        $href      = VideoController::publicPath($item);
        ?>
        <article class="video-card" data-id="<?= $videoId ?>">
            <a class="video-card__link" href="<?= htmlspecialchars($href) ?>">
                <div class="video-card__thumb">
                    <?php if ($thumb): ?>
                        <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($itemTitle) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="video-card__no-thumb">▶</div>
                    <?php endif; ?>
                    <div class="video-card__play">▶</div>
                    <?php if ($duration): ?>
                        <span class="video-card__duration"><?= htmlspecialchars($duration) ?></span>
                    <?php endif; ?>
                    <span class="video-card__type"><?= htmlspecialchars($type) ?></span>
                </div>
                <div class="video-card__body">
                    <h3><?= htmlspecialchars($itemTitle) ?></h3>
                    <div class="video-card__meta">
                        <?php if ($views): ?><span>👁 <?= $views ?></span><?php endif; ?>
                        <span>❤ <span class="video-like-count"><?= $likes ?></span></span>
                    </div>
                </div>
            </a>
            <div class="video-card__actions">
                <button type="button"
                        class="like-btn video-like-btn"
                        data-id="<?= $videoId ?>"
                        data-liked-key="liked_video_<?= $videoId ?>"
                        aria-label="<?= htmlspecialchars($isRu ? 'Поставить лайк' : 'Like this video') ?>">
                    <span>❤</span><span class="video-like-count"><?= $likes ?></span>
                </button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php
$paginationPage    = $page ?? 1;
$paginationTotal   = $total ?? 0;
$paginationPerPage = $perPage ?? 12;
$paginationBase    = ($activeCategorySlug ?? '') !== ''
    ? '/videos/category/' . rawurlencode($activeCategorySlug)
    : '/videos';
$paginationChpu    = true;
include APP_ROOT . '/app/views/partials/pagination.php';
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toastAlready = <?= json_encode($isRu ? 'Вы уже ставили лайк' : 'You already liked this') ?>;
    const toastLiked   = <?= json_encode($isRu ? 'Лайк засчитан' : 'Liked!') ?>;
    const toastFail    = <?= json_encode($isRu ? 'Не удалось поставить лайк' : 'Could not like') ?>;

    document.querySelectorAll('.video-like-btn').forEach((btn) => {
        const id = btn.dataset.id;
        const key = btn.dataset.likedKey || ('liked_video_' + id);

        if (localStorage.getItem(key) === '1') {
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        }

        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            if (localStorage.getItem(key) === '1') {
                if (window.showToast) window.showToast(toastAlready, 'info');
                return;
            }

            try {
                const res = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {Accept: 'application/json'},
                    body: new URLSearchParams({type: 'video', id}),
                });
                if (!res.ok) throw new Error('bad');
                const data = await res.json();
                const likes = Number.isFinite(Number(data.likes)) ? Number(data.likes) : null;
                if (likes !== null) {
                    document.querySelectorAll('.video-card[data-id="' + id + '"] .video-like-count').forEach((el) => {
                        el.textContent = String(likes);
                    });
                }
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                localStorage.setItem(key, '1');
                if (window.showToast) window.showToast(data.already ? toastAlready : toastLiked, 'success');
            } catch (err) {
                if (window.showToast) window.showToast(toastFail, 'danger');
            }
        });
    });
});
</script>
<?php endif; ?>
