<?php ob_start(); ?>
<form method="post" enctype="multipart/form-data" class="card stack">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <h3>Upload module ZIP</h3>
    <p class="muted">Archive should contain module.php at the root of the module folder.</p>
    <label class="field">
        <span>Archive</span>
        <input type="file" name="archive" accept=".zip" required>
    </label>
    <div class="toolbar">
        <button type="submit" class="btn primary">Upload</button>
        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules') ?>">Back</a>
    </div>
</form>
<?php
$title = $title ?? 'Upload Module';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
