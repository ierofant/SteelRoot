<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$settings = $settings ?? [];
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('pwa.title') ?></p>
            <h3><?= __('pwa.subtitle') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('pwa.action.back_admin') ?></a>
    </div>
    <?php if (!empty($message)): ?>
        <div class="alert success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <label class="field checkbox">
            <input type="checkbox" name="pwa_enabled" value="1" <?= !empty($settings['pwa_enabled']) ? 'checked' : '' ?>>
            <span><?= __('pwa.field.enabled') ?></span>
        </label>

        <div class="grid two">
            <label class="field">
                <span><?= __('pwa.field.name') ?></span>
                <input type="text" name="pwa_name" value="<?= htmlspecialchars($settings['pwa_name'] ?? '') ?>" placeholder="<?= __('pwa.placeholder.name') ?>">
            </label>
            <label class="field">
                <span><?= __('pwa.field.short_name') ?></span>
                <input type="text" name="pwa_short_name" value="<?= htmlspecialchars($settings['pwa_short_name'] ?? '') ?>" placeholder="<?= __('pwa.placeholder.short_name') ?>">
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pwa.field.start_url') ?></span>
                <input type="text" name="pwa_start_url" value="<?= htmlspecialchars($settings['pwa_start_url'] ?? '/') ?>" placeholder="/">
            </label>
            <label class="field">
                <span><?= __('pwa.field.display') ?></span>
                <?php $disp = $settings['pwa_display'] ?? 'standalone'; ?>
                <select name="pwa_display">
                    <option value="standalone" <?= $disp === 'standalone' ? 'selected' : '' ?>><?= __('pwa.display.standalone') ?></option>
                    <option value="minimal-ui" <?= $disp === 'minimal-ui' ? 'selected' : '' ?>><?= __('pwa.display.minimal') ?></option>
                    <option value="fullscreen" <?= $disp === 'fullscreen' ? 'selected' : '' ?>><?= __('pwa.display.fullscreen') ?></option>
                </select>
            </label>
        </div>

        <div class="grid two">
            <label class="field">
                <span><?= __('pwa.field.theme_color') ?></span>
                <input type="text" name="pwa_theme_color" value="<?= htmlspecialchars($settings['pwa_theme_color'] ?? '#1f6feb') ?>" placeholder="#1f6feb">
            </label>
            <label class="field">
                <span><?= __('pwa.field.bg_color') ?></span>
                <input type="text" name="pwa_bg_color" value="<?= htmlspecialchars($settings['pwa_bg_color'] ?? '#ffffff') ?>" placeholder="#ffffff">
            </label>
        </div>

        <label class="field">
            <span><?= __('pwa.field.icon') ?></span>
            <input type="text" name="pwa_icon" value="<?= htmlspecialchars($settings['pwa_icon'] ?? '') ?>" placeholder="/assets/icons/pwa-512.png">
        </label>

        <div class="grid two">
            <label class="field">
                <span><?= __('pwa.field.sw_version') ?></span>
                <input type="text" name="pwa_sw_version" value="<?= htmlspecialchars($settings['pwa_sw_version'] ?? 'v1') ?>" placeholder="v1">
            </label>
            <label class="field">
                <span><?= __('pwa.field.cache_list') ?></span>
                <input type="text" name="pwa_cache_list" value="<?= htmlspecialchars($settings['pwa_cache_list'] ?? '/,/assets/css/app.css') ?>" placeholder="/,/assets/css/app.css">
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('pwa.action.save') ?></button>
            <a class="btn ghost" href="/"><?= __('pwa.action.to_site') ?></a>
        </div>
    </form>
</div>
<?php
$title = __('pwa.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
