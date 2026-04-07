<?php ob_start(); ?>
<?php $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin'; ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Master plans</p>
            <h3>Manual plan assignment</h3>
        </div>
        <div class="users-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users">Users</a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/users/plans/create">New plan</a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table data">
            <thead>
            <tr><th>Name</th><th>Slug</th><th>Price</th><th>Limits</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach (($plans ?? []) as $plan): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$plan['name']) ?></td>
                    <td><span class="pill"><?= htmlspecialchars((string)$plan['slug']) ?></span></td>
                    <td><?= htmlspecialchars((string)(($plan['price'] ?? '') !== '' ? $plan['price'] . ' ' . $plan['currency'] : '—')) ?></td>
                    <td><?= (int)($plan['gallery_limit'] ?? 0) ?> works / <?= (int)($plan['pinned_works_limit'] ?? 0) ?> pinned</td>
                    <td><span class="pill <?= !empty($plan['active']) ? 'success' : 'danger' ?>"><?= !empty($plan['active']) ? 'Active' : 'Disabled' ?></span></td>
                    <td><a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/plans/edit/<?= (int)$plan['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$title = 'Master plans';
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
