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
            <span>Username</span>
            <input type="text" name="username" required>
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
            <span>Profile visibility</span>
            <select name="profile_visibility">
                <?php foreach (($visibilityOptions ?? ['public','private']) as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= ucfirst($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <label class="field">
            <span>Signature (optional, plain text)</span>
            <textarea name="signature" maxlength="300" rows="3"></textarea>
        </label>
        <div class="users-admin-panel users-admin-groups-field">
            <p class="eyebrow">Master access</p>
            <div class="users-admin-permissions users-admin-permissions--compact">
                <label class="users-admin-choice">
                    <input type="checkbox" name="is_master" value="1">
                    <span>Master profile</span>
                </label>
                <label class="users-admin-choice">
                    <input type="checkbox" name="is_verified" value="1">
                    <span>Verified master</span>
                </label>
            </div>
        </div>
        <?php if (!empty($groupOptions)): ?>
            <div class="users-admin-panel users-admin-groups-field">
                <p class="eyebrow">Groups</p>
                <p class="muted">Tattoo Master and Verified Tattoo Master are available here too. Flags above still sync them automatically.</p>
                <div class="users-admin-permissions">
                    <?php foreach ($groupOptions as $group): ?>
                        <label class="users-admin-choice">
                            <input type="checkbox" name="group_ids[]" value="<?= (int)$group['id'] ?>">
                            <span><?= htmlspecialchars($group['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="field">
                <span>Primary group</span>
                <select name="primary_group_id">
                    <option value="">None</option>
                    <?php foreach ($groupOptions as $group): ?>
                        <option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if (!empty($planOptions)): ?>
            <label class="field">
                <span>Master plan</span>
                <select name="plan_id">
                    <option value="">None</option>
                    <?php foreach ($planOptions as $plan): ?>
                        <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Plan expires at</span>
                <input type="date" name="plan_expires_at">
            </label>
            <label class="field">
                <span>Plan status</span>
                <select name="plan_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="expired">Expired</option>
                </select>
            </label>
            <label class="field">
                <span>Plan note</span>
                <textarea name="plan_note" rows="3"></textarea>
            </label>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn primary" type="submit">Save</button>
        </div>
    </form>
</div>
<?php
$title = 'Create User';
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
