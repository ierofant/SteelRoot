<?php $u = $user ?? []; ?>
<nav class="user-nav-logged">
    <button id="openProfilePanel" class="profile-btn" type="button" aria-label="Profile"
        data-name="<?= htmlspecialchars($u['name'] ?? 'User') ?>"
        data-email="<?= htmlspecialchars($u['email'] ?? '') ?>"
        data-avatar="<?= htmlspecialchars($u['avatar'] ?? '/assets/img/avatar-placeholder.png') ?>"
        data-profile="/profile"
        data-logout="/logout"
        data-token="<?= htmlspecialchars(\Core\Csrf::token('logout')) ?>"
    >
        <img src="<?= htmlspecialchars($u['avatar'] ?? '/assets/img/avatar-placeholder.png') ?>" class="avatar-sm" alt="" width="38" height="38" loading="lazy">
    </button>
</nav>
