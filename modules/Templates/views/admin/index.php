<?php
$ap = $adminPrefix ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin');
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Templates</p>
            <h3>Template Library</h3>
            <p class="muted">Upload and switch frontend templates per theme.</p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>">Back to admin</a>
    </div>

    <?php if (!empty($saved)): ?>
        <div class="alert success">Template settings saved.</div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card subtle stack">
        <p class="eyebrow">Current</p>
        <div class="chip-row">
            <span class="pill">Theme: <?= htmlspecialchars($theme ?? 'default') ?></span>
            <span class="pill">Template: <?= htmlspecialchars($currentTemplate ?? 'default') ?></span>
        </div>
        <p class="muted">Templates live in <code>resources/views/templates/</code> and override views when selected.</p>
    </div>

    <form method="post" action="<?= htmlspecialchars($ap . '/templates/select') ?>" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="card subtle stack">
            <h4>Available templates</h4>
            <div class="grid two">
                <?php foreach (($templates ?? []) as $tpl): ?>
                    <?php $isCurrent = ($currentTemplate ?? 'default') === ($tpl['name'] ?? ''); ?>
                    <label class="card">
                        <div class="field">
                            <span><?= htmlspecialchars($tpl['name'] ?? '') ?></span>
                            <input type="radio" name="template" value="<?= htmlspecialchars($tpl['name'] ?? '') ?>" <?= $isCurrent ? 'checked' : '' ?> <?= !empty($tpl['valid']) ? '' : 'disabled' ?>>
                            <?php if (empty($tpl['valid'])): ?>
                                <span class="muted">Missing layout.php</span>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary">Use selected template</button>
            </div>
        </div>
    </form>

    <form method="post" action="<?= htmlspecialchars($ap . '/templates/delete') ?>" class="card subtle stack" data-confirm="Delete this template? This cannot be undone.">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <h4>Delete template</h4>
        <label class="field">
            <span>Template name</span>
            <select name="template" required>
                <?php foreach (($templates ?? []) as $tpl): ?>
                    <?php $name = $tpl['name'] ?? ''; ?>
                    <?php if ($name === 'default') continue; ?>
                    <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="muted">If this template is active, it will be switched to default before deletion.</span>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn ghost">Delete</button>
        </div>
    </form>

    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($ap . '/templates/upload') ?>" class="card subtle stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <h4>Upload template</h4>
        <label class="field">
            <span>Template архив (.zip)</span>
            <input type="file" name="template_zip" accept=".zip" required>
        </label>
        <label class="field">
            <span>Activate after upload</span>
            <input type="checkbox" name="activate" value="1" checked>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn primary">Upload</button>
        </div>
    </form>
</div>
<?php
$title = 'Templates';
$content = ob_get_clean();
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-confirm]');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        const msg = form.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });
});
</script>
