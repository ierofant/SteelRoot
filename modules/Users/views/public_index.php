<?php
$users = $users ?? [];
$page = max(1, (int)($page ?? 1));
$pages = max(1, (int)($pages ?? 1));
$totalUsers = (int)($totalUsers ?? count($users));
$baseUrl = '/users';

$pageHref = static function (int $targetPage) use ($baseUrl): string {
    return $targetPage > 1 ? ($baseUrl . '?page=' . $targetPage) : $baseUrl;
};

$windowStart = max(1, $page - 2);
$windowEnd = min($pages, $page + 2);
if (($windowEnd - $windowStart) < 4) {
    if ($windowStart === 1) {
        $windowEnd = min($pages, 5);
    } elseif ($windowEnd === $pages) {
        $windowStart = max(1, $pages - 4);
    }
}
?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users.css') ?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users-directory.css') ?>
<section class="users-shell users-directory">
    <article class="users-card users-card--soft users-directory-hero">
        <div class="users-card__header">
            <div>
                <p class="users-eyebrow"><?= __('users.directory.eyebrow') ?></p>
                <h1><?= __('users.directory.title') ?></h1>
                <p class="users-copy users-directory-copy"><?= __('users.directory.subtitle') ?></p>
            </div>
            <div class="users-actions">
                <span class="users-pill"><?= $totalUsers ?> <?= __('users.directory.count') ?></span>
            </div>
        </div>
    </article>

    <?php if ($users !== []): ?>
        <div class="users-directory-grid">
            <?php foreach ($users as $u): ?>
                <?php
                $displayName = trim((string)($u['display_name'] ?? ($u['name'] ?? __('users.public.user_fallback'))));
                $letter = strtoupper(mb_substr($displayName !== '' ? $displayName : 'U', 0, 1));
                $identifier = (string)($u['username'] ?? $u['id'] ?? '');
                $profileUrl = '/users/' . rawurlencode($identifier);
                $summary = trim((string)($u['signature'] ?? ''));
                if ($summary === '') {
                    $summary = trim((string)($u['artist_note'] ?? ''));
                }
                if ($summary === '') {
                    $summary = trim((string)($u['bio'] ?? ''));
                }
                if ($summary === '') {
                    $parts = array_values(array_filter([
                        trim((string)($u['specialization'] ?? '')),
                        trim((string)($u['styles'] ?? '')),
                        trim((string)($u['city'] ?? '')),
                    ]));
                    $summary = implode(' • ', array_slice($parts, 0, 2));
                }
                if ($summary !== '') {
                    $summary = mb_substr($summary, 0, 150);
                    if (mb_strlen($summary) >= 150) {
                        $summary .= '...';
                    }
                }
                ?>
                <article class="users-card users-card--soft users-directory-card">
                    <a class="users-directory-card__cover" href="<?= htmlspecialchars($profileUrl) ?>">
                        <?php if (!empty($u['cover_image'])): ?>
                            <img src="<?= htmlspecialchars((string)$u['cover_image']) ?>" alt="">
                        <?php else: ?>
                            <span class="users-directory-card__cover-fallback"></span>
                        <?php endif; ?>
                    </a>
                    <div class="users-directory-card__body">
                        <a class="users-avatar users-directory-card__avatar" href="<?= htmlspecialchars($profileUrl) ?>">
                            <?php if (!empty($u['avatar'])): ?>
                                <img src="<?= htmlspecialchars((string)$u['avatar']) ?>" alt="">
                            <?php else: ?>
                                <?= htmlspecialchars($letter) ?>
                            <?php endif; ?>
                        </a>
                        <div class="users-directory-card__meta">
                            <div class="users-directory-card__head">
                                <div>
                                    <h2><a href="<?= htmlspecialchars($profileUrl) ?>"><?= htmlspecialchars($displayName) ?></a></h2>
                                    <?php if (!empty($u['username'])): ?><p class="users-directory-card__handle">@<?= htmlspecialchars((string)$u['username']) ?></p><?php endif; ?>
                                </div>
                                <div class="users-meta-row">
                                    <?php if (!empty($u['is_master'])): ?><span class="users-pill"><?= __('users.public.eyebrow.master') ?></span><?php endif; ?>
                                    <?php if (!empty($u['is_verified'])): ?><span class="users-pill users-pill--accent"><?= __('users.profile.badge.verified_master') ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="users-meta-row users-directory-card__facts">
                                <?php if (!empty($u['city'])): ?><span class="users-pill"><?= htmlspecialchars((string)$u['city']) ?></span><?php endif; ?>
                                <?php if (!empty($u['specialization'])): ?><span class="users-pill"><?= htmlspecialchars((string)$u['specialization']) ?></span><?php endif; ?>
                                <?php if ((int)($u['works_count'] ?? 0) > 0): ?><span class="users-pill"><?= (int)$u['works_count'] ?> <?= __('users.public.works_count') ?></span><?php endif; ?>
                            </div>
                            <?php if ($summary !== ''): ?>
                                <p class="users-copy users-directory-card__summary"><?= htmlspecialchars($summary) ?></p>
                            <?php endif; ?>
                            <div class="users-actions">
                                <a class="users-button users-button--ghost" href="<?= htmlspecialchars($profileUrl) ?>"><?= __('users.directory.open_profile') ?></a>
                                <?php if ((int)($u['works_count'] ?? 0) > 0): ?>
                                    <a class="users-button users-button--ghost" href="<?= htmlspecialchars($profileUrl . '/works') ?>"><?= __('users.public.all_works') ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="users-pagination" aria-label="<?= htmlspecialchars((string)__('users.directory.pagination')) ?>">
                <?php if ($page > 1): ?>
                    <a class="users-button users-button--ghost" href="<?= htmlspecialchars($pageHref($page - 1)) ?>"><?= __('users.directory.prev') ?></a>
                <?php endif; ?>

                <div class="users-pagination__pages">
                    <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                        <a class="users-pagination__link<?= $i === $page ? ' is-active' : '' ?>" href="<?= htmlspecialchars($pageHref($i)) ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $pages): ?>
                    <a class="users-button users-button--ghost" href="<?= htmlspecialchars($pageHref($page + 1)) ?>"><?= __('users.directory.next') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <article class="users-card users-card--soft">
            <p class="users-copy"><?= __('users.directory.empty') ?></p>
        </article>
    <?php endif; ?>
</section>
