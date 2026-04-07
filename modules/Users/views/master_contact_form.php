<?php
\Core\Slot::register('head_end', static function (): string {
    return \Core\Asset::styleTag('/modules/Users/assets/css/users.css') . "\n";
});
$master = $master ?? [];
$message = trim((string)($message ?? ''));
$error = trim((string)($error ?? ''));
?>
<section class="users-shell users-dashboard">
    <?php if ($message !== ''): ?><div class="users-alert users-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="users-alert users-alert--danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="users-grid">
        <section class="users-card users-overview-stack">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.master_contact.public.eyebrow') ?></p>
                    <h1><?= __('users.master_contact.form.title') ?></h1>
                    <p class="users-muted"><?= __('users.master_contact.form.subtitle') ?></p>
                </div>
                <a class="users-button users-button--ghost" href="/users/<?= rawurlencode((string)($master['username'] ?? $master['id'])) ?>"><?= __('users.master_contact.form.back') ?></a>
            </div>

            <form method="post" enctype="multipart/form-data" class="users-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($contactToken ?? '')) ?>">
                <div class="users-form-grid">
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.client_name') ?></span>
                        <input type="text" name="client_name" value="<?= htmlspecialchars((string)($prefillName ?? '')) ?>" required>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.client_contact') ?></span>
                        <input type="text" name="client_contact" value="<?= htmlspecialchars((string)($prefillContact ?? '')) ?>" required>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.preferred_contact_method') ?></span>
                        <input type="text" name="preferred_contact_method" placeholder="<?= htmlspecialchars((string)__('users.master_contact.placeholder.preferred_contact_method')) ?>">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.city') ?></span>
                        <input type="text" name="city">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.body_placement') ?></span>
                        <input type="text" name="body_placement">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.approx_size') ?></span>
                        <input type="text" name="approx_size">
                    </label>
                    <label class="users-field users-field--full">
                        <span><?= __('users.master_contact.field.request_summary') ?></span>
                        <input type="text" name="request_summary" required>
                    </label>
                    <label class="users-field users-field--full">
                        <span><?= __('users.master_contact.field.description') ?></span>
                        <textarea name="description" rows="6" placeholder="<?= htmlspecialchars((string)__('users.master_contact.placeholder.description')) ?>"></textarea>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.budget') ?></span>
                        <input type="text" name="budget">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_contact.field.target_date') ?></span>
                        <input type="text" name="target_date">
                    </label>
                    <label class="users-field users-field--full">
                        <span><?= __('users.master_contact.field.extra_notes') ?></span>
                        <textarea name="extra_notes" rows="4"></textarea>
                    </label>
                    <label class="users-field users-field--full">
                        <span><?= __('users.master_contact.field.references') ?></span>
                        <input type="file" name="references[]" accept="image/jpeg,image/png,image/webp" multiple>
                        <small class="users-muted"><?= __('users.master_contact.field.references_hint') ?></small>
                    </label>
                    <label class="users-field users-field--full">
                        <span class="users-check">
                            <input type="checkbox" name="coverup_flag" value="1">
                            <span><?= __('users.master_contact.field.coverup_flag') ?></span>
                        </span>
                    </label>
                </div>
                <div class="users-actions">
                    <button type="submit" class="users-button"><?= __('users.master_contact.form.submit') ?></button>
                    <a class="users-button users-button--ghost" href="/users/<?= rawurlencode((string)($master['username'] ?? $master['id'])) ?>"><?= __('users.master_contact.form.cancel') ?></a>
                </div>
            </form>
        </section>

        <aside class="users-stack">
            <section class="users-card users-card--soft">
                <p class="users-eyebrow"><?= __('users.master_contact.form.master') ?></p>
                <h3><?= htmlspecialchars((string)($master['display_name'] ?? ($master['name'] ?? ''))) ?></h3>
                <ul class="users-facts">
                    <?php if (!empty($master['city'])): ?><li><span><?= __('users.profile.field.city') ?></span><strong><?= htmlspecialchars((string)$master['city']) ?></strong></li><?php endif; ?>
                    <?php if (!empty($master['styles'])): ?><li><span><?= __('users.profile.field.styles') ?></span><strong><?= htmlspecialchars((string)$master['styles']) ?></strong></li><?php endif; ?>
                    <?php if (!empty($master['booking_status'])): ?><li><span><?= __('users.public.booking') ?></span><strong><?= htmlspecialchars(__('users.booking.' . strtolower((string)$master['booking_status']))) ?></strong></li><?php endif; ?>
                </ul>
            </section>
        </aside>
    </div>
</section>
