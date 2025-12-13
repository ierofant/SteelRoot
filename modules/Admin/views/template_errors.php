<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$s = $settings ?? [];
$codes = $codes ?? ['404','403','500','503'];
$active = $active ?? '404';
function field($s, $key) { return htmlspecialchars($s[$key] ?? ''); }
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('errors.settings.title') ?></p>
            <h3><?= __('errors.settings.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/theme"><?= __('errors.settings.back') ?></a>
    </div>
    <?php if (!empty($saved)): ?>
        <div class="alert success"><?= __('errors.settings.saved') ?></div>
    <?php endif; ?>
    <div class="form-actions" style="gap:8px;">
        <?php foreach ($codes as $code): ?>
            <?php $labelKey = "errors.settings.code_{$code}"; ?>
            <a class="btn ghost <?= $code === $active ? 'active' : '' ?>" href="?code=<?= urlencode($code) ?>"><?= __($labelKey) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="code" value="<?= htmlspecialchars($active) ?>">
        <?php $code = $active; ?>
        <div class="card soft stack">
            <p class="eyebrow"><?= __('errors.settings.code', ['code' => $code]) ?></p>
            <label class="field checkbox">
                <input type="checkbox" name="error_<?= $code ?>_custom_enabled" value="1" <?= !empty($s["error_{$code}_custom_enabled"]) ? 'checked' : '' ?>>
                <span><?= __('errors.settings.custom_enabled') ?></span>
            </label>
            <div class="grid two">
                <label class="field">
                    <span><?= __('errors.settings.title_label') ?></span>
                    <input type="text" name="error_<?= $code ?>_title" value="<?= field($s, "error_{$code}_title") ?>">
                </label>
                <label class="field">
                    <span><?= __('errors.settings.icon') ?></span>
                    <input type="text" name="error_<?= $code ?>_icon" value="<?= field($s, "error_{$code}_icon") ?>" placeholder="😕">
                </label>
            </div>
            <label class="field">
                <span><?= __('errors.settings.message_label') ?></span>
                <input type="text" name="error_<?= $code ?>_message" value="<?= field($s, "error_{$code}_message") ?>">
            </label>
            <label class="field">
                <span><?= __('errors.settings.description_label') ?></span>
                <textarea name="error_<?= $code ?>_description" rows="3"><?= $s["error_{$code}_description"] ?? '' ?></textarea>
            </label>
            <div class="grid two">
                <label class="field">
                    <span><?= __('errors.settings.cta_text') ?></span>
                    <input type="text" name="error_<?= $code ?>_cta_text" value="<?= field($s, "error_{$code}_cta_text") ?>">
                </label>
                <label class="field">
                    <span><?= __('errors.settings.cta_url') ?></span>
                    <input type="text" name="error_<?= $code ?>_cta_url" value="<?= field($s, "error_{$code}_cta_url") ?>">
                </label>
            </div>
            <label class="field checkbox">
                <input type="checkbox" name="error_<?= $code ?>_show_home_button" value="1" <?= !empty($s["error_{$code}_show_home_button"]) ? 'checked' : '' ?>>
                <span><?= __('errors.settings.show_home_button') ?></span>
            </label>
        </div>
        <div class="form-actions" style="gap:8px;">
            <button type="submit" class="btn primary"><?= __('errors.settings.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/theme"><?= __('errors.settings.cancel') ?></a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
