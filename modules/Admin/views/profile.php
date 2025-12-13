<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('profile.title') ?></p>
            <h3><?= __('profile.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('profile.action.back_admin') ?></a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($message)): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span><?= __('profile.field.username') ?></span>
            <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>">
        </label>
        <label class="field">
            <span><?= __('profile.field.old_password') ?></span>
            <input type="password" name="old_password" required>
        </label>
        <label class="field">
            <span><?= __('profile.field.new_password') ?></span>
            <input type="password" name="new_password" placeholder="<?= __('profile.placeholder.new_password') ?>">
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('profile.action.save') ?></button>
            <p class="muted"><?= __('profile.hint') ?></p>
        </div>
    </form>
</div>
<?php
$title = __('profile.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
