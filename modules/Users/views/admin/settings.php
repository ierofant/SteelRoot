<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$s = $settings ?? [];
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('users.settings.title') ?></p>
            <h3><?= __('users.settings.subtitle') ?></h3>
        </div>
        <div class="users-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/groups"><?= __('users.groups.title') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/plans">Master plans</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users"><?= __('users.settings.back') ?></a>
        </div>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.registration') ?></p>
            <label class="field checkbox">
                <input type="checkbox" name="users_registration_enabled" value="1" <?= !empty($s['registration_enabled']) ? 'checked' : '' ?>>
                <span><?= __('users.settings.registration_enabled') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="users_email_verification_required" value="1" <?= !empty($s['email_verification_required']) ? 'checked' : '' ?>>
                <span><?= __('users.settings.email_verification_required') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="users_auto_login_after_register" value="1" <?= !empty($s['auto_login_after_register']) ? 'checked' : '' ?>>
                <span><?= __('users.settings.auto_login_after_register') ?></span>
            </label>
            <label class="field">
                <span><?= __('users.settings.default_role') ?></span>
                <input type="text" name="users_default_role" value="<?= htmlspecialchars($s['default_role'] ?? '') ?>">
                <small class="muted"><?= __('users.settings.default_role_hint') ?></small>
            </label>
        </div>

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.features') ?></p>
            <div class="users-settings-checks">
            <?php foreach ([
                'extended_profiles_enabled' => 'users.settings.extended_profiles_enabled',
                'groups_enabled' => 'users.settings.groups_enabled',
                'master_profiles_enabled' => 'users.settings.master_profiles_enabled',
                'master_uploads_enabled' => 'users.settings.master_uploads_enabled',
                'verified_masters_only_upload' => 'users.settings.verified_masters_only_upload',
                'favorites_enabled' => 'users.settings.favorites_enabled',
                'ratings_enabled' => 'users.settings.ratings_enabled',
                'reviews_enabled' => 'users.settings.reviews_enabled',
                'profile_comments_enabled' => 'users.settings.profile_comments_enabled',
                'cover_enabled' => 'users.settings.cover_enabled',
                'external_links_enabled' => 'users.settings.external_links_enabled',
                'contacts_enabled' => 'users.settings.contacts_enabled',
                'master_contact_enabled' => 'users.settings.master_contact_enabled',
                'master_plans_enabled' => 'users.settings.master_plans_enabled',
                'manual_plan_assignment_only' => 'users.settings.manual_plan_assignment_only',
                'forbid_raw_external_links' => 'users.settings.forbid_raw_external_links',
                'username_change_disabled' => 'users.settings.username_change_disabled',
            ] as $key => $label): ?>
                <label class="field checkbox">
                    <input type="checkbox" name="users_<?= htmlspecialchars($key) ?>" value="1" <?= !empty($s[$key]) ? 'checked' : '' ?>>
                    <span><?= __($label) ?></span>
                </label>
            <?php endforeach; ?>
            </div>
            <label class="field">
                <span><?= __('users.settings.allowed_social_platforms') ?></span>
                <input type="text" name="users_allowed_social_platforms" value="<?= htmlspecialchars($s['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp') ?>">
            </label>
            <label class="field">
                <span><?= __('users.settings.master_contact_bot_username') ?></span>
                <input type="text" name="users_master_contact_bot_username" value="<?= htmlspecialchars($s['master_contact_bot_username'] ?? '') ?>" placeholder="@tattootodaybot">
            </label>
        </div>

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.moderation') ?></p>
            <div class="users-settings-checks">
            <?php foreach ([
                'master_gallery_moderation' => 'users.settings.master_gallery_moderation',
                'reviews_require_moderation' => 'users.settings.reviews_require_moderation',
                'links_require_approval' => 'users.settings.links_require_approval',
                'master_verification_required' => 'users.settings.master_verification_required',
            ] as $key => $label): ?>
                <label class="field checkbox">
                    <input type="checkbox" name="users_<?= htmlspecialchars($key) ?>" value="1" <?= !empty($s[$key]) ? 'checked' : '' ?>>
                    <span><?= __($label) ?></span>
                </label>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.email') ?></p>
            <div class="grid three">
                <label class="field">
                    <span><?= __('users.settings.email_blacklist') ?></span>
                    <textarea name="users_email_blacklist" rows="4"><?= htmlspecialchars($s['email_blacklist'] ?? '') ?></textarea>
                    <small class="muted"><?= __('users.settings.email_blacklist_hint') ?></small>
                </label>
                <label class="field">
                    <span><?= __('users.settings.email_domain_blacklist') ?></span>
                    <textarea name="users_email_domain_blacklist" rows="4"><?= htmlspecialchars($s['email_domain_blacklist'] ?? '') ?></textarea>
                    <small class="muted"><?= __('users.settings.email_domain_blacklist_hint') ?></small>
                </label>
                <label class="field">
                    <span><?= __('users.settings.email_domain_whitelist') ?></span>
                    <textarea name="users_email_domain_whitelist" rows="4"><?= htmlspecialchars($s['email_domain_whitelist'] ?? '') ?></textarea>
                    <small class="muted"><?= __('users.settings.email_domain_whitelist_hint') ?></small>
                </label>
            </div>
        </div>

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.password') ?></p>
            <div class="grid two">
                <label class="field">
                    <span><?= __('users.settings.username_min_length') ?></span>
                    <input type="number" name="users_username_min_length" value="<?= (int)($s['username_min_length'] ?? 3) ?>">
                </label>
                <label class="field">
                    <span><?= __('users.settings.username_max_length') ?></span>
                    <input type="number" name="users_username_max_length" value="<?= (int)($s['username_max_length'] ?? 32) ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('users.settings.password_min_length') ?></span>
                    <input type="number" name="users_password_min_length" value="<?= (int)($s['password_min_length'] ?? 8) ?>">
                </label>
            </div>
            <label class="field checkbox">
                <input type="checkbox" name="users_password_require_numbers" value="1" <?= !empty($s['password_require_numbers']) ? 'checked' : '' ?>>
                <span><?= __('users.settings.password_require_numbers') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="users_password_require_special" value="1" <?= !empty($s['password_require_special']) ? 'checked' : '' ?>>
                <span><?= __('users.settings.password_require_special') ?></span>
            </label>
        </div>

        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.settings.section.abuse') ?></p>
            <label class="field">
                <span><?= __('users.settings.registration_rate_limit') ?></span>
                <input type="number" name="users_registration_rate_limit" value="<?= (int)($s['registration_rate_limit'] ?? 5) ?>">
                <small class="muted"><?= __('users.settings.registration_rate_limit_hint') ?></small>
            </label>
            <label class="field">
                <span><?= __('users.settings.blocked_ips') ?></span>
                <textarea name="users_blocked_ips" rows="4"><?= htmlspecialchars($s['blocked_ips'] ?? '') ?></textarea>
                <small class="muted"><?= __('users.settings.blocked_ips_hint') ?></small>
            </label>
        </div>

        <div class="form-actions users-admin-actions">
            <button type="submit" class="btn primary"><?= __('users.settings.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users"><?= __('users.settings.cancel') ?></a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$flash = $flash ?? null;
include __DIR__ . '/../../../Admin/views/layout.php';
