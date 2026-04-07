<?php
$user = $user ?? null;
$activeTab = (string)($activeTab ?? 'overview');
$displayGroups = array_values(array_filter((array)($groups ?? []), static function (array $group): bool {
    $slug = strtolower(trim((string)($group['slug'] ?? '')));
    return $slug !== '' && !in_array($slug, ['user', 'master', 'verified_master'], true);
}));
?>
<header class="users-hero">
    <div class="users-hero__cover">
        <?php if (!empty($user['cover_image'])): ?><img src="<?= htmlspecialchars((string)$user['cover_image']) ?>" alt=""><?php endif; ?>
    </div>
    <div class="users-hero__inner">
        <div class="users-avatar users-avatar--xl">
            <?php if (!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars((string)$user['avatar']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(function_exists('mb_strtoupper') && function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)($user['name'] ?? 'U'), 0, 1)) : strtoupper(substr((string)($user['name'] ?? 'U'), 0, 1))) ?>
            <?php endif; ?>
        </div>
        <div class="users-hero__body users-hero__body--dashboard">
            <p class="users-eyebrow"><?= __('users.profile.account') ?></p>
            <h1 class="users-hero__title"><?= htmlspecialchars((string)($user['display_name'] ?? ($user['name'] ?? ''))) ?></h1>
            <p class="users-hero__subtitle">@<?= htmlspecialchars((string)($user['username'] ?? '')) ?></p>
            <div class="users-meta-row users-meta-row--hero">
                <?php if (!empty($user['is_verified'])): ?>
                    <span class="users-pill users-pill--accent"><?= __('users.profile.badge.verified_master') ?></span>
                <?php elseif (!empty($user['is_master'])): ?>
                    <span class="users-pill"><?= __('users.profile.badge.master_profile') ?></span>
                <?php endif; ?>
                <?php foreach ($displayGroups as $group): ?>
                    <span class="users-pill"><?= htmlspecialchars((string)($group['name'] ?? '')) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($user['signature'])): ?>
                <p class="users-signature-line"><?= htmlspecialchars((string)$user['signature']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>

<nav class="users-tabs" aria-label="Profile sections">
    <a class="users-tab<?= $activeTab === 'overview' ? ' is-active' : '' ?>" href="/profile?tab=overview"><?= __('users.profile.tab.overview') ?></a>
    <a class="users-tab<?= $activeTab === 'settings' ? ' is-active' : '' ?>" href="/profile?tab=settings"><?= __('users.profile.tab.settings') ?></a>
    <a class="users-tab<?= $activeTab === 'community' ? ' is-active' : '' ?>" href="/profile?tab=community"><?= __('users.profile.tab.community') ?></a>
    <a class="users-tab<?= $activeTab === 'collections' ? ' is-active' : '' ?>" href="/profile?tab=collections"><?= __('users.profile.tab.collections') ?></a>
    <a class="users-tab<?= $activeTab === 'activity' ? ' is-active' : '' ?>" href="/profile?tab=activity"><?= __('users.profile.tab.activity') ?></a>
    <a class="users-tab<?= $activeTab === 'my_requests' ? ' is-active' : '' ?>" href="/profile/my-requests"><?= __('users.master_contact.client.short') ?></a>
    <?php if (!empty($user['is_master'])): ?>
        <a class="users-tab<?= $activeTab === 'master_requests' ? ' is-active' : '' ?>" href="/profile/master-requests"><?= __('users.master_contact.inbox.short') ?></a>
    <?php endif; ?>
    <?php if (!empty($canManageMasterWorks)): ?>
        <a class="users-tab users-tab--ghost" href="/profile/works"><?= __('users.master_works.open') ?></a>
    <?php endif; ?>
</nav>
