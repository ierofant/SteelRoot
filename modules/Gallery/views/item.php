<?php use App\Services\PublicImageInfoService; ?>
<?= \Core\Asset::styleTag('/assets/css/gallery.css') ?>
<?php
    $tKey = $locale === 'ru' ? 'title_ru' : 'title_en';
    $dKey = $locale === 'ru' ? 'description_ru' : 'description_en';
    $showTitle       = !empty($display['show_title']);
    $showDescription = !empty($display['show_description']);
    $showLikes       = !empty($display['show_likes']);
    $showViews       = !empty($display['show_views']);
    $showTags        = !empty($display['show_tags']);
    $tags            = $tags ?? [];
    $authorProfileUrl = $authorProfileUrl ?? (!empty($item['author_username'])
        ? '/users/' . urlencode($item['author_username'])
        : '/users/' . (int)($item['author_id'] ?? 0));
    $authorSignatureVisible = $authorSignatureVisible ?? false;
    $authorSignature        = $authorSignature ?? '';
    $masterLikeState        = $masterLikeState ?? ['can_like' => false, 'reason' => 'login_required'];
    $masterLikeToken        = $masterLikeToken ?? '';
    $collectionToken        = $collectionToken ?? '';
    $collectionsAvailable   = !empty($collectionsAvailable);
    $viewer                 = $viewer ?? null;
    $masterLikesCount       = (int)($item['master_likes_count'] ?? 0);
    $imgPath = $item['path_medium'] ?? $item['path'] ?? '';
    $imgSrc  = htmlspecialchars($imgPath);
    $imgDims = PublicImageInfoService::dimensions($imgPath);
    $imgAlt  = htmlspecialchars($item[$tKey] ?? 'Image');
?>
<div class="gallery-photo-hero">
    <div class="gallery-photo-bg" aria-hidden="true">
        <img src="<?= $imgSrc ?>" alt="" loading="eager" fetchpriority="high" decoding="async"<?= $imgDims ? (' width="' . (int)$imgDims['width'] . '" height="' . (int)$imgDims['height'] . '"') : '' ?>>
    </div>
    <div class="gallery-photo-stage">
        <img src="<?= $imgSrc ?>" alt="<?= $imgAlt ?>" loading="eager" fetchpriority="high" decoding="async"<?= $imgDims ? (' width="' . (int)$imgDims['width'] . '" height="' . (int)$imgDims['height'] . '"') : '' ?>>
    </div>
</div>

<div class="gallery-photo-body">

    <?php if (($message ?? '') === 'collection-saved'): ?><div class="users-alert users-alert--success gallery-inline-alert">Saved to your collection.</div><?php endif; ?>
    <?php if (($error ?? '') === 'collection-save-failed'): ?><div class="users-alert users-alert--danger gallery-inline-alert">Could not save this work to a collection.</div><?php endif; ?>

    <?php if ($showTitle && !empty($item[$tKey])): ?>
    <div class="gallery-photo-headline">
        <h2><?= htmlspecialchars($item[$tKey]) ?></h2>
        <?php if ($showViews || $showLikes): ?>
        <div class="gallery-photo-stats">
            <?php if ($showViews): ?>
            <span class="gallery-stat">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><ellipse cx="12" cy="12" rx="11" ry="8" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
                <span id="g-view-count"><?= (int)($item['views'] ?? 0) ?></span>
            </span>
            <?php endif; ?>
            <?php if ($showLikes): ?>
            <span class="gallery-stat">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
                <span id="g-like-count" data-likes-for="gallery-<?= (int)$item['id'] ?>"><?= (int)($item['likes'] ?? 0) ?></span>
            </span>
            <?php endif; ?>
            <span class="gallery-stat gallery-stat--master">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span id="g-master-like-count"><?= $masterLikesCount ?></span>
                <span><?= htmlspecialchars(__('gallery.master_like.short')) ?></span>
            </span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($item['submitted_by_master']) && !empty($item['author_name']) && !empty($item['author_id'])): ?>
    <div class="gallery-author-row">
        <?php $authorInitial = htmlspecialchars(function_exists('mb_strtoupper') && function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)$item['author_name'], 0, 1)) : strtoupper(substr((string)$item['author_name'], 0, 1))); ?>
        <span class="gallery-author-avatar">
            <?php if (!empty($item['author_avatar'])): ?>
                <img src="<?= htmlspecialchars($item['author_avatar']) ?>" alt="<?= htmlspecialchars($item['author_name']) ?>">
            <?php else: ?>
                <?= $authorInitial ?>
            <?php endif; ?>
        </span>
        <a class="muted" href="<?= htmlspecialchars($authorProfileUrl) ?>"><?= htmlspecialchars($item['author_name']) ?></a>
    </div>
    <?php endif; ?>

    <?php if ($showDescription && !empty($item[$dKey])): ?>
    <?php
        $descRaw = (string)($item[$dKey] ?? '');
        $descHtml = (strip_tags($descRaw) === $descRaw)
            ? nl2br(htmlspecialchars($descRaw))
            : $descRaw;
    ?>
    <div class="gallery-photo-desc"><?= $descHtml ?></div>
    <?php endif; ?>

    <?php if (!empty($authorSignatureVisible) && $authorSignature !== ''): ?>
    <div class="muted author-signature"><?= htmlspecialchars($authorSignature) ?></div>
    <?php endif; ?>

    <?php if ($showTags && !empty($tags)): ?>
    <section class="gallery-tag-row gallery-tag-row--detail" aria-label="Gallery tags">
        <div class="gallery-tag-row__meta">
            <span class="gallery-tag-row__label">Tags</span>
        </div>
        <div class="gallery-tag-band__list">
        <?php foreach ($tags as $tag): ?>
            <a class="gallery-tag-chip" href="/tags/<?= urlencode($tag['slug'] ?? '') ?>">
                <span class="gallery-tag-chip__hash">#</span>
                <span class="gallery-tag-chip__label"><?= htmlspecialchars(ltrim($tag['name'] ?? $tag['slug'] ?? '', "# \t\n\r\0\x0B")) ?></span>
            </a>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="gallery-photo-actions">
        <div class="gallery-photo-actions__stats">
        <?php if ($showLikes): ?>
        <button type="button" class="like-btn gallery-like-btn gallery-photo-action" id="like-gallery" data-like-type="gallery" data-id="<?= (int)$item['id'] ?>">
            <svg class="like-btn-heart" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            <span id="like-count-inline" data-like-count><?= (int)($item['likes'] ?? 0) ?></span>
        </button>
        <?php endif; ?>
        <?php if (!empty($masterLikeState['can_like'])): ?>
        <button
            type="button"
            class="like-btn gallery-master-like-btn gallery-photo-action"
            id="master-like-gallery"
            data-id="<?= (int)$item['id'] ?>"
            data-token="<?= htmlspecialchars($masterLikeToken) ?>"
        >
            <svg class="gallery-master-like-btn__icon" width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
            </svg>
            <span id="master-like-count-inline"><?= $masterLikesCount ?></span>
            <span><?= htmlspecialchars(__('gallery.master_like.action')) ?></span>
        </button>
        <?php else: ?>
        <span class="gallery-master-like-pill gallery-photo-action" title="<?= htmlspecialchars(__('gallery.master_like.only_verified')) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M5 8.5 8.5 5l3.5 3 3.5-3L19 8.5v2.5H5V8.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                <path d="M12 20.5 5.7 14.6a3.85 3.85 0 0 1 0-5.48 3.8 3.8 0 0 1 5.38 0L12 10l.92-.88a3.8 3.8 0 0 1 5.38 0 3.85 3.85 0 0 1 0 5.48L12 20.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
            </svg>
            <span><?= $masterLikesCount ?></span>
            <span><?= htmlspecialchars(__('gallery.master_like.label')) ?></span>
        </span>
        <?php endif; ?>
        </div>
        <div class="gallery-photo-actions__tools">
        <?php if ($collectionsAvailable && !empty($viewer)): ?>
        <?php $isSaved = !empty($collectionSaved); ?>
        <?php if ($isSaved): ?>
        <span class="btn ghost btn--saved gallery-photo-action" title="Already in your collection">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><polyline points="20 6 9 17 4 12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Saved
        </span>
        <?php else: ?>
        <form method="post" action="/profile/collections/quick-save" class="gallery-photo-save-form gallery-photo-action">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($collectionToken) ?>">
            <input type="hidden" name="entity_type" value="gallery">
            <input type="hidden" name="entity_id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? (!empty($item['slug']) ? '/gallery/photo/' . $item['slug'] : '/gallery/view?id=' . (int)$item['id'])) ?>">
            <button type="submit" class="btn ghost gallery-photo-action-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                Save
            </button>
        </form>
        <?php endif; ?>
        <?php elseif (empty($viewer)): ?>
        <a class="btn ghost gallery-photo-action gallery-photo-action-btn" href="/login">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            Save
        </a>
        <?php endif; ?>
        <?php if (!empty($shareTargets)): ?>
        <div class="gallery-share" data-gallery-share>
            <button type="button" class="btn ghost gallery-share__trigger gallery-photo-action-btn" data-gallery-share-toggle aria-expanded="false" aria-controls="gallery-share-sheet">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 12V5.75a1.75 1.75 0 0 1 3.02-1.2l4.23 4.5a1.75 1.75 0 0 1 0 2.4l-4.23 4.5A1.75 1.75 0 0 1 8 14.75V12Zm-3 7h14a2 2 0 0 0 2-2v-2.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?= htmlspecialchars(__('gallery.share.action')) ?>
            </button>
            <div class="gallery-share__sheet" id="gallery-share-sheet" hidden>
                <div class="gallery-share__head">
                    <span class="gallery-share__eyebrow"><?= htmlspecialchars(__('gallery.share.label')) ?></span>
                    <button type="button" class="gallery-share__close" data-gallery-share-close aria-label="<?= htmlspecialchars(__('gallery.share.close')) ?>">×</button>
                </div>
                <div class="gallery-share__list">
                    <?php foreach ($shareTargets as $target): ?>
                    <a class="gallery-share__link gallery-share__link--<?= htmlspecialchars((string)$target['platform']) ?>" href="<?= htmlspecialchars((string)$target['href']) ?>" target="_blank" rel="nofollow noopener noreferrer">
                        <span><?= htmlspecialchars((string)$target['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                    <button type="button" class="gallery-share__link gallery-share__link--copy" data-gallery-share-copy="<?= htmlspecialchars((string)($shareCopyUrl ?? '')) ?>" data-gallery-share-copy-label="<?= htmlspecialchars((string)__('gallery.share.copy')) ?>" data-gallery-share-copied-label="<?= htmlspecialchars((string)__('gallery.share.copied')) ?>">
                        <span><?= htmlspecialchars(__('gallery.share.copy')) ?></span>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <a class="btn ghost gallery-photo-action gallery-photo-action-btn gallery-photo-back" href="/gallery">← Галерея</a>
        </div>
    </div>

    <?= $commentsHtml ?? '' ?>

</div>
<?php $galleryJs = APP_ROOT . '/modules/Gallery/assets/js/gallery.js'; ?>
<?= \Core\Asset::scriptTag('/modules/Gallery/assets/js/gallery.js', ['defer' => true]) ?>
