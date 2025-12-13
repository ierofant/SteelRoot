<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$form = $formData ?? [];
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= $isNew ? __('forms.embed.create') : __('forms.embed.edit') ?></p>
            <h3><?= $isNew ? __('forms.embed.create') : __('forms.embed.edit') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms/embeds"><?= __('forms.embed.back') ?></a>
    </div>
    <p class="muted"><?= __('forms.embed.description') ?></p>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field">
                <span><?= __('forms.embed.fields.name') ?></span>
                <input type="text" name="name" value="<?= htmlspecialchars($form['name'] ?? '') ?>" required>
            </label>
            <label class="field">
                <span><?= __('forms.embed.fields.slug') ?></span>
                <input type="text" name="slug" value="<?= htmlspecialchars($form['slug'] ?? '') ?>" required>
                <small class="muted"><?= __('forms.embed.help.slug') ?></small>
            </label>
        </div>
        <label class="field">
            <span><?= __('forms.embed.fields.recipient_email') ?></span>
            <input type="email" name="recipient_email" value="<?= htmlspecialchars($form['recipient_email'] ?? '') ?>" placeholder="<?= __('forms.embed.help.recipient') ?>">
            <small class="muted"><?= __('forms.embed.help.recipient_hint') ?></small>
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('forms.embed.fields.success_en') ?></span>
                <input type="text" name="success_en" value="<?= htmlspecialchars($form['success_en'] ?? '') ?>">
            </label>
            <label class="field">
                <span><?= __('forms.embed.fields.success_ru') ?></span>
                <input type="text" name="success_ru" value="<?= htmlspecialchars($form['success_ru'] ?? '') ?>">
            </label>
        </div>
        <label class="field">
            <span><?= __('forms.embed.fields.fields_json') ?></span>
            <textarea name="fields" rows="10"><?= htmlspecialchars($form['fields'] ?? '') ?></textarea>
            <small class="muted"><?= __('forms.embed.help.fields') ?></small>
        </label>
        <label class="field checkbox">
            <input type="checkbox" name="enabled" value="1" <?= !empty($form['enabled']) ? 'checked' : '' ?>>
            <span><?= __('forms.embed.fields.enabled') ?></span>
        </label>
        <div class="form-actions" style="gap:8px;">
            <button type="submit" class="btn primary"><?= __('forms.embed.action.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms/embeds"><?= __('forms.embed.action.cancel') ?></a>
        </div>
    </form>
</div>
<?php
$title = $isNew ? __('forms.embed.create') : __('forms.embed.edit');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
