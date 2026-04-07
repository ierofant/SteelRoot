<?php
$ap = $adminPrefix ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin');
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= htmlspecialchars(__('comments.admin.kicker')) ?></p>
            <h3><?= htmlspecialchars(__('comments.admin.title')) ?></h3>
        </div>
        <div class="u-flex u-gap-half">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/comments/settings"><?= htmlspecialchars(__('comments.admin.settings')) ?></a>
        </div>
    </div>

    <div class="comments-admin-toolbar">
        <div class="comments-admin-toolbar__stat">
            <span class="comments-admin-toolbar__label">Всего</span>
            <strong><?= (int)($total ?? count($comments ?? [])) ?></strong>
        </div>
        <div class="comments-admin-toolbar__stat">
            <span class="comments-admin-toolbar__label">На странице</span>
            <strong><?= (int)count($comments ?? []) ?></strong>
        </div>
        <div class="comments-admin-toolbar__stat">
            <span class="comments-admin-toolbar__label">Страница</span>
            <strong><?= (int)($page ?? 1) ?></strong>
        </div>
    </div>

    <form method="get" action="<?= htmlspecialchars($ap) ?>/comments" class="comments-admin-filters">
        <label class="field">
            <span><?= htmlspecialchars(__('comments.admin.filter.status')) ?></span>
            <select name="status">
                <option value=""><?= htmlspecialchars(__('comments.admin.filter.all')) ?></option>
                <?php foreach (['pending', 'approved', 'rejected', 'spam', 'deleted'] as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= htmlspecialchars(__('comments.admin.filter.entity')) ?></span>
            <select name="entity_type">
                <option value=""><?= htmlspecialchars(__('comments.admin.filter.all')) ?></option>
                <?php foreach ($entityOptions as $type => $label): ?>
                    <option value="<?= htmlspecialchars($type) ?>" <?= ($filters['entity_type'] ?? '') === $type ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span><?= htmlspecialchars(__('comments.admin.filter.search')) ?></span>
            <input type="text" name="q" value="<?= htmlspecialchars((string)($filters['q'] ?? '')) ?>">
        </label>
        <label class="field">
            <span><?= htmlspecialchars(__('comments.admin.filter.from')) ?></span>
            <input type="date" name="date_from" value="<?= htmlspecialchars((string)($filters['date_from'] ?? '')) ?>">
        </label>
        <label class="field">
            <span><?= htmlspecialchars(__('comments.admin.filter.to')) ?></span>
            <input type="date" name="date_to" value="<?= htmlspecialchars((string)($filters['date_to'] ?? '')) ?>">
        </label>
        <div class="comments-admin-filters__actions">
            <button class="btn" type="submit"><?= htmlspecialchars(__('comments.admin.filter.apply')) ?></button>
        </div>
    </form>
</div>

<form method="post" action="<?= htmlspecialchars($ap) ?>/comments/bulk" class="card stack comments-admin-bulk" data-comments-bulk-form data-comments-source=".comments-admin-table" data-comment-confirm="delete">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
    <div class="comments-admin-bulk__bar">
        <div class="comments-admin-bulk__copy">
            <p class="eyebrow">Массовое действие</p>
            <strong>Выберите комментарии в таблице и примените одно действие</strong>
        </div>
        <div class="comments-admin-bulk__controls">
            <select name="bulk_action">
                <option value="approve"><?= htmlspecialchars(__('comments.action.approve')) ?></option>
                <option value="reject"><?= htmlspecialchars(__('comments.action.reject')) ?></option>
                <option value="spam"><?= htmlspecialchars(__('comments.action.spam')) ?></option>
                <option value="delete"><?= htmlspecialchars(__('comments.action.delete')) ?></option>
                <option value="purge"><?= htmlspecialchars(__('comments.action.purge')) ?></option>
            </select>
            <button type="submit" class="btn ghost"><?= htmlspecialchars(__('comments.admin.bulk.apply')) ?></button>
        </div>
    </div>

</form>
<div class="table-wrap card">
    <table class="table comments-admin-table">
        <thead>
            <tr>
                <th><input type="checkbox" data-comment-check-all></th>
                <th>ID</th>
                <th><?= htmlspecialchars(__('comments.admin.table.author')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.target')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.parent')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.status')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.text')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.date')) ?></th>
                <th><?= htmlspecialchars(__('comments.admin.table.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($comments as $comment): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int)$comment['id'] ?>"></td>
                    <td><span class="comments-admin-id">#<?= (int)$comment['id'] ?></span></td>
                    <td>
                        <div class="comments-admin-author">
                            <strong><?= htmlspecialchars((string)($comment['author_display'] ?? $comment['guest_name'] ?? 'Guest')) ?></strong>
                            <span><?= !empty($comment['user_id']) ? 'user #' . (int)$comment['user_id'] : 'guest' ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="comments-admin-target">
                            <span class="comments-admin-target__type"><?= htmlspecialchars((string)$comment['entity_type']) ?></span>
                            <code>#<?= (int)$comment['entity_id'] ?></code>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($comment['parent_id'])): ?>
                            <span class="comments-admin-parent">#<?= (int)$comment['parent_id'] ?></span>
                        <?php else: ?>
                            <span class="comments-admin-parent is-root">Корень</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="comments-admin-status comments-admin-status--<?= htmlspecialchars((string)$comment['status']) ?>"><?= htmlspecialchars((string)$comment['status']) ?></span></td>
                    <td><div class="comments-admin-body"><?= htmlspecialchars(mb_strimwidth((string)($comment['body'] ?? ''), 0, 140, '…')) ?></div></td>
                    <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)($comment['created_at'] ?? 'now')))) ?></td>
                    <td class="actions">
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/approve/<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
                            <button type="submit" class="btn ghost small"><?= htmlspecialchars(__('comments.action.approve')) ?></button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/reject/<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
                            <button type="submit" class="btn ghost small"><?= htmlspecialchars(__('comments.action.reject')) ?></button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/spam/<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
                            <button type="submit" class="btn ghost small"><?= htmlspecialchars(__('comments.action.spam')) ?></button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/delete/<?= (int)$comment['id'] ?>" data-comment-confirm="delete">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
                            <button type="submit" class="btn ghost danger small"><?= htmlspecialchars(__('comments.action.delete')) ?></button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/purge/<?= (int)$comment['id'] ?>" data-comment-confirm="purge">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
                            <button type="submit" class="btn ghost danger small"><?= htmlspecialchars(__('comments.action.purge')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($comments)): ?>
                <tr>
                    <td colspan="9" class="muted"><?= htmlspecialchars(__('comments.admin.empty')) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$totalPages = max(1, (int)ceil(((int)$total) / max(1, (int)$perPage)));
if ($totalPages > 1): ?>
    <div class="card">
        <div class="comments-pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php
                $params = $filters;
                $params['page'] = $i;
                ?>
                <a class="comments-pagination__link<?= $i === (int)$page ? ' is-active' : '' ?>" href="<?= htmlspecialchars($ap . '/comments?' . http_build_query($params)) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$headHtml = \Core\Asset::styleTag('/modules/Comments/assets/css/comments.css');
$bodyHtml = \Core\Asset::scriptTag('/modules/Comments/assets/js/comments.js');
$content = ob_get_clean();
$pageTitle = $title ?? __('comments.admin.title');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
