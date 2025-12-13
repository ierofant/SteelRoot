<?php
$s = $settings ?? [];
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('popups.title') ?></p>
            <h3><?= __('popups.subtitle') ?></h3>
        </div>
        <?php if (!empty($saved)): ?><div class="alert success"><?= __('popups.saved') ?></div><?php endif; ?>
    </div>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

        <div class="tabs">
            <button type="button" class="tab active" data-tab="adult"><?= __('popups.tab.adult') ?></button>
            <button type="button" class="tab" data-tab="cookie"><?= __('popups.tab.cookie') ?></button>
        </div>

        <div class="tab-pane" data-pane="adult" style="display:block;">
            <label class="field checkbox">
                <input type="checkbox" name="adult_enabled" value="1" <?= !empty($s['adult_enabled']) ? 'checked' : '' ?>>
                <span><?= __('popups.adult.enabled') ?></span>
            </label>
            <label class="field">
                <span><?= __('popups.adult.pages') ?></span>
                <textarea name="adult_pages" rows="2" placeholder='["/","/gallery"]'><?= htmlspecialchars(json_encode($s['adult_pages'] ?? ["/"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></textarea>
            </label>
            <div class="grid two">
                <label class="field">
                    <span><?= __('popups.adult.delay') ?></span>
                    <input type="number" name="adult_delay" min="0" max="60000" value="<?= htmlspecialchars($s['adult_delay'] ?? 500) ?>">
                </label>
                <label class="field checkbox">
                    <input type="checkbox" name="adult_once_per_session" value="1" <?= ($s['adult_once_per_session'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span><?= __('popups.adult.once') ?></span>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('popups.adult.text_ru') ?></span>
                    <textarea name="adult_text_ru" rows="2"><?= htmlspecialchars($s['adult_text_ru'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span><?= __('popups.adult.text_en') ?></span>
                    <textarea name="adult_text_en" rows="2"><?= htmlspecialchars($s['adult_text_en'] ?? '') ?></textarea>
                </label>
            </div>
        </div>

        <div class="tab-pane" data-pane="cookie" style="display:none;">
            <label class="field checkbox">
                <input type="checkbox" name="cookie_enabled" value="1" <?= !empty($s['cookie_enabled']) ? 'checked' : '' ?>>
                <span><?= __('popups.cookie.enabled') ?></span>
            </label>
            <div class="grid two">
                <label class="field">
                    <span><?= __('popups.cookie.text_ru') ?></span>
                    <textarea name="cookie_text_ru" rows="2"><?= htmlspecialchars($s['cookie_text_ru'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span><?= __('popups.cookie.text_en') ?></span>
                    <textarea name="cookie_text_en" rows="2"><?= htmlspecialchars($s['cookie_text_en'] ?? '') ?></textarea>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('popups.cookie.button_text') ?></span>
                    <input type="text" name="cookie_button_text" value="<?= htmlspecialchars($s['cookie_button_text'] ?? 'OK') ?>">
                </label>
                <label class="field">
                    <span><?= __('popups.cookie.key') ?></span>
                    <input type="text" name="cookie_key" value="<?= htmlspecialchars($s['cookie_key'] ?? 'cookie_policy_accepted') ?>">
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span><?= __('popups.cookie.position') ?></span>
                    <?php $pos = $s['cookie_position'] ?? 'bottom-right'; ?>
                    <select name="cookie_position">
                        <option value="bottom-left" <?= $pos === 'bottom-left' ? 'selected' : '' ?>><?= __('popups.cookie.pos_bottom_left') ?></option>
                        <option value="bottom-right" <?= $pos === 'bottom-right' ? 'selected' : '' ?>><?= __('popups.cookie.pos_bottom_right') ?></option>
                        <option value="top" <?= $pos === 'top' ? 'selected' : '' ?>><?= __('popups.cookie.pos_top') ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= __('popups.cookie.store') ?></span>
                    <?php $store = $s['cookie_store'] ?? 'local'; ?>
                    <select name="cookie_store">
                        <option value="local" <?= $store === 'local' ? 'selected' : '' ?>><?= __('popups.cookie.store_local') ?></option>
                        <option value="session" <?= $store === 'session' ? 'selected' : '' ?>><?= __('popups.cookie.store_session') ?></option>
                    </select>
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('popups.action.save') ?></button>
            <a class="btn ghost" href="<?= htmlspecialchars(defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') ?>"><?= __('popups.action.back') ?></a>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tabs .tab');
    const panes = document.querySelectorAll('.tab-pane');
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        panes.forEach(p => p.style.display = 'none');
        tab.classList.add('active');
        const pane = document.querySelector(`[data-pane=\"${tab.dataset.tab}\"]`);
        if (pane) pane.style.display = 'block';
    }));
});
</script>
<?php
$content = ob_get_clean();
$title = __('popups.page_title');
$showSidebar = true;
$flash = null;
include APP_ROOT . '/modules/Admin/views/layout.php';
