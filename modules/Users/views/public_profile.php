<?php
    $u = $user ?? [];
    $restricted = !empty($restricted);
    $letter = strtoupper(substr($u['name'] ?? ($username ?? 'U'), 0, 1));
    $canView = !empty($canViewDetails);
?>
<section class="public-profile">
    <?php if ($restricted): ?>
        <div class="restricted">
            <h1>Profile is private</h1>
            <p>User <?= htmlspecialchars($username ?? ($u['username'] ?? '')) ?> has chosen to hide profile information.</p>
        </div>
    <?php else: ?>
        <div class="public-profile-row">
            <div class="avatar">
                <?php if (!empty($u['avatar'])): ?>
                    <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="<?= htmlspecialchars($u['name'] ?? 'User') ?>">
                <?php else: ?>
                    <?= htmlspecialchars($letter) ?>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="u-m-0"><?= htmlspecialchars($u['name'] ?? 'User') ?></h1>
                <div class="pill"><?= htmlspecialchars(ucfirst($u['role'] ?? 'user')) ?></div>
                <div class="muted">@<?= htmlspecialchars($u['username'] ?? '') ?></div>
            </div>
        </div>
        <?php if ($canView && !empty($u['signature'])): ?>
            <div class="signature"><?= htmlspecialchars($u['signature']) ?></div>
        <?php endif; ?>
    <?php endif; ?>
</section>
