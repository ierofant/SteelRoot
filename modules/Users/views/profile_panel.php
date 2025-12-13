<?php
$user = $user ?? null;
$name = $user['name'] ?? 'User';
$email = $user['email'] ?? '';
$avatar = $user['avatar'] ?? '/assets/img/avatar-placeholder.png';
?>
<div id="profilePanelBackdrop" class="profile-panel-backdrop"></div>
<div id="profilePanel" class="profile-panel">
    <button id="closeProfilePanel" class="profile-panel-close" aria-label="Close">×</button>
    <div class="profile-panel-header">
        <div class="profile-panel-avatar" style="background-image:url('<?= htmlspecialchars($avatar) ?>');"></div>
        <div class="profile-panel-meta">
            <div class="profile-panel-name"><?= htmlspecialchars($name) ?></div>
            <?php if ($email): ?><div class="profile-panel-email"><?= htmlspecialchars($email) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="profile-panel-actions">
        <a href="/profile" class="profile-panel-btn">Профиль</a>
        <form method="POST" action="/logout">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('logout')) ?>">
            <button type="submit" class="profile-panel-btn ghost">Выйти</button>
        </form>
    </div>
</div>
