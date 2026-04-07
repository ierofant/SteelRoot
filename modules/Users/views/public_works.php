<?php
$u = $user ?? [];
$works = $works ?? [];
$profileUrl = $profileUrl ?? '/users';
$baseUrl = $baseUrl ?? $profileUrl . '/works';
$page = max(1, (int)($page ?? 1));
$pages = max(1, (int)($pages ?? 1));
$totalWorks = (int)($totalWorks ?? count($works));
?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users.css') ?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users-public-works.css') ?>
<section class="users-shell users-public users-public-works">
    <article class="users-card users-card--soft">
        <div class="users-card__header">
            <div>
                <p class="users-eyebrow">Portfolio</p>
                <h1><?= htmlspecialchars((string)($u['display_name'] ?? ($u['name'] ?? 'User'))) ?>: all works</h1>
            </div>
            <div class="users-actions">
                <span class="users-pill"><?= $totalWorks ?> works</span>
                <a class="users-button users-button--ghost" href="<?= htmlspecialchars($profileUrl) ?>">Back to profile</a>
            </div>
        </div>

        <?php if (!empty($works)): ?>
            <div class="users-works-grid">
                <?php foreach ($works as $work): ?>
                    <?php
                    $workHref = !empty($work['slug'])
                        ? '/gallery/photo/' . rawurlencode((string)$work['slug'])
                        : '/gallery/view?id=' . (int)($work['id'] ?? 0);
                    ?>
                    <a class="users-work" href="<?= htmlspecialchars($workHref) ?>">
                        <?php if (!empty($work['path_thumb'])): ?>
                            <img src="<?= htmlspecialchars((string)$work['path_thumb']) ?>" alt="">
                        <?php elseif (!empty($work['path_medium'])): ?>
                            <img src="<?= htmlspecialchars((string)$work['path_medium']) ?>" alt="">
                        <?php endif; ?>
                        <span><?= htmlspecialchars((string)($work['title_ru'] ?: ($work['title_en'] ?: ('#' . ($work['id'] ?? 0))))) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="users-pagination" aria-label="Works pagination">
                    <?php if ($page > 1): ?>
                        <a class="users-button users-button--ghost" href="<?= htmlspecialchars($baseUrl . '?page=' . ($page - 1)) ?>">Previous</a>
                    <?php endif; ?>
                    <span class="users-pill">Page <?= $page ?> / <?= $pages ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="users-button users-button--ghost" href="<?= htmlspecialchars($baseUrl . '?page=' . ($page + 1)) ?>">Next</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <p class="users-muted">No published works yet.</p>
        <?php endif; ?>
    </article>
</section>
