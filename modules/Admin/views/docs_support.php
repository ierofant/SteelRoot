<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('docs.title') ?></p>
            <h3><?= __('docs.support.title') ?></h3>
        </div>
        <div class="form-actions" style="gap:8px;">
            <a class="btn ghost" href="/admin/docs"><?= __('docs.menu') ?></a>
        </div>
    </div>
    <div class="stack docs-block">
        <p class="muted"><?= __('docs.support.body.intro') ?></p>
        <p class="muted"><?= __('docs.support.body.optional') ?></p>
        <p class="muted"><?= __('docs.support.body.usage') ?></p>
        <p class="muted"><?= __('docs.support.body.details') ?></p>
    </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
