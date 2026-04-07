<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$currentSort = (string)($sort ?? 'created_at');
$currentDir = strtolower((string)($dir ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$sortBaseQuery = array_filter([
    'email' => $filters['email'] ?? '',
    'username' => $filters['username'] ?? '',
    'role' => $filters['role'] ?? '',
    'status' => $filters['status'] ?? '',
    'group' => $filters['group'] ?? '',
]);
$sortUrl = static function (string $column) use ($ap, $sortBaseQuery, $currentSort, $currentDir): string {
    $query = $sortBaseQuery;
    $query['sort'] = $column;
    $query['dir'] = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
    return $ap . '/users?' . http_build_query($query);
};
$sortIndicator = static function (string $column) use ($currentSort, $currentDir): string {
    if ($currentSort !== $column) {
        return '';
    }
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
};
?>
<div class="card users-admin-directory">
    <div class="card-header users-admin-directory__header">
        <div>
            <p class="eyebrow">Users</p>
            <h3>Directory & access</h3>
        </div>
        <div class="form-actions users-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/export.csv?<?= htmlspecialchars(http_build_query(array_filter([
                'email' => $filters['email'] ?? '',
                'username' => $filters['username'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'group' => $filters['group'] ?? '',
                'sort' => $currentSort,
                'dir' => $currentDir,
            ]))) ?>">CSV</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/community-poll"><?= __('users.community_poll.admin.title') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/groups"><?= __('users.groups.title') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/plans">Master plans</a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/settings"><?= __('users.settings.title') ?></a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/users/create">New user</a>
        </div>
    </div>
    <form class="filters grid four users-admin-filters" method="GET" action="">
        <label class="field users-admin-filters__field users-admin-filters__field--wide">
            <span>Поиск по email</span>
            <input type="text" name="email" value="<?= htmlspecialchars($filters['email'] ?? '') ?>">
        </label>
        <label class="field users-admin-filters__field users-admin-filters__field--wide">
            <span>Поиск по нику</span>
            <input type="text" name="username" value="<?= htmlspecialchars($filters['username'] ?? '') ?>">
        </label>
        <label class="field users-admin-filters__field">
            <span>Role</span>
            <select name="role">
                <option value="">Any</option>
                <?php foreach (['user','editor','admin'] as $role): ?>
                    <option value="<?= $role ?>"<?= ($filters['role'] ?? '') === $role ? ' selected' : '' ?>><?= ucfirst($role) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field users-admin-filters__field">
            <span>Status</span>
            <select name="status">
                <option value="">Any</option>
                <?php foreach (['active','pending','blocked'] as $st): ?>
                    <option value="<?= $st ?>"<?= ($filters['status'] ?? '') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field users-admin-filters__field">
            <span>Group</span>
            <select name="group">
                <option value="">Any</option>
                <?php foreach (($groupOptions ?? []) as $group): ?>
                    <option value="<?= htmlspecialchars($group['slug']) ?>"<?= ($filters['group'] ?? '') === ($group['slug'] ?? '') ? ' selected' : '' ?>>
                        <?= htmlspecialchars($group['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions users-admin-filters__actions">
            <button class="btn ghost" type="submit">Filter</button>
        </div>
    </form>
    <form id="users-bulk-delete-form" class="users-admin-bulk-actions" method="POST" action="<?= htmlspecialchars($ap) ?>/users/bulk-delete" onsubmit="return confirm('Delete selected users?');">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($deleteToken ?? '') ?>">
        <button class="btn ghost danger" type="submit" data-users-bulk-delete disabled>Delete selected</button>
        <span class="users-admin-bulk-actions__hint" data-users-selection-hint>Select users in the table to delete them together.</span>
    </form>
    <div class="table-wrap users-admin-directory__table-wrap">
        <table class="table data users-admin-table">
            <thead>
            <tr>
                <th class="users-admin-table__select">
                    <input type="checkbox" class="users-admin-checkbox" data-users-select-all aria-label="Select all users on this page">
                </th>
                <th><a class="users-admin-sort<?= $currentSort === 'id' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('id')) ?>">ID<?= htmlspecialchars($sortIndicator('id')) ?></a></th>
                <th><a class="users-admin-sort<?= $currentSort === 'name' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('name')) ?>">User<?= htmlspecialchars($sortIndicator('name')) ?></a></th>
                <th><a class="users-admin-sort<?= $currentSort === 'role' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('role')) ?>">Role<?= htmlspecialchars($sortIndicator('role')) ?></a></th>
                <th><a class="users-admin-sort<?= $currentSort === 'group' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('group')) ?>">Group<?= htmlspecialchars($sortIndicator('group')) ?></a></th>
                <th><a class="users-admin-sort<?= $currentSort === 'status' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('status')) ?>">Status<?= htmlspecialchars($sortIndicator('status')) ?></a></th>
                <th>Reg IP</th>
                <th><a class="users-admin-sort<?= $currentSort === 'last_login' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($sortUrl('last_login')) ?>">Last login<?= htmlspecialchars($sortIndicator('last_login')) ?></a></th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="users-admin-table__select">
                        <input
                            type="checkbox"
                            class="users-admin-checkbox"
                            name="ids[]"
                            value="<?= (int)$u['id'] ?>"
                            form="users-bulk-delete-form"
                            data-users-select
                            aria-label="Select user #<?= (int)$u['id'] ?>"
                        >
                    </td>
                    <td><span class="users-admin-chip users-admin-chip--id">#<?= (int)$u['id'] ?></span></td>
                    <td>
                        <div class="users-admin-usercell">
                            <span class="avatar tiny">
                                <?php if (!empty($u['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars(function_exists('mb_strtoupper') && function_exists('mb_substr') ? mb_strtoupper(mb_substr((string)($u['name'] ?? 'U'), 0, 1)) : strtoupper(substr((string)($u['name'] ?? 'U'), 0, 1))) ?>
                                <?php endif; ?>
                            </span>
                            <div class="users-admin-usermeta">
                                <strong><?= htmlspecialchars($u['name'] ?? '') ?></strong>
                                <span><?= htmlspecialchars($u['email'] ?? '') ?></span>
                                <?php if (!empty($u['username'])): ?><span>@<?= htmlspecialchars($u['username']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><span class="users-admin-chip"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td>
                        <?php if (!empty($u['primary_group_name'])): ?>
                            <span class="users-admin-chip users-admin-chip--group"><?= htmlspecialchars($u['primary_group_name']) ?></span>
                        <?php else: ?>
                            <span class="users-admin-chip users-admin-chip--empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="users-admin-chip users-admin-chip--status <?= ($u['status'] ?? '') === 'blocked' ? 'is-danger' : (($u['status'] ?? '') === 'pending' ? 'is-warning' : 'is-success') ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                    <td><span class="users-admin-last-login"><?= htmlspecialchars($u['registration_ip'] ?? '—') ?></span></td>
                    <td><span class="users-admin-last-login"><?= htmlspecialchars($u['last_login'] ?? '—') ?></span></td>
                    <td class="row-actions">
                        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/edit/<?= (int)$u['id'] ?>">Edit</a>
                        <?php if (($u['status'] ?? '') !== 'blocked'): ?>
                            <form method="POST" action="<?= htmlspecialchars($ap) ?>/users/block/<?= (int)$u['id'] ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken ?? '') ?>">
                                <button class="btn ghost danger" type="submit">Block</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= htmlspecialchars($ap) ?>/users/unblock/<?= (int)$u['id'] ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken ?? '') ?>">
                                <button class="btn ghost" type="submit">Unblock</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= htmlspecialchars($ap) ?>/users/reset-password/<?= (int)$u['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($resetToken ?? '') ?>">
                            <button class="btn ghost" type="submit">Reset password</button>
                        </form>
                        <form method="POST" action="<?= htmlspecialchars($ap) ?>/users/delete/<?= (int)$u['id'] ?>" onsubmit="return confirm('Delete this user?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($deleteToken ?? '') ?>">
                            <button class="btn ghost danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $paginationPage    = $page ?? 1;
    $paginationTotal   = $total ?? 0;
    $paginationPerPage = $perPage ?? 20;
    $qs = array_filter([
        'email' => $filters['email'] ?? '',
        'username' => $filters['username'] ?? '',
        'role' => $filters['role'] ?? '',
        'status' => $filters['status'] ?? '',
        'group' => $filters['group'] ?? '',
        'sort' => $currentSort,
        'dir' => $currentDir,
    ]);
    $paginationBase = $ap . '/users' . ($qs ? '?' . http_build_query($qs) : '');
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</div>
<?php
$title = 'Users';
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$bodyHtml = \Core\Asset::scriptTag('/modules/Users/assets/js/users-admin.js', ['defer' => true]);
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
