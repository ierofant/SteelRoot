<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Edit user</p>
            <h3><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users">Back</a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/edit/<?= (int)($user['id'] ?? 0) ?>" class="grid two">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span>Name</span>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
        </label>
        <label class="field">
            <span>Username</span>
            <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
        </label>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
        </label>
        <label class="field">
            <span>Role</span>
            <select name="role">
                <?php foreach (['user','editor','admin'] as $role): ?>
                    <option value="<?= $role ?>"<?= ($user['role'] ?? '') === $role ? ' selected' : '' ?>><?= ucfirst($role) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Status</span>
            <select name="status">
                <?php foreach (['active','pending','blocked'] as $st): ?>
                    <option value="<?= $st ?>"<?= ($user['status'] ?? '') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Profile visibility</span>
            <select name="profile_visibility">
                <?php foreach (($visibilityOptions ?? ['public','private']) as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"<?= ($user['profile_visibility'] ?? 'public') === $opt ? ' selected' : '' ?>><?= ucfirst($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Signature (optional, plain text)</span>
            <textarea name="signature" maxlength="300" rows="3"><?= htmlspecialchars($user['signature'] ?? '') ?></textarea>
        </label>
        <label class="field">
            <span>New password (optional)</span>
            <input type="password" name="password">
        </label>
        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="password_confirm">
        </label>
        <div class="form-actions">
            <button class="btn primary" type="submit">Update</button>
        </div>
    </form>
</div>
<?php
$title = 'Edit User';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
