<?php ob_start(); ?>
<form method="post" class="card stack">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <?php foreach ($schemaFields as $field): ?>
        <?php if (!in_array($field['name'], $formFields, true)) { continue; } ?>
        <label class="field">
            <span><?= htmlspecialchars(ucfirst($field['name'])) ?></span>
            <?php if (($field['type'] ?? '') === 'enum' && !empty($field['values'])): ?>
                <select name="<?= htmlspecialchars($field['name']) ?>">
                    <?php foreach ($field['values'] as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= (($item[$field['name']] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($val)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif (($field['type'] ?? '') === 'text'): ?>
                <textarea name="<?= htmlspecialchars($field['name']) ?>" rows="4"><?= htmlspecialchars($item[$field['name']] ?? '') ?></textarea>
            <?php else: ?>
                <input type="text" name="<?= htmlspecialchars($field['name']) ?>" value="<?= htmlspecialchars($item[$field['name']] ?? '') ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <div class="toolbar">
        <button type="submit" class="btn primary">Save</button>
        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/faq') ?>">Cancel</a>
    </div>
</form>
<?php
$content = ob_get_clean();
$title = $title ?? 'Edit';
include APP_ROOT . '/modules/Admin/views/layout.php';
?>