<?php
\Core\Slot::register('head_end', static function (): string {
    return \Core\Asset::styleTag('/modules/Users/assets/css/users.css') . "\n";
});
if (isset($this) && method_exists($this, 'setSection')) {
    $this->setSection(
        'footer_scripts',
        \Core\Asset::scriptTag('/modules/Users/assets/js/profile-preview.js', ['defer' => true])
    );
}
?>
<?php $user = $user ?? null; ?>
<?php $ratings = $ratings ?? ['avg' => null, 'count' => 0, 'latest' => []]; ?>
<?php $activeTab = $activeTab ?? 'overview'; ?>
<?php $collectionsAvailable = !empty($collectionsAvailable); ?>
<?php $collections = $collections ?? []; ?>
<?php $currentCollection = $currentCollection ?? null; ?>
<?php $collectionItems = $collectionItems ?? []; ?>
<?php
$collectionInitial = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return 'S';
    }
    return function_exists('mb_substr') ? mb_substr($value, 0, 1) : substr($value, 0, 1);
};
?>
<section class="users-shell users-dashboard">
    <?php include APP_ROOT . '/modules/Users/views/partials/dashboard_header.php'; ?>

    <?php if (!empty($error)): ?><div class="users-alert users-alert--danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'updated'): ?><div class="users-alert users-alert--success"><?= __('users.profile.flash.updated') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'avatar'): ?><div class="users-alert users-alert--success"><?= __('users.profile.flash.avatar') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'cover'): ?><div class="users-alert users-alert--success"><?= __('users.profile.flash.cover') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'collection-created'): ?><div class="users-alert users-alert--success"><?= __('users.collections.flash.created') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'collection-deleted'): ?><div class="users-alert users-alert--success"><?= __('users.collections.flash.deleted') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'collection-item-removed'): ?><div class="users-alert users-alert--success"><?= __('users.collections.flash.item_removed') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'collection-saved'): ?><div class="users-alert users-alert--success"><?= __('users.collections.flash.saved') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'community-poll-submitted'): ?><div class="users-alert users-alert--success"><?= __('users.community_poll.flash.submitted') ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'community-poll-exists'): ?><div class="users-alert users-alert--success"><?= __('users.community_poll.flash.exists') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'collection-create-failed'): ?><div class="users-alert users-alert--danger"><?= __('users.collections.flash.create_failed') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'collection-save-failed'): ?><div class="users-alert users-alert--danger"><?= __('users.collections.flash.save_failed_selected') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'community-poll-invalid'): ?><div class="users-alert users-alert--danger"><?= __('users.community_poll.flash.invalid') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'community-poll-other-required'): ?><div class="users-alert users-alert--danger"><?= __('users.community_poll.flash.other_required') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'community-poll-rate-limit'): ?><div class="users-alert users-alert--danger"><?= __('users.community_poll.flash.rate_limit') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'community-poll-inactive'): ?><div class="users-alert users-alert--danger"><?= __('users.community_poll.flash.inactive') ?></div><?php endif; ?>
    <?php if (($error ?? '') === 'community-poll-not-ready'): ?><div class="users-alert users-alert--danger"><?= __('users.community_poll.flash.not_ready') ?></div><?php endif; ?>

    <?php if ($activeTab === 'overview'): ?>
    <div class="users-grid">
        <section class="users-card users-overview-stack">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.profile.tab.overview') ?></p>
                    <h2><?= __('users.profile.overview.title') ?></h2>
                </div>
                <div class="users-actions">
                    <a class="users-button users-button--ghost" href="/users/<?= rawurlencode((string)($user['username'] ?? $user['id'])) ?>"><?= __('users.profile.action.open_public') ?></a>
                    <a class="users-button" href="/profile?tab=settings"><?= __('users.profile.action.edit_profile') ?></a>
                </div>
            </div>
            <div class="users-stat-grid">
                <div><strong><?= (int)count($favorites ?? []) ?></strong><span><?= __('users.public.favorites') ?></span></div>
                <div><strong><?= (int)count($works ?? []) ?></strong><span><?= __('users.public.works') ?></span></div>
                <div><strong><?= (int)($ratings['count'] ?? 0) ?></strong><span><?= __('users.public.rating') ?></span></div>
            </div>
            <div class="users-overview-grid">
                <section class="users-card users-card--soft">
                    <p class="users-eyebrow"><?= __('profile') ?></p>
                    <ul class="users-facts">
                        <li><span><?= __('users.profile.field.name') ?></span><strong><?= htmlspecialchars((string)($user['display_name'] ?? ($user['name'] ?? ''))) ?></strong></li>
                        <li><span><?= __('users.profile.field.username') ?></span><strong>@<?= htmlspecialchars((string)($user['username'] ?? '')) ?></strong></li>
                        <?php if (!empty($user['city'])): ?><li><span><?= __('users.profile.field.city') ?></span><strong><?= htmlspecialchars((string)$user['city']) ?></strong></li><?php endif; ?>
                        <?php if (!empty($user['specialization'])): ?><li><span><?= __('users.profile.field.specialization') ?></span><strong><?= htmlspecialchars((string)$user['specialization']) ?></strong></li><?php endif; ?>
                        <li><span><?= __('users.profile.field.visibility') ?></span><strong><?= htmlspecialchars(__('users.visibility.' . strtolower((string)($user['profile_visibility'] ?? 'public')))) ?></strong></li>
                    </ul>
                </section>
                <section class="users-card users-card--soft">
                    <p class="users-eyebrow"><?= __('users.profile.quick_actions') ?></p>
                    <div class="users-quick-actions">
                        <a class="users-button" href="/profile/avatar/editor"><?= __('users.profile.action.change_avatar') ?></a>
                        <a class="users-button users-button--ghost" href="/profile?tab=settings"><?= __('users.profile.action.edit_settings') ?></a>
                        <a class="users-button users-button--ghost" href="/profile/my-requests"><?= __('users.master_contact.client.short') ?></a>
                        <?php if (!empty($user['is_master'])): ?>
                            <a class="users-button users-button--ghost" href="/profile/master-requests"><?= __('users.master_contact.inbox.short') ?></a>
                        <?php endif; ?>
                        <?php if (!empty($canManageMasterWorks)): ?>
                            <a class="users-button users-button--ghost" href="/profile/works"><?= __('users.master_works.open') ?></a>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <?php if (!empty($works)): ?>
                <section class="users-card users-card--soft">
                    <div class="users-card__header">
                        <div>
                            <p class="users-eyebrow">Works</p>
                            <h3><?= __('users.profile.recent_uploads') ?></h3>
                        </div>
                    </div>
                    <div class="users-works-grid">
                        <?php foreach ($works as $work): ?>
                            <a class="users-work" href="/gallery/photo/<?= rawurlencode((string)($work['slug'] ?? '')) ?>">
                                <?php if (!empty($work['path_thumb'])): ?><img src="<?= htmlspecialchars((string)$work['path_thumb']) ?>" alt=""><?php endif; ?>
                                <span><?= htmlspecialchars((string)($work['title_ru'] ?: ($work['title_en'] ?: ('#' . $work['id'])))) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </section>

        <aside class="users-stack">
            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.public.favorites') ?></p>
                <?php if (!empty($favorites)): ?>
                    <ul class="users-list">
                        <?php foreach (array_slice((array)$favorites, 0, 5) as $favorite): ?>
                            <li><a href="<?= htmlspecialchars($favorite['url'] ?? '#') ?>"><?= htmlspecialchars($favorite['title'] ?? '') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted"><?= __('users.profile.empty.favorites') ?></p>
                <?php endif; ?>
            </section>

            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.profile.recent_comments') ?></p>
                <?php if (!empty($myComments)): ?>
                    <ul class="users-list users-list--comments">
                        <?php foreach (array_slice((array)$myComments, 0, 4) as $comment): ?>
                            <li>
                                <strong><?= htmlspecialchars((string)($comment['entity_label'] ?? ($comment['entity_type'] ?? 'comment'))) ?></strong>
                                <?php if (!empty($comment['entity_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$comment['entity_url']) ?>"><?= htmlspecialchars((string)($comment['entity_title'] ?? __('users.profile.action.open'))) ?></a>
                                <?php endif; ?>
                                <p><?= htmlspecialchars((string)($comment['body'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted"><?= __('users.profile.empty.comments') ?></p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
    <?php elseif ($activeTab === 'community'): ?>
    <div class="users-grid">
        <section class="users-card users-overview-stack">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.profile.tab.community') ?></p>
                    <h2><?= __('users.community_poll.title') ?></h2>
                </div>
            </div>
            <?php include APP_ROOT . '/modules/Users/views/partials/community_poll_card.php'; ?>
        </section>

        <aside class="users-stack">
            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.community_poll.highlight_title') ?></p>
                <p class="users-muted"><?= __('users.community_poll.description') ?></p>
            </section>
        </aside>
    </div>
    <?php elseif ($activeTab === 'settings'): ?>
    <div class="users-grid">
        <section class="users-card">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.profile.identity') ?></p>
                    <h2><?= __('users.profile.identity_title') ?></h2>
                </div>
                <a class="users-button users-button--ghost" href="/profile/avatar/editor"><?= __('users.profile.action.change_avatar') ?></a>
            </div>
            <?php if (!empty($usersSettings['cover_enabled'])): ?>
                <section class="users-cover-manager">
                    <div class="users-card__header">
                        <div>
                            <p class="users-eyebrow"><?= __('users.profile.cover') ?></p>
                            <h3><?= __('users.profile.cover_title') ?></h3>
                        </div>
                    </div>
                    <div class="users-cover-manager__preview">
                        <?php if (!empty($user['cover_image'])): ?>
                            <img src="<?= htmlspecialchars((string)$user['cover_image']) ?>" alt="">
                        <?php else: ?>
                            <div class="users-cover-manager__placeholder"><?= __('users.profile.cover_placeholder') ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="/profile/cover" enctype="multipart/form-data" class="users-cover-manager__form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($coverToken ?? '') ?>">
                        <label class="users-field">
                            <span><?= __('users.profile.cover_upload') ?></span>
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required>
                        </label>
                        <button class="users-button" type="submit"><?= __('users.profile.cover_upload_submit') ?></button>
                    </form>
                </section>
            <?php endif; ?>
            <form method="POST" action="/profile/update" class="users-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="_tab" value="settings">
                <div class="users-form-grid">
                    <label class="users-field">
                        <span><?= __('users.profile.field.name') ?></span>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.display_name') ?></span>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($user['display_name'] ?? '') ?>">
                    </label>
                    <div class="users-field">
                        <span><?= __('users.profile.field.username') ?></span>
                        <div class="users-static-value">@<?= htmlspecialchars($user['username'] ?? '') ?></div>
                    </div>
                    <label class="users-field">
                        <span><?= __('users.profile.field.email') ?></span>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.city') ?></span>
                        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.studio') ?></span>
                        <input type="text" name="studio_name" value="<?= htmlspecialchars($user['studio_name'] ?? '') ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.specialization') ?></span>
                        <input type="text" name="specialization" value="<?= htmlspecialchars($user['specialization'] ?? '') ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.styles') ?></span>
                        <input type="text" name="styles" value="<?= htmlspecialchars($user['styles'] ?? '') ?>" placeholder="blackwork, realism, lettering">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.experience') ?></span>
                        <input type="number" min="0" max="80" name="experience_years" value="<?= (int)($user['experience_years'] ?? 0) ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.price_from') ?></span>
                        <input type="text" name="price_from" value="<?= htmlspecialchars($user['price_from'] ?? '') ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.booking_status') ?></span>
                        <select name="booking_status">
                            <?php foreach (['open', 'busy', 'closed'] as $value): ?>
                                <option value="<?= htmlspecialchars($value) ?>"<?= ($user['booking_status'] ?? 'open') === $value ? ' selected' : '' ?>><?= htmlspecialchars(__('users.booking.' . $value)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.visibility') ?></span>
                        <select name="profile_visibility">
                            <?php foreach (($visibilityOptions ?? ['public','private']) as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"<?= ($user['profile_visibility'] ?? 'public') === $opt ? ' selected' : '' ?>><?= htmlspecialchars(__('users.visibility.' . $opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="users-field">
                    <span><?= __('users.profile.field.cover_image_url') ?></span>
                    <input type="text" name="cover_image" value="<?= htmlspecialchars($user['cover_image'] ?? '') ?>" placeholder="/storage/uploads/users/covers/123.jpg">
                </label>
                <?php if (!empty($canManagePhotoCopyright)): ?>
                    <section class="users-watermark-manager" data-watermark-preview data-enabled="<?= !empty($user['photo_copyright_enabled']) ? '1' : '0' ?>">
                        <div class="users-card__header">
                            <div>
                                <p class="users-eyebrow"><?= __('users.profile.verified_mark') ?></p>
                                <h3><?= __('users.profile.watermark.title') ?></h3>
                            </div>
                        </div>
                        <div class="users-form-grid">
                            <label class="users-check">
                                <input type="checkbox" name="photo_copyright_enabled" value="1" <?= !empty($user['photo_copyright_enabled']) ? 'checked' : '' ?>>
                                <span><?= __('users.profile.watermark.enable') ?></span>
                            </label>
                            <div class="users-watermark-preview__status" data-watermark-preview-state></div>
                        </div>
                        <div class="users-form-grid">
                            <label class="users-field">
                                <span><?= __('users.profile.watermark.text') ?></span>
                                <input type="text" name="photo_copyright_text" maxlength="120" value="<?= htmlspecialchars((string)($user['photo_copyright_text'] ?? '')) ?>" placeholder="@Mult">
                            </label>
                            <label class="users-field">
                                <span><?= __('users.profile.watermark.font') ?></span>
                                <select name="photo_copyright_font">
                                    <?php foreach (($photoCopyrightFonts ?? []) as $fontKey => $fontLabel): ?>
                                        <option value="<?= htmlspecialchars((string)$fontKey) ?>"<?= (($user['photo_copyright_font'] ?? 'oswald') === $fontKey) ? ' selected' : '' ?>><?= htmlspecialchars((string)$fontLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="users-form-grid">
                            <label class="users-field">
                                <span><?= __('users.profile.watermark.color') ?></span>
                                <?php $wmColor = (string)($user['photo_copyright_color'] ?? ($photoCopyrightDefaultColor ?? '#f8f0eb')); ?>
                                <div class="users-color-field">
                                    <input type="color" value="<?= htmlspecialchars($wmColor) ?>" data-watermark-color-picker>
                                    <input type="text" name="photo_copyright_color" value="<?= htmlspecialchars($wmColor) ?>" placeholder="#f8f0eb" data-watermark-color-input>
                                </div>
                            </label>
                        </div>
                        <div class="users-watermark-preview">
                            <div class="users-watermark-preview__canvas">
                                <span class="users-watermark-preview__mark font-<?= htmlspecialchars((string)($user['photo_copyright_font'] ?? 'oswald')) ?>" style="color: <?= htmlspecialchars($wmColor) ?>;" data-watermark-preview-text><?= htmlspecialchars((string)(($user['photo_copyright_text'] ?? '') !== '' ? $user['photo_copyright_text'] : '@artist')) ?></span>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <label class="users-field">
                    <span><?= __('users.profile.field.statement') ?></span>
                    <textarea name="bio" rows="5" placeholder="<?= htmlspecialchars(__('users.profile.placeholder.statement')) ?>"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </label>
                <?php if (!empty($user['is_master'])): ?>
                    <label class="users-field">
                        <span><?= __('users.profile.field.artist_note') ?></span>
                        <textarea name="artist_note" maxlength="280" rows="3" placeholder="<?= htmlspecialchars(__('users.profile.placeholder.artist_note')) ?>"><?= htmlspecialchars($user['artist_note'] ?? '') ?></textarea>
                    </label>
                <?php endif; ?>
                <label class="users-field">
                    <span><?= __('users.public.signature') ?></span>
                    <textarea name="signature" maxlength="300" rows="3" placeholder="<?= htmlspecialchars(__('users.profile.placeholder.signature')) ?>"><?= htmlspecialchars($user['signature'] ?? '') ?></textarea>
                </label>
                <label class="users-field">
                    <span><?= __('users.public.contacts') ?></span>
                    <textarea name="contacts_text" rows="3"><?= htmlspecialchars($user['contacts_text'] ?? '') ?></textarea>
                </label>
                <?php $socialLinks = json_decode((string)($user['external_links_json'] ?? ''), true); if (!is_array($socialLinks)) { $socialLinks = []; } ?>
                <div class="users-form-grid">
                    <label class="users-field"><span><?= __('users.social.telegram') ?></span><input type="text" name="social_telegram" value="<?= htmlspecialchars((string)($socialLinks['telegram'] ?? '')) ?>" placeholder="https://t.me/username"></label>
                    <label class="users-field"><span><?= __('users.social.vk') ?></span><input type="text" name="social_vk" value="<?= htmlspecialchars((string)($socialLinks['vk'] ?? '')) ?>" placeholder="https://vk.com/username"></label>
                    <label class="users-field"><span><?= __('users.social.instagram') ?></span><input type="text" name="social_instagram" value="<?= htmlspecialchars((string)($socialLinks['instagram'] ?? '')) ?>" placeholder="https://instagram.com/username"></label>
                    <label class="users-field"><span><?= __('users.social.youtube') ?></span><input type="text" name="social_youtube" value="<?= htmlspecialchars((string)($socialLinks['youtube'] ?? '')) ?>" placeholder="https://youtube.com/..."></label>
                    <label class="users-field"><span><?= __('users.social.tiktok') ?></span><input type="text" name="social_tiktok" value="<?= htmlspecialchars((string)($socialLinks['tiktok'] ?? '')) ?>" placeholder="https://tiktok.com/@username"></label>
                    <label class="users-field"><span><?= __('users.social.whatsapp') ?></span><input type="text" name="social_whatsapp" value="<?= htmlspecialchars((string)($socialLinks['whatsapp'] ?? '')) ?>" placeholder="https://wa.me/123456789"></label>
                </div>
                <div class="users-check-grid">
                    <label class="users-check"><input type="checkbox" name="show_contacts" value="1" <?= !empty($user['show_contacts']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_contacts') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_favorites" value="1" <?= !empty($user['show_favorites']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_favorites') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_comments" value="1" <?= !empty($user['show_comments']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_comments') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_ratings" value="1" <?= !empty($user['show_ratings']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_ratings') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_works" value="1" <?= !empty($user['show_works']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_works') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_personal_feed" value="1" <?= !array_key_exists('show_personal_feed', $user) || !empty($user['show_personal_feed']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_personal_feed') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_personal_feed_works" value="1" <?= !array_key_exists('show_personal_feed_works', $user) || !empty($user['show_personal_feed_works']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_personal_feed_works') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="show_personal_feed_masters" value="1" <?= !array_key_exists('show_personal_feed_masters', $user) || !empty($user['show_personal_feed_masters']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.show_personal_feed_masters') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="comments_moderation" value="1" <?= !empty($user['comments_moderation']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.moderate_comments') ?></span></label>
                    <label class="users-check"><input type="checkbox" name="hide_online_status" value="1" <?= !empty($user['hide_online_status']) ? 'checked' : '' ?>><span><?= __('users.profile.toggle.hide_online_status') ?></span></label>
                    <?php if (!empty($usersSettings['master_profiles_enabled'])): ?>
                        <label class="users-check"><input type="checkbox" name="is_master" value="1" <?= !empty($user['is_master']) ? 'checked' : '' ?>><span><?= __('users.profile.badge.master_profile') ?></span></label>
                    <?php endif; ?>
                </div>
                <?php if (!empty($canManageMasterWorks)): ?>
                    <div class="users-actions">
                        <a class="users-button" href="/profile/works"><?= __('users.master_works.open') ?></a>
                    </div>
                <?php endif; ?>
                <div class="users-form-grid">
                    <label class="users-field">
                        <span><?= __('users.profile.field.new_password') ?></span>
                        <input type="password" name="password" autocomplete="new-password">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.profile.field.confirm_password') ?></span>
                        <input type="password" name="password_confirm" autocomplete="new-password">
                    </label>
                </div>
                <div class="users-actions">
                    <button class="users-button" type="submit"><?= __('users.profile.action.save_profile') ?></button>
                    <a class="users-button users-button--ghost" href="/users/<?= rawurlencode((string)($user['username'] ?? $user['id'])) ?>"><?= __('users.profile.action.open_public') ?></a>
                    <a class="users-button users-button--ghost" href="/logout" onclick="event.preventDefault();document.getElementById('logout-form').submit();"><?= __('users.profile.action.logout') ?></a>
                </div>
            </form>
            <form id="logout-form" method="POST" action="/logout" class="users-hidden">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($logoutToken ?? '') ?>">
            </form>
        </section>

        <aside class="users-stack">
            <?php if (!empty($verifiedMasterFaqItems)): ?>
                <section class="users-card users-faq-card">
                    <div class="users-card__header">
                        <div>
                            <p class="users-eyebrow">Verified master guide</p>
                            <h3>Подробное ЧАВО по мастер-профилю</h3>
                        </div>
                    </div>
                    <p class="users-copy users-copy--muted">Этот блок виден только подтверждённым мастерам и помогает держать профиль, портфолио и публичную витрину в рабочем состоянии.</p>
                    <div class="users-faq-list">
                        <?php foreach ((array)$verifiedMasterFaqItems as $faqItem): ?>
                            <details class="users-faq-item">
                                <summary><?= htmlspecialchars((string)($faqItem['question'] ?? '')) ?></summary>
                                <div class="users-faq-item__body">
                                    <?php foreach ((array)($faqItem['answer'] ?? []) as $paragraph): ?>
                                        <p><?= htmlspecialchars((string)$paragraph) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="users-card">
                        <p class="users-eyebrow"><?= __('users.profile.stats') ?></p>
                <div class="users-stat-grid">
                    <div><strong><?= (int)count($favorites ?? []) ?></strong><span><?= __('users.public.favorites') ?></span></div>
                    <div><strong><?= (int)count($works ?? []) ?></strong><span><?= __('users.public.works') ?></span></div>
                    <div><strong><?= (int)($ratings['count'] ?? 0) ?></strong><span><?= __('users.public.rating') ?></span></div>
                </div>
            </section>

            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.public.favorites') ?></p>
                <?php if (!empty($favorites)): ?>
                    <ul class="users-list">
                        <?php foreach ($favorites as $favorite): ?>
                            <li>
                                <a href="<?= htmlspecialchars($favorite['url'] ?? '#') ?>"><?= htmlspecialchars($favorite['title'] ?? '') ?></a>
                                <form method="post" action="/favorites/toggle">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($favoriteToken ?? '') ?>">
                                    <input type="hidden" name="entity_type" value="<?= htmlspecialchars($favorite['entity_type'] ?? '') ?>">
                                    <input type="hidden" name="entity_id" value="<?= (int)($favorite['entity_id'] ?? 0) ?>">
                                    <input type="hidden" name="return_to" value="/profile">
                                    <button class="users-inline-button" type="submit"><?= __('users.collections.action.remove') ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted"><?= __('users.profile.empty.favorites') ?></p>
                <?php endif; ?>
            </section>

            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.profile.my_comments') ?></p>
                <?php if (!empty($myComments)): ?>
                    <ul class="users-list users-list--comments">
                        <?php foreach ($myComments as $comment): ?>
                            <li>
                                <strong><?= htmlspecialchars((string)($comment['entity_label'] ?? ($comment['entity_type'] ?? 'comment'))) ?></strong>
                                <span class="users-muted"><?= htmlspecialchars((string)($comment['status'] ?? '')) ?></span>
                                <?php if (!empty($comment['entity_url'])): ?>
                                    <a href="<?= htmlspecialchars((string)$comment['entity_url']) ?>"><?= htmlspecialchars((string)($comment['entity_title'] ?? 'Open')) ?></a>
                                <?php else: ?>
                                    <span class="users-muted"><?= htmlspecialchars((string)($comment['entity_title'] ?? ('#' . ($comment['entity_id'] ?? 0)))) ?></span>
                                <?php endif; ?>
                                <p><?= htmlspecialchars((string)($comment['body'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted"><?= __('users.profile.empty.comments') ?></p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
    <?php elseif ($activeTab === 'collections'): ?>
    <div class="users-grid">
        <section class="users-card">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.profile.tab.collections') ?></p>
                    <h2><?= __('users.collections.title') ?></h2>
                </div>
            </div>
            <?php if ($collectionsAvailable): ?>
                <div class="users-collections-shell">
                    <section class="users-card users-card--soft">
                        <div class="users-card__header">
                            <div>
                                <p class="users-eyebrow"><?= __('users.collections.create') ?></p>
                                <h3><?= __('users.collections.new_collection') ?></h3>
                            </div>
                        </div>
                        <form method="post" action="/profile/collections/create" class="users-form">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($collectionToken ?? '') ?>">
                            <label class="users-field">
                                <span><?= __('users.collections.field.title') ?></span>
                                <input type="text" name="title" maxlength="160" placeholder="<?= htmlspecialchars(__('users.collections.placeholder.title')) ?>" required>
                            </label>
                            <label class="users-field">
                                <span><?= __('users.collections.field.description') ?></span>
                                <textarea name="description" rows="3" placeholder="<?= htmlspecialchars(__('users.collections.placeholder.description')) ?>"></textarea>
                            </label>
                            <div class="users-actions">
                                <button class="users-button" type="submit"><?= __('users.collections.action.create') ?></button>
                            </div>
                        </form>
                    </section>

                    <section class="users-card users-card--soft">
                        <div class="users-card__header">
                            <div>
                                <p class="users-eyebrow"><?= __('users.collections.library') ?></p>
                                <h3><?= __('users.collections.your_collections') ?></h3>
                            </div>
                        </div>
                        <?php if (!empty($collections)): ?>
                            <div class="users-collection-list">
                                <?php foreach ($collections as $collection): ?>
                                    <a class="users-collection-pill<?= !empty($currentCollection) && (int)$currentCollection['id'] === (int)$collection['id'] ? ' is-active' : '' ?>" href="/profile?tab=collections&collection=<?= (int)$collection['id'] ?>">
                                        <span><?= htmlspecialchars((string)($collection['title'] ?? 'Collection')) ?></span>
                                        <strong><?= (int)($collection['items_count'] ?? 0) ?></strong>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="users-muted"><?= __('users.collections.empty.library') ?></p>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($currentCollection)): ?>
                        <section class="users-card users-card--soft">
                            <div class="users-card__header">
                                <div>
                                    <p class="users-eyebrow"><?= __('users.collections.current') ?></p>
                                    <h3><?= htmlspecialchars((string)($currentCollection['title'] ?? __('users.collections.collection_fallback'))) ?></h3>
                                </div>
                                <form method="post" action="/profile/collections/<?= (int)$currentCollection['id'] ?>/delete" onsubmit="return confirm('<?= htmlspecialchars(__('users.collections.confirm_delete')) ?>');">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($collectionToken ?? '') ?>">
                                    <button class="users-inline-button" type="submit"><?= __('users.collections.action.delete') ?></button>
                                </form>
                            </div>
                            <?php if (!empty($currentCollection['description'])): ?>
                                <p class="users-copy users-copy--muted"><?= htmlspecialchars((string)$currentCollection['description']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($collectionItems)): ?>
                                <div class="users-collection-items">
                                    <?php foreach ($collectionItems as $collectionItem): ?>
                                        <article class="users-collection-card">
                                            <a class="users-collection-card__media" href="<?= htmlspecialchars((string)($collectionItem['url'] ?? '#')) ?>">
                                                <?php if (!empty($collectionItem['thumb'])): ?>
                                                    <img src="<?= htmlspecialchars((string)$collectionItem['thumb']) ?>" alt="">
                                                <?php else: ?>
                                                    <span><?= htmlspecialchars($collectionInitial((string)($collectionItem['title'] ?? 'Saved'))) ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <div class="users-collection-card__body">
                                                <p class="users-eyebrow"><?= htmlspecialchars(__('users.collections.entity.' . strtolower((string)($collectionItem['entity_type'] ?? 'item')))) ?></p>
                                                <a class="users-collection-card__title" href="<?= htmlspecialchars((string)($collectionItem['url'] ?? '#')) ?>"><?= htmlspecialchars((string)($collectionItem['title'] ?? __('users.collections.saved_item'))) ?></a>
                                                <?php if (!empty($collectionItem['subtitle'])): ?>
                                                    <p class="users-muted"><?= htmlspecialchars((string)$collectionItem['subtitle']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <form method="post" action="/profile/collections/<?= (int)$currentCollection['id'] ?>/items/remove/<?= (int)$collectionItem['id'] ?>">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($collectionToken ?? '') ?>">
                                                <button class="users-inline-button" type="submit"><?= __('users.collections.action.remove') ?></button>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="users-muted"><?= __('users.collections.empty.current') ?></p>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="users-muted"><?= __('users.collections.unavailable') ?></p>
            <?php endif; ?>
        </section>

        <aside class="users-stack">
            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.profile.tab.collections') ?></p>
                <div class="users-stat-grid">
                    <div><strong><?= (int)count($collections) ?></strong><span><?= __('users.collections.stat.boards') ?></span></div>
                    <div><strong><?= !empty($currentCollection) ? (int)($currentCollection['items_count'] ?? 0) : 0 ?></strong><span><?= __('users.collections.stat.items') ?></span></div>
                    <div><strong><?= !empty($collectionItems) ? (int)count($collectionItems) : 0 ?></strong><span><?= __('users.collections.stat.visible_now') ?></span></div>
                </div>
            </section>

            <section class="users-card">
                <p class="users-eyebrow"><?= __('users.collections.how_it_works') ?></p>
                <p class="users-copy users-copy--muted"><?= __('users.collections.how_it_works_text') ?></p>
            </section>
        </aside>
    </div>
    <?php else: ?>
    <div class="users-grid">
        <section class="users-card users-activity-card">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.profile.tab.activity') ?></p>
                    <h2><?= __('users.profile.activity_title') ?></h2>
                </div>
            </div>
            <div class="users-activity-panels">
            <section class="users-card users-card--soft users-activity-panel">
                <div class="users-activity-panel__head">
                    <p class="users-eyebrow"><?= __('users.public.favorites') ?></p>
                    <span class="users-activity-panel__count"><?= (int)count($favorites ?? []) ?></span>
                </div>
                <?php if (!empty($favorites)): ?>
                    <ul class="users-list users-list--activity">
                        <?php foreach ($favorites as $favorite): ?>
                            <li>
                                <div class="users-list__main">
                                    <a href="<?= htmlspecialchars($favorite['url'] ?? '#') ?>"><?= htmlspecialchars($favorite['title'] ?? '') ?></a>
                                    <?php if (!empty($favorite['entity_type'])): ?>
                                        <span class="users-list__meta"><?= htmlspecialchars((string)$favorite['entity_type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="/favorites/toggle">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($favoriteToken ?? '') ?>">
                                    <input type="hidden" name="entity_type" value="<?= htmlspecialchars($favorite['entity_type'] ?? '') ?>">
                                    <input type="hidden" name="entity_id" value="<?= (int)($favorite['entity_id'] ?? 0) ?>">
                                    <input type="hidden" name="return_to" value="/profile?tab=activity">
                                    <button class="users-inline-button" type="submit"><?= __('users.collections.action.remove') ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted users-muted--panel"><?= __('users.profile.empty.favorites') ?></p>
                <?php endif; ?>
            </section>

            <section class="users-card users-card--soft users-activity-panel">
                <div class="users-activity-panel__head">
                    <p class="users-eyebrow"><?= __('users.profile.my_comments') ?></p>
                    <span class="users-activity-panel__count"><?= (int)count($myComments ?? []) ?></span>
                </div>
                <?php if (!empty($myComments)): ?>
                    <ul class="users-list users-list--comments">
                        <?php foreach ($myComments as $comment): ?>
                            <li>
                                <div class="users-list__main">
                                    <strong><?= htmlspecialchars((string)($comment['entity_type'] ?? 'comment')) ?></strong>
                                    <span class="users-list__meta"><?= htmlspecialchars((string)($comment['status'] ?? '')) ?></span>
                                </div>
                                <p><?= htmlspecialchars((string)($comment['body'] ?? '')) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="users-muted users-muted--panel"><?= __('users.profile.empty.comments') ?></p>
                <?php endif; ?>
            </section>
            </div>
        </section>

        <aside class="users-stack">
            <section class="users-card users-activity-stats">
                <p class="users-eyebrow"><?= __('users.profile.stats') ?></p>
                <div class="users-stat-grid">
                    <div><strong><?= (int)count($favorites ?? []) ?></strong><span><?= __('users.public.favorites') ?></span></div>
                    <div><strong><?= (int)count($works ?? []) ?></strong><span><?= __('users.public.works') ?></span></div>
                    <div><strong><?= (int)($ratings['count'] ?? 0) ?></strong><span><?= __('users.public.rating') ?></span></div>
                </div>
            </section>
        </aside>
    </div>
    <?php endif; ?>
</section>
