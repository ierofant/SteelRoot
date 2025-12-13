<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('forms.embed.title') ?></p>
            <h3><?= __('forms.embed.subtitle') ?></h3>
        </div>
        <div class="form-actions" style="gap:8px;">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms"><?= __('forms.title') ?></a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/forms/embeds/create"><?= __('forms.embed.action.create') ?></a>
        </div>
    </div>
    <p class="muted"><?= __('forms.embed.description') ?></p>
    <div class="card soft stack docs-block">
        <div class="grid two">
            <div>
                <p class="eyebrow"><?= __('forms.embed.fields.embed') ?></p>
                <code>{{ form:example_slug }}</code>
            </div>
            <div>
                <p class="eyebrow"><?= __('forms.embed.fields.status') ?></p>
                <span class="pill muted"><?= __('forms.embed.enabled') ?> / <?= __('forms.embed.disabled') ?></span>
            </div>
        </div>
    </div>
    <div class="table">
        <div class="table__head">
            <div>#</div>
            <div><?= __('forms.embed.fields.name') ?></div>
            <div><?= __('forms.embed.fields.slug') ?></div>
            <div><?= __('forms.embed.fields.status') ?></div>
            <div><?= __('forms.embed.fields.embed') ?></div>
            <div><?= __('forms.embed.actions') ?></div>
        </div>
        <?php foreach ($forms as $form): ?>
            <div class="table__row">
                <div><?= (int)$form['id'] ?></div>
                <div><?= htmlspecialchars($form['name'] ?? '') ?></div>
                <div><?= htmlspecialchars($form['slug'] ?? '') ?></div>
                <div>
                    <?php if (!empty($form['enabled'])): ?>
                        <span class="pill success"><?= __('forms.embed.enabled') ?></span>
                    <?php else: ?>
                        <span class="pill muted"><?= __('forms.embed.disabled') ?></span>
                    <?php endif; ?>
                </div>
                <div><code>{{ form:<?= htmlspecialchars($form['slug'] ?? '') ?> }}</code></div>
                <div class="actions">
                    <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms/embeds/edit/<?= (int)$form['id'] ?>"><?= __('forms.embed.action.edit') ?></a>
                    <form method="post" action="<?= htmlspecialchars($ap) ?>/forms/embeds/delete/<?= (int)$form['id'] ?>" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn ghost danger"><?= __('forms.embed.action.delete') ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($forms)): ?>
            <div class="table__row">
                <div class="muted" style="grid-column: 1 / -1;"><?= __('forms.embed.empty') ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$title = __('forms.embed.title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
