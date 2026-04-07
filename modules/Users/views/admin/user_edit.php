<?php ob_start(); ?>
<?php $selectedGroups = array_fill_keys(array_map(static fn(array $group): int => (int)$group['id'], (array)($user['groups'] ?? [])), true); ?>
<?php $primaryGroupId = (int)(($user['primary_group']['id'] ?? 0)); ?>
<?php $currentPlan = $user['current_plan'] ?? null; ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Edit user</p>
            <h3><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users">Back</a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
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
        <div class="users-admin-panel users-admin-groups-field">
            <p class="eyebrow">Master access</p>
            <div class="users-admin-permissions users-admin-permissions--compact">
                <label class="users-admin-choice">
                    <input type="checkbox" name="is_master" value="1" <?= !empty($user['is_master']) ? 'checked' : '' ?>>
                    <span>Master profile</span>
                </label>
                <label class="users-admin-choice">
                    <input type="checkbox" name="is_verified" value="1" <?= !empty($user['is_verified']) ? 'checked' : '' ?>>
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
                        <?php $groupId = (int)$group['id']; ?>
                        <label class="users-admin-choice">
                            <input type="checkbox" name="group_ids[]" value="<?= $groupId ?>" <?= isset($selectedGroups[$groupId]) ? 'checked' : '' ?>>
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
                        <option value="<?= (int)$group['id'] ?>"<?= $primaryGroupId === (int)$group['id'] ? ' selected' : '' ?>><?= htmlspecialchars($group['name']) ?></option>
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
                        <option value="<?= (int)$plan['id'] ?>"<?= (int)($currentPlan['plan_id'] ?? $currentPlan['id'] ?? 0) === (int)$plan['id'] ? ' selected' : '' ?>><?= htmlspecialchars($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Plan expires at</span>
                <input type="date" name="plan_expires_at" value="<?= !empty($currentPlan['expires_at']) ? htmlspecialchars(substr((string)$currentPlan['expires_at'], 0, 10)) : '' ?>">
            </label>
            <label class="field">
                <span>Plan status</span>
                <select name="plan_status">
                    <?php foreach (['active','inactive','expired'] as $planStatus): ?>
                        <option value="<?= $planStatus ?>"<?= (($currentPlan['status'] ?? 'active') === $planStatus) ? ' selected' : '' ?>><?= ucfirst($planStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Plan note</span>
                <textarea name="plan_note" rows="3"><?= htmlspecialchars((string)($currentPlan['admin_note'] ?? '')) ?></textarea>
            </label>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn primary" type="submit">Update</button>
        </div>
    </form>
    <form method="POST" action="<?= htmlspecialchars(($ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/users/delete/<?= (int)($user['id'] ?? 0) ?>" class="form-actions" onsubmit="return confirm('Delete this user?');">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($deleteToken ?? '') ?>">
        <button class="btn ghost danger" type="submit">Delete user</button>
    </form>
</div>
<?php
$title = 'Edit User';
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
