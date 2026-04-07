<?php
$u = $user ?? [];
$restricted = !empty($restricted);
$letterSource = (string)($u['display_name'] ?? ($u['name'] ?? ($username ?? 'U')));
$letter = function_exists('mb_strtoupper') && function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($letterSource, 0, 1))
    : strtoupper(substr($letterSource, 0, 1));
$canView = !empty($canViewDetails);
$links = json_decode((string)($u['external_links_json'] ?? ''), true);
if (!is_array($links)) {
    $links = [];
}
$supportedLinkPlatforms = ['telegram', 'vk', 'instagram', 'youtube', 'tiktok', 'whatsapp'];
$links = array_filter($links, static function ($value, $key) use ($supportedLinkPlatforms): bool {
    if (!is_string($key) || !is_string($value)) {
        return false;
    }

    $platform = strtolower(trim($key));
    $value = trim($value);

    return $value !== '' && in_array($platform, $supportedLinkPlatforms, true);
}, ARRAY_FILTER_USE_BOTH);
$role = strtolower((string)($u['role'] ?? 'user'));
$primaryGroupName = trim((string)($u['primary_group']['name'] ?? ''));
$primaryGroupSlug = strtolower(trim((string)($u['primary_group']['slug'] ?? '')));
$showRolePill = $role !== 'user';
$showPrimaryGroupPill = $primaryGroupName !== '' && !in_array($primaryGroupSlug, ['master', 'verified_master'], true);
$verifiedLabel = !empty($u['is_verified']) ? __('users.profile.badge.verified_master') : '';
$isVerified = !empty($u['is_verified']);
$isMasterProfile = !empty($u['is_master']);
$displayName = trim((string)($u['display_name'] ?? ($u['name'] ?? ($u['username'] ?? ''))));
$masterContactAvailability = is_array($masterContactAvailability ?? null) ? $masterContactAvailability : ['available' => false, 'settings' => []];
$identityStatement = $canView && !empty($u['bio'])
    ? mb_substr(trim((string)$u['bio']), 0, 180)
    : (!empty($u['city'])
        ? __('users.public.presence.city_intro') . ' ' . trim((string)$u['city']) . ' ' . __('users.public.presence.city_outro')
        : __('users.public.presence.default'));
?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users.css') ?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users-public.css') ?>
<section class="users-shell users-public">
    <?php if (($message ?? '') === 'collection-saved'): ?><div class="users-alert users-alert--success"><?= __('users.collections.flash.saved') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'collection-save-failed'): ?><div class="users-alert users-alert--danger"><?= __('users.collections.flash.save_failed_item') ?></div><?php endif; ?>
    <?php if ($restricted): ?>
        <article class="users-card users-restricted">
            <p class="users-eyebrow"><?= __('profile') ?></p>
            <h1><?= __('users.public.private.title') ?></h1>
            <p><?= __('users.public.private.text_before') ?> @<?= htmlspecialchars($username ?? ($u['username'] ?? '')) ?> <?= __('users.public.private.text_after') ?></p>
        </article>
    <?php else: ?>
        <article class="users-card users-public-card">
            <header class="users-hero">
                <div class="users-hero__cover">
                    <?php if (!empty($u['cover_image'])): ?><img src="<?= htmlspecialchars($u['cover_image']) ?>" alt=""><?php endif; ?>
                </div>
                <div class="users-hero__inner">
                    <div class="users-avatar users-avatar--xl<?= $isVerified ? ' users-avatar--verified' : '' ?>">
                        <?php if (!empty($u['avatar'])): ?>
                            <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($letter) ?>
                        <?php endif; ?>
                    </div>
                    <div class="users-hero__body">
                        <?php
                        if ($isMasterProfile) {
                            $eyebrowText = __('users.public.eyebrow.master');
                        } elseif (in_array($role, ['admin', 'editor'], true)) {
                            $eyebrowText = ucfirst($role);
                        } else {
                            $eyebrowText = __('users.public.eyebrow.member');
                        }
                        ?>
                        <p class="users-eyebrow"><?= htmlspecialchars($eyebrowText) ?></p>
                        <h1 class="users-hero__name"><?= htmlspecialchars($displayName) ?></h1>
                        <?php if ($isVerified): ?>
                            <div class="users-verified-seal">
                                <svg class="users-verified-seal__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 0 0 1.946-.806 3.42 3.42 0 0 1 4.438 0 3.42 3.42 0 0 0 1.946.806 3.42 3.42 0 0 1 3.138 3.138 3.42 3.42 0 0 0 .806 1.946 3.42 3.42 0 0 1 0 4.438 3.42 3.42 0 0 0-.806 1.946 3.42 3.42 0 0 1-3.138 3.138 3.42 3.42 0 0 0-1.946.806 3.42 3.42 0 0 1-4.438 0 3.42 3.42 0 0 0-1.946-.806 3.42 3.42 0 0 1-3.138-3.138 3.42 3.42 0 0 0-.806-1.946 3.42 3.42 0 0 1 0-4.438 3.42 3.42 0 0 0 .806-1.946 3.42 3.42 0 0 1 3.138-3.138z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span><?= htmlspecialchars($verifiedLabel) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="users-meta-row">
                            <span class="users-pill">@<?= htmlspecialchars($u['username'] ?? '') ?></span>
                            <?php if ($showRolePill): ?><span class="users-pill"><?= htmlspecialchars(ucfirst($role)) ?></span><?php endif; ?>
                            <?php if ($showPrimaryGroupPill): ?><span class="users-pill"><?= htmlspecialchars($primaryGroupName) ?></span><?php endif; ?>
                            <?php if (!empty($u['is_featured'])): ?><span class="users-pill users-pill--accent"><?= __('users.profile.badge.featured') ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <div class="users-public-grid">
                <div class="users-stack">
                    <?php if ($canView && !empty($u['signature'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.signature') ?></p>
                            <p class="users-copy"><?= htmlspecialchars($u['signature']) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if ($canView && $isMasterProfile && !empty($u['artist_note'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.artist_note') ?></p>
                            <p class="users-copy"><?= htmlspecialchars($u['artist_note']) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if ($canView && !empty($u['bio'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.about') ?></p>
                            <p class="users-copy"><?= nl2br(htmlspecialchars($u['bio'])) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if ($canView && !empty($works)): ?>
                        <section class="users-card users-card--soft">
                            <div class="users-card__header">
                                <div>
                                    <p class="users-eyebrow"><?= __('users.public.works') ?></p>
                                    <h2><?= __('users.public.portfolio') ?></h2>
                                </div>
                                <div class="users-actions">
                                    <span class="users-pill"><?= (int)count($works) ?> <?= __('users.public.works_count') ?></span>
                                    <a class="users-button users-button--ghost" href="/users/<?= rawurlencode((string)($u['username'] ?? $u['id'])) ?>/works"><?= __('users.public.all_works') ?></a>
                                </div>
                            </div>
                            <div class="users-works-grid">
                                <?php foreach ($works as $work): ?>
                                    <?php
                                    $workHref = !empty($work['slug'])
                                        ? '/gallery/photo/' . rawurlencode((string)$work['slug'])
                                        : '/gallery/view?id=' . (int)($work['id'] ?? 0);
                                    ?>
                                    <a class="users-work" href="<?= htmlspecialchars($workHref) ?>">
                                        <?php if (!empty($work['path_thumb'])): ?><img src="<?= htmlspecialchars($work['path_thumb']) ?>" alt=""><?php endif; ?>
                                        <span><?= htmlspecialchars($work['title_ru'] ?: ($work['title_en'] ?: __('users.public.work_fallback'))) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($commentsHtml)): ?>
                        <section class="users-profile-comments">
                            <?= $commentsHtml ?>
                        </section>
                    <?php endif; ?>
                </div>

                <aside class="users-stack">
                    <?php if (!empty($viewer) && empty($isOwner)): ?>
                        <section class="users-card users-card--soft users-quick-actions-card">
                            <p class="users-eyebrow"><?= __('users.profile.quick_actions') ?></p>
                            <div class="users-inline-actions users-inline-actions--compact">
                                <form method="post" action="/favorites/toggle" class="users-inline-form">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($favoriteToken ?? '') ?>">
                                    <input type="hidden" name="entity_type" value="user_profile">
                                    <input type="hidden" name="entity_id" value="<?= (int)($u['id'] ?? 0) ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/users/' . ($u['username'] ?? $u['id'])) ?>">
                                    <button class="users-button users-button--fav<?= !empty($isFavorite) ? ' users-button--fav-active' : '' ?>" type="submit">
                                        <?php if (!empty($isFavorite)): ?>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                                            <?= __('users.public.action.remove_favorite') ?>
                                        <?php else: ?>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                            <?= __('users.public.action.add_favorite') ?>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </div>
                        </section>
                    <?php endif; ?>
                    <?php if ($isMasterProfile && !empty($masterContactAvailability['available'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.master_contact.public.eyebrow') ?></p>
                            <h3><?= __('users.master_contact.public.title') ?></h3>
                            <p class="users-copy"><?= __('users.master_contact.public.text') ?></p>
                            <a class="users-button" href="/users/<?= rawurlencode((string)($u['username'] ?? $u['id'])) ?>/contact"><?= __('users.master_contact.public.cta') ?></a>
                        </section>
                    <?php endif; ?>
                    <?php
                    $hasMasterFacts = !empty($u['city']) || !empty($u['studio_name']) || !empty($u['specialization']) || !empty($u['styles']) || !empty($u['experience_years']) || !empty($u['price_from']) || ($isMasterProfile && !empty($u['booking_status']));
                    $hasMemberFacts = !empty($u['city']) || !empty($u['specialization']) || !empty($u['styles']) || !empty($u['studio_name']);
                    ?>
                    <?php if (($isMasterProfile && $hasMasterFacts) || (!$isMasterProfile && $hasMemberFacts)): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('profile') ?></p>
                            <ul class="users-facts">
                                <?php if ($isMasterProfile): ?>
                                    <?php if (!empty($u['city'])): ?><li><span><?= __('users.profile.field.city') ?></span><strong><?= htmlspecialchars((string)$u['city']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['studio_name'])): ?><li><span><?= __('users.public.base') ?></span><strong><?= htmlspecialchars((string)$u['studio_name']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['specialization'])): ?><li><span><?= __('users.profile.field.specialization') ?></span><strong><?= htmlspecialchars($u['specialization']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['styles'])): ?><li><span><?= __('users.profile.field.styles') ?></span><strong><?= htmlspecialchars($u['styles']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['experience_years'])): ?><li><span><?= __('users.profile.field.experience') ?></span><strong><?= (int)$u['experience_years'] ?> <?= __('users.public.years') ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['price_from'])): ?><li><span><?= __('users.public.price_from') ?></span><strong><?= htmlspecialchars((string)$u['price_from']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['booking_status'])): ?><li><span><?= __('users.public.booking') ?></span><strong><?= htmlspecialchars(__('users.booking.' . strtolower((string)$u['booking_status']))) ?></strong></li><?php endif; ?>
                                <?php else: ?>
                                    <?php if (!empty($u['city'])): ?><li><span><?= __('users.profile.field.city') ?></span><strong><?= htmlspecialchars((string)$u['city']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['specialization'])): ?><li><span><?= __('users.public.focus') ?></span><strong><?= htmlspecialchars((string)$u['specialization']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['styles'])): ?><li><span><?= __('users.public.interests') ?></span><strong><?= htmlspecialchars((string)$u['styles']) ?></strong></li><?php endif; ?>
                                    <?php if (!empty($u['studio_name'])): ?><li><span><?= __('users.public.base') ?></span><strong><?= htmlspecialchars((string)$u['studio_name']) ?></strong></li><?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if (!$isMasterProfile && empty($u['bio'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.presence') ?></p>
                            <p class="users-copy"><?= htmlspecialchars($identityStatement) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($u['show_contacts']) && !empty($u['contacts_text'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.contacts') ?></p>
                            <p class="users-copy"><?= nl2br(htmlspecialchars((string)$u['contacts_text'])) ?></p>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($usersSettings['external_links_enabled']) && !empty($u['show_contacts']) && !empty($links)): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.links') ?></p>
                            <ul class="users-links">
                                <?php foreach ($links as $platform => $link): ?>
                                    <?php
                                    $platformSlug = strtolower(trim((string)$platform));
                                    $identifier = (string)($u['username'] ?? $u['id'] ?? '');
                                    $redirectUrl = !empty($usersSettings['forbid_raw_external_links'])
                                        ? '/users/out/' . rawurlencode($identifier) . '/' . rawurlencode($platformSlug)
                                        : (string)$link;
                                    $linkLabel = ucfirst((string)$platform);
                                    $linkHost = (string)(parse_url((string)$link, PHP_URL_HOST) ?? '');
                                    $linkPath = trim((string)(parse_url((string)$link, PHP_URL_PATH) ?? ''), '/');
                                    $linkHint = $linkHost !== '' ? preg_replace('#^www\.#i', '', $linkHost) : '';
                                    if ($linkPath !== '') {
                                        $segments = array_values(array_filter(explode('/', $linkPath)));
                                        if ($segments !== []) {
                                            $tail = implode('/', array_slice($segments, 0, 2));
                                            $linkHint = ($linkHint !== '' ? $linkHint . '/' : '') . $tail;
                                        }
                                    }
                                    ?>
                                    <li>
                                        <a class="users-social-link users-social-link--<?= htmlspecialchars($platformSlug) ?>" href="<?= htmlspecialchars($redirectUrl) ?>" rel="nofollow ugc noopener noreferrer" target="_blank">
                                            <span class="users-social-link__copy">
                                                <span class="users-social-link__label"><?= htmlspecialchars($linkLabel) ?></span>
                                                <?php if ($linkHint !== ''): ?><span class="users-social-link__hint"><?= htmlspecialchars($linkHint) ?></span><?php endif; ?>
                                            </span>
                                            <span class="users-social-link__icon" aria-hidden="true">↗</span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($isMasterProfile && !empty($u['show_ratings']) && !empty($usersSettings['ratings_enabled'])): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.rating') ?></p>
                            <div class="users-rating">
                                <strong><?= htmlspecialchars((string)($ratings['avg'] ?? '0.0')) ?></strong>
                                <span><?= (int)($ratings['count'] ?? 0) ?> <?= __('users.public.ratings_count') ?></span>
                            </div>
                            <?php if (!empty($ratings['latest'])): ?>
                                <ul class="users-list users-list--comments">
                                    <?php foreach ($ratings['latest'] as $rating): ?>
                                        <li>
                                            <strong><?= htmlspecialchars((string)($rating['author_name'] ?? __('users.public.user_fallback'))) ?> · <?= (int)($rating['rating'] ?? 0) ?>/5</strong>
                                            <p><?= htmlspecialchars((string)($rating['review'] ?? '')) ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if (!empty($u['show_favorites']) && !empty($favorites)): ?>
                        <section class="users-card users-card--soft">
                            <p class="users-eyebrow"><?= __('users.public.favorites') ?></p>
                            <ul class="users-links">
                                <?php foreach ($favorites as $favorite): ?>
                                    <li><a href="<?= htmlspecialchars($favorite['url'] ?? '#') ?>"><?= htmlspecialchars($favorite['title'] ?? '') ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </aside>
            </div>
        </article>
    <?php endif; ?>
</section>
