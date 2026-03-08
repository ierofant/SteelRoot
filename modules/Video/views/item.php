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
                <video id="sr-video-player" class="video-embed__iframe" src="<?= htmlspecialchars($videoUrl) ?>" preload="metadata" playsinline></video>
            <?php else: ?>
                <?= $embedHtml ?>
            <?php endif; ?>
        </div>

        <?php if ($type === 'mp4'): ?>
            <div class="video-player-controls" id="sr-video-controls" aria-label="<?= htmlspecialchars($isRu ? 'Управление видео' : 'Video controls') ?>">
                <button type="button" class="btn ghost small" id="sr-video-play"><?= $isRu ? '▶ Пуск' : '▶ Play' ?></button>
                <span class="video-time" id="sr-video-time">00:00 / 00:00</span>
                <input type="range" id="sr-video-progress" min="0" max="1000" step="1" value="0" aria-label="<?= htmlspecialchars($isRu ? 'Прогресс' : 'Progress') ?>">
                <button type="button" class="btn ghost small" id="sr-video-mute"><?= $isRu ? 'Звук' : 'Sound' ?></button>
                <input type="range" id="sr-video-volume" min="0" max="1" step="0.01" value="1" aria-label="<?= htmlspecialchars($isRu ? 'Громкость' : 'Volume') ?>">
                <select id="sr-video-rate" aria-label="<?= htmlspecialchars($isRu ? 'Скорость' : 'Speed') ?>">
                    <option value="0.75">0.75x</option>
                    <option value="1" selected>1x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2">2x</option>
                </select>
                <button type="button" class="btn ghost small" id="sr-video-fullscreen"><?= $isRu ? 'Во весь экран' : 'Fullscreen' ?></button>
            </div>
        <?php endif; ?>

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

    const video = document.getElementById('sr-video-player');
    if (!video) return;

    const playBtn = document.getElementById('sr-video-play');
    const muteBtn = document.getElementById('sr-video-mute');
    const progress = document.getElementById('sr-video-progress');
    const volume = document.getElementById('sr-video-volume');
    const rate = document.getElementById('sr-video-rate');
    const time = document.getElementById('sr-video-time');
    const fullscreenBtn = document.getElementById('sr-video-fullscreen');

    const fmt = (sec) => {
        if (!Number.isFinite(sec) || sec < 0) return '00:00';
        const s = Math.floor(sec % 60).toString().padStart(2, '0');
        const m = Math.floor((sec / 60) % 60).toString().padStart(2, '0');
        const h = Math.floor(sec / 3600);
        return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
    };

    let isDragging = false;

    progress?.addEventListener('mousedown',  () => { isDragging = true; });
    progress?.addEventListener('touchstart', () => { isDragging = true; }, { passive: true });
    document.addEventListener('mouseup',  () => { isDragging = false; });
    document.addEventListener('touchend', () => { isDragging = false; }, { passive: true });

    const syncTime = () => {
        const current = video.currentTime || 0;
        const total = video.duration || 0;
        if (!isDragging && progress && Number.isFinite(total) && total > 0) {
            progress.value = String(Math.round((current / total) * 1000));
        }
        if (time) {
            time.textContent = `${fmt(current)} / ${fmt(total)}`;
        }
    };

    const syncPlay = () => {
        if (playBtn) {
            playBtn.textContent = video.paused ? '<?= $isRu ? '▶ Пуск' : '▶ Play' ?>' : '<?= $isRu ? '❚❚ Пауза' : '❚❚ Pause' ?>';
        }
    };

    const syncMute = () => {
        if (muteBtn) {
            muteBtn.textContent = video.muted || video.volume === 0 ? '<?= $isRu ? 'Без звука' : 'Muted' ?>' : '<?= $isRu ? 'Звук' : 'Sound' ?>';
        }
    };

    playBtn?.addEventListener('click', async () => {
        try {
            if (video.paused) {
                await video.play();
            } else {
                video.pause();
            }
        } catch (_) {}
        syncPlay();
    });

    muteBtn?.addEventListener('click', () => {
        video.muted = !video.muted;
        syncMute();
    });

    progress?.addEventListener('input', () => {
        const total = video.duration || 0;
        if (!Number.isFinite(total) || total <= 0) return;
        const val = Number(progress.value) / 1000;
        video.currentTime = total * val;
        syncTime();
    });

    volume?.addEventListener('input', () => {
        const v = Number(volume.value);
        video.volume = Number.isFinite(v) ? Math.min(1, Math.max(0, v)) : 1;
        if (video.volume > 0 && video.muted) {
            video.muted = false;
        }
        syncMute();
    });

    rate?.addEventListener('change', () => {
        const r = Number(rate.value);
        if (Number.isFinite(r) && r > 0) {
            video.playbackRate = r;
        }
    });

    fullscreenBtn?.addEventListener('click', () => {
        const box = video.closest('.video-embed');
        if (!box) return;
        if (document.fullscreenElement) {
            document.exitFullscreen?.();
        } else {
            box.requestFullscreen?.();
        }
    });

    video.addEventListener('timeupdate', syncTime);
    video.addEventListener('loadedmetadata', syncTime);
    video.addEventListener('play', syncPlay);
    video.addEventListener('pause', syncPlay);
    video.addEventListener('volumechange', syncMute);
    video.addEventListener('click', async () => {
        if (video.paused) {
            await video.play().catch(() => {});
        } else {
            video.pause();
        }
    });

    syncTime();
    syncPlay();
    syncMute();
});
</script>
