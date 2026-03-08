<?php
$loc       = $locale ?? 'en';
$isRu      = $loc === 'ru';
$titleKey  = $isRu ? 'title_ru' : 'title_en';
$descKey   = $isRu ? 'description_ru' : 'description_en';
$itemTitle = $item[$titleKey] ?: ($item['title_en'] ?: $item['title_ru']);
$itemDesc  = $item[$descKey] ?: ($item['description_en'] ?: $item['description_ru']);
$views     = (int)($item['views'] ?? 0);
$likes     = (int)($item['likes'] ?? 0);
$duration  = $item['duration'] ?? '';
$type      = (string)($item['video_type'] ?? 'youtube');
$videoUrl  = (string)($item['video_url'] ?? '');
$relatedVideos = is_array($relatedVideos ?? null) ? $relatedVideos : [];

$descHtml = '';
if (trim((string)$itemDesc) !== '') {
    $raw = (string)$itemDesc;
    $raw = preg_replace('#<\s*(script|style)[^>]*>.*?<\s*/\s*\\1\s*>#is', '', $raw) ?? '';
    $allowed = '<p><br><strong><em><b><i><u><ul><ol><li><blockquote><h3><h4><a>';
    $safe = strip_tags($raw, $allowed);
    $safe = preg_replace_callback(
        '#<a\b([^>]*)>#i',
        static function (array $m): string {
            $attrs = $m[1] ?? '';
            $href = '';
            if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm) === 1) {
                $candidate = trim((string)$hm[2]);
                if (
                    preg_match('#^(https?://|mailto:|tel:|/|#)#i', $candidate) === 1
                    && preg_match('#^\s*javascript:#i', $candidate) !== 1
                ) {
                    $href = htmlspecialchars($candidate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
            }
            if ($href === '') {
                return '<a>';
            }
            return '<a href="' . $href . '" rel="nofollow noopener noreferrer">';
        },
        $safe
    ) ?? $safe;
    $safe = preg_replace('#<a>\s*</a>#i', '', $safe) ?? $safe;
    $descHtml = $safe;
}
?>
<section class="video-page">
    <article class="video-view">
        <header class="video-view__header">
            <p class="eyebrow">
                <?= htmlspecialchars(strtoupper($type)) ?>
                <?php if ($duration): ?>&nbsp;· <?= htmlspecialchars($duration) ?><?php endif; ?>
            </p>
            <h1><?= htmlspecialchars($itemTitle) ?></h1>
            <div class="video-view__meta muted">
                <?php if ($views): ?><span>👁 <?= $views ?></span><?php endif; ?>
                <?php if ($likes): ?><span>❤ <?= $likes ?></span><?php endif; ?>
            </div>
        </header>

        <div class="video-embed">
            <?php if ($type === 'mp4'): ?>
                <video class="video-embed__iframe" src="<?= htmlspecialchars($videoUrl) ?>" controls preload="metadata" playsinline controlsList="nodownload"></video>
            <?php else: ?>
                <?= $embedHtml ?>
            <?php endif; ?>
        </div>

        <?php if ($descHtml !== ''): ?>
            <div class="video-view__desc">
                <?= $descHtml ?>
            </div>
        <?php endif; ?>

        <div class="form-actions video-item-actions">
            <button type="button" class="like-btn" id="like-video" data-id="<?= (int)$item['id'] ?>">
                <span>❤</span><span id="like-count"><?= $likes ?></span>
            </button>
            <a class="btn ghost" href="/videos">← <?= $isRu ? 'Все видео' : 'All videos' ?></a>
        </div>
    </article>

    <?php if (!empty($relatedVideos)): ?>
        <aside class="video-related">
            <h3><?= $isRu ? 'Похожие видео' : 'Related videos' ?></h3>
            <div class="video-related__list">
                <?php foreach ($relatedVideos as $rv): ?>
                    <?php
                    $rvTitle = $isRu
                        ? ((string)($rv['title_ru'] ?: $rv['title_en']))
                        : ((string)($rv['title_en'] ?: $rv['title_ru']));
                    $rvThumb = \Modules\Video\Controllers\VideoController::resolveThumbnail($rv);
                    $rvHref = \Modules\Video\Controllers\VideoController::publicPath($rv);
                    ?>
                    <a class="video-related__item" href="<?= htmlspecialchars($rvHref) ?>">
                        <span class="video-related__thumb">
                            <?php if ($rvThumb): ?>
                                <img src="<?= htmlspecialchars($rvThumb) ?>" alt="<?= htmlspecialchars($rvTitle) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="video-related__thumb-empty">▶</span>
                            <?php endif; ?>
                        </span>
                        <span class="video-related__meta">
                            <strong><?= htmlspecialchars($rvTitle) ?></strong>
                            <span>👁 <?= (int)($rv['views'] ?? 0) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('like-video');
    if (btn) {
        const countEl = document.getElementById('like-count');
        const storageKey = 'liked_video_' + btn.dataset.id;
        if (localStorage.getItem(storageKey) === '1') {
            btn.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');
        }
        btn.addEventListener('click', async () => {
            if (localStorage.getItem(storageKey) === '1') {
                if (window.showToast) window.showToast('<?= $isRu ? 'Вы уже ставили лайк' : 'You already liked this' ?>', 'info');
                return;
            }
            const id = btn.dataset.id;
            try {
                const res = await fetch('/api/v1/like', {
                    method: 'POST',
                    headers: {'Accept': 'application/json'},
                    body: new URLSearchParams({type: 'video', id})
                });
                if (!res.ok) throw new Error('bad');
                const data = await res.json();
                if (data.likes !== undefined) countEl.textContent = data.likes;
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                localStorage.setItem(storageKey, '1');
                if (window.showToast) window.showToast(data.already ? '<?= $isRu ? 'Уже лайкали' : 'Already liked' ?>' : '<?= $isRu ? 'Лайк засчитан' : 'Liked!' ?>', 'success');
            } catch (e) {
                if (window.showToast) window.showToast('<?= $isRu ? 'Не удалось поставить лайк' : 'Could not like' ?>', 'danger');
            }
        });
    }

});
</script>
