<?php
\Core\Slot::register('head_end', static function (): string {
    return \Core\Asset::styleTag('/modules/Users/assets/css/users.css') . "\n";
});
$items = $items ?? [];
$selected = $selected ?? null;
$contactSettings = $contactSettings ?? [];
$currentStatus = (string)($currentStatus ?? '');
$message = (string)($message ?? '');
$error = (string)($error ?? '');
?>
<section class="users-shell users-dashboard users-master-requests">
    <?php include APP_ROOT . '/modules/Users/views/partials/dashboard_header.php'; ?>
    <?php if ($message === 'settings'): ?><div class="users-alert users-alert--success"><?= __('users.master_contact.flash.settings_saved') ?></div><?php endif; ?>
    <?php if ($message === 'status'): ?><div class="users-alert users-alert--success"><?= __('users.master_contact.flash.status_saved') ?></div><?php endif; ?>
    <?php if ($error === 'telegram'): ?><div class="users-alert users-alert--danger"><?= __('users.master_contact.flash.telegram_missing') ?></div><?php endif; ?>
    <?php if ($error === 'status'): ?><div class="users-alert users-alert--danger"><?= __('users.master_contact.flash.status_error') ?></div><?php endif; ?>
    <?php if ($error !== '' && !in_array($error, ['telegram', 'status'], true)): ?><div class="users-alert users-alert--danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="users-grid">
        <section class="users-card users-overview-stack">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.master_contact.inbox.eyebrow') ?></p>
                    <h1><?= __('users.master_contact.inbox.title') ?></h1>
                    <p class="users-muted"><?= __('users.master_contact.inbox.subtitle') ?></p>
                </div>
                <div class="users-actions users-request-filters">
                    <?php foreach ($statuses as $status): ?>
                        <a class="users-button users-button--ghost<?= $currentStatus === $status ? ' is-active' : '' ?>" href="/profile/master-requests?status=<?= rawurlencode((string)$status) ?>"><?= htmlspecialchars(__('users.master_contact.status.' . $status)) ?></a>
                    <?php endforeach; ?>
                    <a class="users-button users-button--ghost<?= $currentStatus === '' ? ' is-active' : '' ?>" href="/profile/master-requests"><?= __('users.master_contact.inbox.all') ?></a>
                </div>
            </div>

            <div class="users-dashboard-grid users-request-layout">
                <div class="users-card users-card--soft users-request-list-card">
                    <?php if ($items === []): ?>
                        <p class="users-muted"><?= __('users.master_contact.inbox.empty') ?></p>
                    <?php else: ?>
                        <ul class="users-list users-list--comments users-request-list">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <strong><a href="/profile/master-requests/<?= (int)$item['id'] ?>"><?= htmlspecialchars((string)($item['client_name'] ?? '')) ?></a></strong>
                                    <span class="users-pill"><?= htmlspecialchars(__('users.master_contact.status.' . strtolower((string)($item['status'] ?? 'new')))) ?></span>
                                    <p><?= htmlspecialchars((string)($item['request_summary'] ?? '')) ?></p>
                                    <small class="users-muted"><?= htmlspecialchars((string)($item['created_at'] ?? '')) ?> · <?= (int)($item['files_count'] ?? 0) ?> <?= __('users.master_contact.inbox.files') ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="users-card users-card--soft users-request-detail">
                    <?php if (!$selected): ?>
                        <p class="users-muted"><?= __('users.master_contact.inbox.select') ?></p>
                    <?php else: ?>
                        <div class="users-card__header">
                            <div>
                                <p class="users-eyebrow"><?= __('users.master_contact.inbox.request') ?> #<?= (int)$selected['id'] ?></p>
                                <h3><?= htmlspecialchars((string)($selected['client_name'] ?? '')) ?></h3>
                            </div>
                            <span class="users-pill"><?= htmlspecialchars(__('users.master_contact.status.' . strtolower((string)($selected['status'] ?? 'new')))) ?></span>
                        </div>
                        <ul class="users-facts">
                            <li><span><?= __('users.master_contact.field.client_name') ?></span><strong><?= htmlspecialchars((string)($selected['client_name'] ?? '')) ?></strong></li>
                            <li><span><?= __('users.master_contact.field.client_contact') ?></span><strong><?= htmlspecialchars((string)($selected['client_contact'] ?? '')) ?></strong></li>
                            <?php if (!empty($selected['preferred_contact_method'])): ?><li><span><?= __('users.master_contact.field.preferred_contact_method') ?></span><strong><?= htmlspecialchars((string)$selected['preferred_contact_method']) ?></strong></li><?php endif; ?>
                            <?php if (!empty($selected['city'])): ?><li><span><?= __('users.master_contact.field.city') ?></span><strong><?= htmlspecialchars((string)$selected['city']) ?></strong></li><?php endif; ?>
                            <?php if (!empty($selected['body_placement'])): ?><li><span><?= __('users.master_contact.field.body_placement') ?></span><strong><?= htmlspecialchars((string)$selected['body_placement']) ?></strong></li><?php endif; ?>
                            <?php if (!empty($selected['approx_size'])): ?><li><span><?= __('users.master_contact.field.approx_size') ?></span><strong><?= htmlspecialchars((string)$selected['approx_size']) ?></strong></li><?php endif; ?>
                            <?php if (!empty($selected['budget'])): ?><li><span><?= __('users.master_contact.field.budget') ?></span><strong><?= htmlspecialchars((string)$selected['budget']) ?></strong></li><?php endif; ?>
                            <?php if (!empty($selected['target_date'])): ?><li><span><?= __('users.master_contact.field.target_date') ?></span><strong><?= htmlspecialchars((string)$selected['target_date']) ?></strong></li><?php endif; ?>
                            <li><span><?= __('users.master_contact.field.coverup_flag') ?></span><strong><?= !empty($selected['coverup_flag']) ? __('users.common.yes') : __('users.common.no') ?></strong></li>
                        </ul>
                        <?php if (!empty($selected['request_summary'])): ?><p class="users-copy"><strong><?= htmlspecialchars((string)$selected['request_summary']) ?></strong></p><?php endif; ?>
                        <?php if (!empty($selected['description'])): ?><p class="users-copy"><?= nl2br(htmlspecialchars((string)$selected['description'])) ?></p><?php endif; ?>
                        <?php if (!empty($selected['extra_notes'])): ?><p class="users-copy"><?= nl2br(htmlspecialchars((string)$selected['extra_notes'])) ?></p><?php endif; ?>

                        <?php if (!empty($selected['files'])): ?>
                            <div class="users-works-grid">
                                <?php foreach ($selected['files'] as $file): ?>
                                    <a class="users-work" href="/profile/master-requests/files/<?= (int)($file['id'] ?? 0) ?>" target="_blank" rel="noopener">
                                        <?php if (!empty($file['is_image'])): ?><img src="/profile/master-requests/files/<?= (int)($file['id'] ?? 0) ?>" alt=""><?php endif; ?>
                                        <span><?= htmlspecialchars((string)($file['original_name'] ?? 'file')) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="/profile/master-requests/<?= (int)$selected['id'] ?>/status" class="users-form">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($statusToken ?? '')) ?>">
                            <label class="users-field">
                                <span><?= __('users.master_contact.inbox.change_status') ?></span>
                                <select name="status">
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= htmlspecialchars((string)$status) ?>" <?= strtolower((string)($selected['status'] ?? 'new')) === $status ? 'selected' : '' ?>><?= htmlspecialchars(__('users.master_contact.status.' . $status)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit" class="users-button"><?= __('users.master_contact.inbox.save_status') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <aside class="users-stack">
            <section class="users-card users-card--soft users-request-settings">
                <p class="users-eyebrow"><?= __('users.master_contact.settings.title') ?></p>
                <form method="post" action="/profile/master-contact-settings" class="users-form">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($settingsToken ?? '')) ?>">
                    <label class="users-field users-check users-check--stacked">
                        <input type="checkbox" name="accept_requests" value="1" <?= !empty($contactSettings['accept_requests']) ? 'checked' : '' ?>>
                        <span><?= __('users.master_contact.settings.accept_requests') ?></span>
                    </label>
                    <label class="users-field users-check users-check--stacked">
                        <input type="checkbox" name="show_contact_cta" value="1" <?= !empty($contactSettings['show_contact_cta']) ? 'checked' : '' ?>>
                        <span><?= __('users.master_contact.settings.show_contact_cta') ?></span>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.settings.notification_email') ?></span>
                        <input type="email" name="notification_email" value="<?= htmlspecialchars((string)($contactSettings['notification_email'] ?? '')) ?>">
                    </label>
                    <fieldset class="users-field">
                        <span><?= __('users.master_contact.settings.notification_channel') ?></span>
                        <label class="users-check users-check--stacked">
                            <input type="radio" name="notification_channel" value="email" <?= empty($contactSettings['telegram_notifications_enabled']) ? 'checked' : '' ?>>
                            <span><?= __('users.master_contact.settings.notification_channel_email') ?></span>
                        </label>
                        <label class="users-check users-check--stacked">
                            <input type="radio" name="notification_channel" value="telegram" <?= !empty($contactSettings['telegram_notifications_enabled']) ? 'checked' : '' ?> <?= empty($contactSettings['telegram_bound_at']) ? 'disabled' : '' ?>>
                            <span><?= __('users.master_contact.settings.notification_channel_telegram') ?></span>
                        </label>
                    </fieldset>
                    <label class="users-field">
                        <span><?= __('users.master_contact.settings.auto_reply_text') ?></span>
                        <textarea name="auto_reply_text" rows="4"><?= htmlspecialchars((string)($contactSettings['auto_reply_text'] ?? '')) ?></textarea>
                    </label>
                    <button type="submit" class="users-button"><?= __('users.master_contact.settings.save') ?></button>
                </form>
            </section>

            <section class="users-card users-card--soft users-request-settings">
                <p class="users-eyebrow"><?= __('users.master_contact.settings.telegram') ?></p>
                <?php if (!empty($contactSettings['telegram_bound_at'])): ?>
                    <p class="users-copy"><?= __('users.master_contact.settings.telegram_bound') ?><?= !empty($contactSettings['telegram_username']) ? ' @' . htmlspecialchars((string)$contactSettings['telegram_username']) : '' ?></p>
                <?php else: ?>
                    <p class="users-copy"><?= __('users.master_contact.settings.telegram_hint') ?></p>
                <?php endif; ?>
                <div class="users-actions">
                    <form method="post" action="/profile/master-contact-settings/telegram-bind">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($telegramBindToken ?? '')) ?>">
                        <button type="submit" class="users-button users-button--ghost"><?= __('users.master_contact.settings.telegram_bind') ?></button>
                    </form>
                    <?php if (!empty($contactSettings['telegram_bound_at'])): ?>
                        <form method="post" action="/profile/master-contact-settings/telegram-unbind">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($telegramUnbindToken ?? '')) ?>">
                            <button type="submit" class="users-button users-button--ghost"><?= __('users.master_contact.settings.telegram_unbind') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </aside>
    </div>
</section>
