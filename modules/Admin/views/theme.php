<?php ob_start(); ?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('theme.title') ?></p>
            <h3><?= __('theme.subtitle') ?></h3>
            <p class="muted"><?= __('theme.description') ?></p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars(defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') ?>"><?= __('theme.action.back_admin') ?></a>
    </div>
    <?php if (!empty($saved)): ?><div class="alert success"><?= __('theme.saved') ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="stack" id="theme-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="reset_theme" id="reset-theme" value="">
        <div class="grid three">
            <label class="field">
                <span><?= __('theme.field.primary') ?></span>
                <input type="color" name="theme_primary" value="<?= htmlspecialchars($settings['theme_primary'] ?? '#22d3ee') ?>">
            </label>
            <label class="field">
                <span><?= __('theme.field.secondary') ?></span>
                <input type="color" name="theme_secondary" value="<?= htmlspecialchars($settings['theme_secondary'] ?? '#0f172a') ?>">
            </label>
            <label class="field">
                <span><?= __('theme.field.accent') ?></span>
                <input type="color" name="theme_accent" value="<?= htmlspecialchars($settings['theme_accent'] ?? '#f97316') ?>">
            </label>
        </div>
        <div class="grid three">
            <label class="field">
                <span><?= __('theme.field.bg') ?></span>
                <input type="color" name="theme_bg" value="<?= htmlspecialchars($settings['theme_bg'] ?? '#f7f9fc') ?>">
            </label>
            <label class="field">
                <span><?= __('theme.field.text') ?></span>
                <input type="color" name="theme_text" value="<?= htmlspecialchars($settings['theme_text'] ?? '#0f172a') ?>">
            </label>
            <label class="field">
                <span><?= __('theme.field.card') ?></span>
                <input type="color" name="theme_card" value="<?= htmlspecialchars($settings['theme_card'] ?? '#ffffff') ?>">
            </label>
        </div>
        <label class="field">
            <span><?= __('theme.field.radius') ?></span>
            <input type="number" name="theme_radius" value="<?= htmlspecialchars($settings['theme_radius'] ?? 12) ?>" min="0" max="30">
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('theme.field.logo') ?></span>
                <input type="text" name="theme_logo" value="<?= htmlspecialchars($settings['theme_logo'] ?? '') ?>" placeholder="/assets/img/logo.svg">
                <span class="muted"><?= __('theme.help.logo') ?></span>
                <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp">
            </label>
            <label class="field">
                <span><?= __('theme.field.favicon') ?></span>
                <input type="text" name="theme_favicon" value="<?= htmlspecialchars($settings['theme_favicon'] ?? '') ?>" placeholder="/assets/img/favicon.ico">
                <span class="muted"><?= __('theme.help.favicon') ?></span>
                <input type="file" name="favicon_file" accept=".ico,.png,.jpg,.jpeg,.webp">
            </label>
        </div>
        <div class="card subtle stack">
            <h4><?= __('theme.presets.title') ?></h4>
            <div class="grid three">
                <button type="button" class="btn ghost preset-btn" data-preset="light"><?= __('theme.presets.light') ?></button>
                <button type="button" class="btn ghost preset-btn" data-preset="dark"><?= __('theme.presets.dark') ?></button>
                <button type="button" class="btn ghost preset-btn" data-preset="custom"><?= __('theme.presets.custom') ?></button>
            </div>
            <p class="muted"><?= __('theme.presets.help') ?></p>
        </div>

        <div class="card subtle stack">
            <p class="eyebrow"><?= __('theme.footer.section') ?></p>
            <h4 style="margin:0;"><?= __('theme.footer.subtitle') ?></h4>
            <?php for ($i=1; $i<=3; $i++): ?>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('theme.footer.title_ru', ['num' => $i]) ?></span>
                        <input type="text" name="footer_col<?= $i ?>_title_ru" value="<?= htmlspecialchars($settings["footer_col{$i}_title_ru"] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('theme.footer.title_en', ['num' => $i]) ?></span>
                        <input type="text" name="footer_col<?= $i ?>_title_en" value="<?= htmlspecialchars($settings["footer_col{$i}_title_en"] ?? '') ?>">
                    </label>
                </div>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('theme.footer.body_ru', ['num' => $i]) ?></span>
                        <textarea name="footer_col<?= $i ?>_body_ru" rows="4"><?= htmlspecialchars($settings["footer_col{$i}_body_ru"] ?? '') ?></textarea>
                    </label>
                    <label class="field">
                        <span><?= __('theme.footer.body_en', ['num' => $i]) ?></span>
                        <textarea name="footer_col<?= $i ?>_body_en" rows="4"><?= htmlspecialchars($settings["footer_col{$i}_body_en"] ?? '') ?></textarea>
                    </label>
                </div>
                <?php if ($i < 3): ?><hr><?php endif; ?>
            <?php endfor; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('theme.action.save') ?></button>
            <button type="button" class="btn ghost" id="reset-btn"><?= __('theme.action.reset') ?></button>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const presets = {
        light: {primary:'#22d3ee', secondary:'#0f172a', accent:'#f97316', bg:'#f7f9fc', text:'#0f172a', card:'#ffffff'},
        dark: {primary:'#4aa3ff', secondary:'#0f172a', accent:'#22d3ee', bg:'#0b0e11', text:'#e5e7eb', card:'#111827'},
        custom: {primary:'#7c3aed', secondary:'#0f172a', accent:'#f472b6', bg:'#0f172a', text:'#e2e8f0', card:'#111827'}
    };
    const form = document.getElementById('theme-form');
    const resetInput = document.getElementById('reset-theme');
    const fields = {
        primary: form.querySelector('input[name="theme_primary"]'),
        secondary: form.querySelector('input[name="theme_secondary"]'),
        accent: form.querySelector('input[name="theme_accent"]'),
        bg: form.querySelector('input[name="theme_bg"]'),
        text: form.querySelector('input[name="theme_text"]'),
        card: form.querySelector('input[name="theme_card"]'),
    };
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = presets[btn.dataset.preset];
            if (!p) return;
            if (fields.primary) fields.primary.value = p.primary;
            if (fields.secondary) fields.secondary.value = p.secondary;
            if (fields.accent) fields.accent.value = p.accent;
            if (fields.bg) fields.bg.value = p.bg;
            if (fields.text) fields.text.value = p.text;
            if (fields.card) fields.card.value = p.card;
            if (window.showToast) window.showToast(<?= json_encode(__('theme.presets.toast')) ?>, 'info');
        });
    });
    document.getElementById('reset-btn').addEventListener('click', () => {
        resetInput.value = '1';
        form.submit();
    });
});
</script>
<?php
$title = __('theme.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
