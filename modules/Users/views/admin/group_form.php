<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$group = $group ?? [];
$selectedPermissions = array_fill_keys((array)($group['permissions'] ?? []), true);
$isEdit = !empty($group['id']);
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('users.groups.title') ?></p>
            <h3><?= htmlspecialchars($title ?? ($isEdit ? 'Edit group' : 'Create group')) ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/groups"><?= __('users.settings.back') ?></a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field">
                <span><?= __('users.groups.form.name') ?></span>
                <input type="text" name="name" value="<?= htmlspecialchars($group['name'] ?? '') ?>" required>
            </label>
            <label class="field">
                <span><?= __('users.groups.form.slug') ?></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($group['slug'] ?? '') ?>" required>
            </label>
        </div>
        <label class="field">
            <span><?= __('users.groups.form.description') ?></span>
            <textarea name="description" rows="4"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
        </label>
        <div class="grid two">
            <label class="field checkbox">
                <input type="checkbox" name="enabled" value="1" <?= !empty($group['enabled']) ? 'checked' : '' ?>>
                <span><?= __('users.groups.form.enabled') ?></span>
            </label>
            <?php if (!$isEdit || empty($group['is_system'])): ?>
                <label class="field checkbox">
                    <input type="checkbox" name="is_system" value="1" <?= !empty($group['is_system']) ? 'checked' : '' ?>>
                    <span><?= __('users.groups.form.system') ?></span>
                </label>
            <?php else: ?>
                <div class="pill"><?= __('users.groups.form.system_locked') ?></div>
            <?php endif; ?>
        </div>
        <div class="card soft stack">
            <p class="eyebrow"><?= __('users.groups.form.permissions') ?></p>
            <div class="users-admin-permissions">
                <?php foreach (($capabilityOptions ?? []) as $key => $label): ?>
                    <label class="field checkbox">
                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key) ?>" <?= isset($selectedPermissions[$key]) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-actions users-admin-actions">
            <button type="submit" class="btn primary"><?= __('users.settings.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/groups"><?= __('users.settings.cancel') ?></a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
