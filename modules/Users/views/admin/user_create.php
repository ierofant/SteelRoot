<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Create</p>
            <h3>New user</h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users">Back</a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/create" class="grid two">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" required>
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" required>
        </label>
        <label class="field">
            <span>Role</span>
            <select name="role">
                <option value="user">User</option>
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>
        </label>
        <label class="field">
            <span>Status</span>
            <select name="status">
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="blocked">Blocked</option>
            </select>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Save</button>
        </div>
    </form>
</div>
<?php
$title = 'Create User';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
