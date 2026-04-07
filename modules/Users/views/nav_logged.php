<?php use Core\Asset; ?>
<?php $u = $user ?? []; ?>
<?php $panelFeedHtml = $panelFeedHtml ?? ''; ?>
<nav class="user-nav-logged">
    <button id="openProfilePanel" class="profile-btn" type="button" aria-label="Profile"
        data-name="<?= htmlspecialchars($u['name'] ?? 'User') ?>"
        data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
        data-avatar="<?= htmlspecialchars($u['avatar'] ?? '/assets/img/avatar-placeholder.png') ?>"
        data-profile="/profile"
        data-logout="/logout"
        data-token="<?= htmlspecialchars(\Core\Csrf::token('logout')) ?>"
        data-feed-html="<?= htmlspecialchars($panelFeedHtml) ?>"
    >
        <img src="<?= htmlspecialchars($u['avatar'] ?? '/assets/img/avatar-placeholder.png') ?>" class="avatar-sm" alt="" width="38" height="38" loading="lazy">
    </button>
</nav>
<?php if (!defined('TT_PROFILE_PANEL_SCRIPT_INCLUDED')): ?>
<?php define('TT_PROFILE_PANEL_SCRIPT_INCLUDED', true); ?>
<?= Asset::scriptTag('/assets/js/profile-panel.js', ['defer' => true]) ?>
<?php endif; ?>
