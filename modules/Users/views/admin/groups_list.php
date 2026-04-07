<?php ob_start(); ?>
<?php $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin'; ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('users.groups.title') ?></p>
            <h3><?= __('users.groups.subtitle') ?></h3>
        </div>
        <div class="users-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users"><?= __('users.settings.back') ?></a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/users/groups/create"><?= __('users.groups.create') ?></a>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table data">
            <thead>
            <tr>
                <th><?= __('users.groups.table.name') ?></th>
                <th><?= __('users.groups.table.slug') ?></th>
                <th><?= __('users.groups.table.users') ?></th>
                <th><?= __('users.groups.table.permissions') ?></th>
                <th><?= __('users.groups.table.status') ?></th>
                <th><?= __('users.groups.table.actions') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($groups ?? []) as $group): ?>
                <tr>
                    <td><?= htmlspecialchars($group['name'] ?? '') ?></td>
                    <td><span class="pill"><?= htmlspecialchars($group['slug'] ?? '') ?></span></td>
                    <td><?= (int)($group['users_count'] ?? 0) ?></td>
                    <td><?= (int)($group['permissions_count'] ?? 0) ?></td>
                    <td><span class="pill <?= !empty($group['enabled']) ? 'success' : 'danger' ?>"><?= !empty($group['enabled']) ? 'Enabled' : 'Disabled' ?></span></td>
                    <td><a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/groups/edit/<?= (int)$group['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$title = 'User groups';
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
