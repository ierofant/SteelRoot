<?php ob_start(); ?>
<div class="card">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('users.title') ?></p>
            <h3><?= __('users.subtitle') ?></h3>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th><?= __('users.table.id') ?></th><th><?= __('users.table.login') ?></th><th><?= __('users.table.created') ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= (int)$user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card stack form-dark">
    <p class="eyebrow"><?= __('users.new.title') ?></p>
    <?php $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin'; ?>
    <form method="post" action="<?= htmlspecialchars($ap) ?>/users" class="grid two">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field">
            <span><?= __('users.form.username') ?></span>
            <input type="text" name="username" required>
        </label>
        <label class="field">
            <span><?= __('users.form.password') ?></span>
            <input type="password" name="password" required>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('users.form.create') ?></button>
        </div>
    </form>
</div>
<?php
$title = __('users.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
