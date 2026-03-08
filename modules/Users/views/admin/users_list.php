<?php ob_start(); ?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow">Users</p>
            <h3>Directory & access</h3>
        </div>
        <div class="form-actions u-gap-8">
            <a class="btn ghost" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/settings"><?= __('users.settings.title') ?></a>
            <a class="btn primary" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/create">New user</a>
        </div>
    </div>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form class="filters grid three" method="GET" action="">
        <label class="field">
            <span>Email</span>
            <input type="text" name="email" value="<?= htmlspecialchars($filters['email'] ?? '') ?>">
        </label>
        <label class="field">
            <span>Role</span>
            <select name="role">
                <option value="">Any</option>
                <?php foreach (['user','editor','admin'] as $role): ?>
                    <option value="<?= $role ?>"<?= ($filters['role'] ?? '') === $role ? ' selected' : '' ?>><?= ucfirst($role) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Status</span>
            <select name="status">
                <option value="">Any</option>
                <?php foreach (['active','pending','blocked'] as $st): ?>
                    <option value="<?= $st ?>"<?= ($filters['status'] ?? '') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="form-actions">
            <button class="btn ghost" type="submit">Filter</button>
        </div>
    </form>
    <div class="table-wrap">
        <table class="table data">
            <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last login</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td>
                        <div class="user-row-main">
                            <span class="avatar tiny">
                                <?php if (!empty($u['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($u['avatar']) ?>" alt="<?= htmlspecialchars($u['name'] ?? 'User') ?>">
                                <?php else: ?>
                                    <?= htmlspecialchars(strtoupper(substr($u['name'] ?? 'U', 0, 1))) ?>
                                <?php endif; ?>
                            </span>
                            <div>
                                <div><?= htmlspecialchars($u['name'] ?? '') ?></div>
                                <div class="muted"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="pill"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td><span class="pill <?= ($u['status'] ?? '') === 'blocked' ? 'danger' : (($u['status'] ?? '') === 'pending' ? 'warning' : 'success') ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                    <td><?= htmlspecialchars($u['last_login'] ?? '—') ?></td>
                    <td class="row-actions">
                        <a class="btn ghost" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/edit/<?= (int)$u['id'] ?>">Edit</a>
                        <?php if (($u['status'] ?? '') !== 'blocked'): ?>
                            <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/block/<?= (int)$u['id'] ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken ?? '') ?>">
                                <button class="btn ghost danger" type="submit">Block</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/unblock/<?= (int)$u['id'] ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($blockToken ?? '') ?>">
                                <button class="btn ghost" type="submit">Unblock</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/reset-password/<?= (int)$u['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($resetToken ?? '') ?>">
                            <button class="btn ghost" type="submit">Reset password</button>
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
    $qs = array_filter(['email' => $filters['email'] ?? '', 'role' => $filters['role'] ?? '', 'status' => $filters['status'] ?? '']);
    $paginationBase = (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/users' . ($qs ? '?' . http_build_query($qs) : '');
    include APP_ROOT . '/app/views/partials/pagination.php';
    ?>
</div>
<?php
$title = 'Users';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
