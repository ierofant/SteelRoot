<?php
\Core\Slot::register('head_end', static function (): string {
    return \Core\Asset::styleTag('/modules/Users/assets/css/users.css') . "\n";
});
$items          = $items ?? [];
$selected       = $selected ?? null;
$currentStatus  = (string)($currentStatus ?? '');
$message        = trim((string)($message ?? ''));
$error          = trim((string)($error ?? ''));
?>
<section class="users-shell users-dashboard users-client-requests">
    <?php include APP_ROOT . '/modules/Users/views/partials/dashboard_header.php'; ?>

    <?php if ($message !== ''): ?>
        <div class="users-alert users-alert--success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="users-alert users-alert--danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="users-grid">
        <section class="users-card users-overview-stack">

            <!-- ── Header ── -->
            <div class="users-card__header req-page-header">
                <div class="req-page-title">
                    <p class="users-eyebrow"><?= __('users.master_contact.client.eyebrow') ?></p>
                    <h1><?= __('users.master_contact.client.title') ?></h1>
                    <p class="users-muted"><?= __('users.master_contact.client.subtitle') ?></p>
                </div>
                <nav class="req-filters" aria-label="Filter requests">
                    <a class="req-filter<?= $currentStatus === '' ? ' is-active' : '' ?>"
                       href="/profile/my-requests"><?= __('users.master_contact.inbox.all') ?></a>
                    <?php foreach ($statuses as $status): ?>
                        <a class="req-filter<?= $currentStatus === $status ? ' is-active' : '' ?>"
                           href="/profile/my-requests?status=<?= rawurlencode((string)$status) ?>"
                           data-status="<?= htmlspecialchars((string)$status) ?>"><?= htmlspecialchars(__('users.master_contact.status.' . $status)) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- ── Two-column layout ── -->
            <div class="users-dashboard-grid users-request-layout">

                <!-- List -->
                <div class="users-card users-card--soft users-request-list-card">
                    <?php if ($items === []): ?>
                        <div class="req-empty">
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <rect x="8" y="6" width="32" height="36" rx="4" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M16 16h16M16 22h16M16 28h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <p><?= __('users.master_contact.client.empty') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="users-list users-list--comments users-request-list">
                            <?php foreach ($items as $item):
                                $isActive   = $selected && (int)$selected['id'] === (int)$item['id'];
                                $itemStatus = strtolower((string)($item['status'] ?? 'new'));
                                $masterName = (string)($item['master_display_name'] ?: ($item['master_name'] ?? ''));
                            ?>
                                <li class="req-item<?= $isActive ? ' is-active' : '' ?>">
                                    <a href="/profile/my-requests/<?= (int)$item['id'] ?>" class="req-item__link">
                                        <div class="req-item__top">
                                            <strong class="req-item__master"><?= htmlspecialchars($masterName) ?></strong>
                                            <span class="users-pill req-status-pill" data-status="<?= htmlspecialchars($itemStatus) ?>"><?= htmlspecialchars(__('users.master_contact.status.' . $itemStatus)) ?></span>
                                        </div>
                                        <?php if (!empty($item['request_summary'])): ?>
                                            <p class="req-item__summary"><?= htmlspecialchars((string)$item['request_summary']) ?></p>
                                        <?php endif; ?>
                                        <div class="req-item__meta">
                                            <time><?= htmlspecialchars((string)($item['created_at'] ?? '')) ?></time>
                                            <?php if ((int)($item['files_count'] ?? 0) > 0): ?>
                                                <span class="req-item__files"><?= (int)$item['files_count'] ?> <?= __('users.master_contact.inbox.files') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Detail -->
                <div class="users-card users-card--soft users-request-detail">
                    <?php if (!$selected): ?>
                        <div class="req-empty req-empty--detail">
                            <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
                                <path d="M10 12h28M10 20h20M10 28h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="34" cy="34" r="8" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M31 34h6M34 31v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                            <p><?= __('users.master_contact.client.select') ?></p>
                        </div>
                    <?php else:
                        $masterName = (string)($selected['master_display_name'] ?: ($selected['master_name'] ?? ''));
                        $selStatus  = strtolower((string)($selected['status'] ?? 'new'));
                    ?>
                        <!-- Detail: header -->
                        <div class="req-detail-head">
                            <div>
                                <p class="users-eyebrow"><?= __('users.master_contact.inbox.request') ?> #<?= (int)$selected['id'] ?></p>
                                <h3 class="req-detail-master"><?= htmlspecialchars($masterName) ?></h3>
                            </div>
                            <span class="users-pill req-status-pill req-status-pill--lg"
                                  data-status="<?= htmlspecialchars($selStatus) ?>"><?= htmlspecialchars(__('users.master_contact.status.' . $selStatus)) ?></span>
                        </div>

                        <!-- Detail: facts -->
                        <ul class="req-facts">
                            <li>
                                <span><?= __('users.master_contact.field.client_name') ?></span>
                                <strong><?= htmlspecialchars((string)($selected['client_name'] ?? '')) ?></strong>
                            </li>
                            <li>
                                <span><?= __('users.master_contact.field.client_contact') ?></span>
                                <strong><?= htmlspecialchars((string)($selected['client_contact'] ?? '')) ?></strong>
                            </li>
                            <?php if (!empty($selected['city'])): ?>
                            <li>
                                <span><?= __('users.master_contact.field.city') ?></span>
                                <strong><?= htmlspecialchars((string)$selected['city']) ?></strong>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($selected['body_placement'])): ?>
                            <li>
                                <span><?= __('users.master_contact.field.body_placement') ?></span>
                                <strong><?= htmlspecialchars((string)$selected['body_placement']) ?></strong>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($selected['approx_size'])): ?>
                            <li>
                                <span><?= __('users.master_contact.field.approx_size') ?></span>
                                <strong><?= htmlspecialchars((string)$selected['approx_size']) ?></strong>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($selected['preferred_contact_method'])): ?>
                            <li>
                                <span><?= __('users.master_contact.field.preferred_contact_method') ?></span>
                                <strong><?= htmlspecialchars((string)$selected['preferred_contact_method']) ?></strong>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <!-- Detail: description -->
                        <?php if (!empty($selected['request_summary']) || !empty($selected['description']) || !empty($selected['extra_notes'])): ?>
                            <div class="req-body">
                                <?php if (!empty($selected['request_summary'])): ?>
                                    <p class="req-body__summary"><?= htmlspecialchars((string)$selected['request_summary']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selected['description'])): ?>
                                    <p class="req-body__text"><?= nl2br(htmlspecialchars((string)$selected['description'])) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($selected['extra_notes'])): ?>
                                    <p class="req-body__notes"><?= nl2br(htmlspecialchars((string)$selected['extra_notes'])) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Detail: files -->
                        <?php if (!empty($selected['files'])): ?>
                            <div class="req-files">
                                <p class="users-eyebrow req-files__label"><?= __('users.master_contact.inbox.files') ?> (<?= count($selected['files']) ?>)</p>
                                <div class="req-files-grid">
                                    <?php foreach ($selected['files'] as $file): ?>
                                        <a class="req-file"
                                           href="/profile/my-requests/files/<?= (int)($file['id'] ?? 0) ?>"
                                           target="_blank" rel="noopener">
                                            <?php if (!empty($file['is_image'])): ?>
                                                <img src="/profile/my-requests/files/<?= (int)($file['id'] ?? 0) ?>"
                                                     alt="<?= htmlspecialchars((string)($file['original_name'] ?? '')) ?>">
                                            <?php else: ?>
                                                <div class="req-file__icon">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                                        <polyline points="14 2 14 8 20 8"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <span class="req-file__name"><?= htmlspecialchars((string)($file['original_name'] ?? 'file')) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </section>
    </div>
</section>
