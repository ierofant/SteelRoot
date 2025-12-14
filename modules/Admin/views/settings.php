<?php ob_start(); ?>
<form method="post" class="card">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <input type="hidden" name="menu_schema" id="menu_schema" value="<?= htmlspecialchars(json_encode($menuItems ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <div class="tabs" id="settings-tabs" style="display:flex;gap:8px;margin-bottom:12px;">
        <button type="button" class="btn ghost small tab-btn active" data-tab="basic"><?= __('settings.tab.basic') ?></button>
        <button type="button" class="btn ghost small tab-btn" data-tab="infra"><?= __('settings.tab.infra') ?></button>
    </div>

    <div class="tab-pane" data-pane="basic" style="display:block;">
    <div class="stack">
        <h3><?= __('settings.section.basic') ?></h3>
        <label class="field">
            <span><?= __('settings.field.site_name') ?></span>
            <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'SteelRoot') ?>">
        </label>
        <label class="field">
            <span><?= __('settings.field.theme') ?></span>
            <select name="theme">
                <option value="light" <?= (($settings['theme'] ?? 'light') === 'light') ? 'selected' : '' ?>><?= __('settings.theme.light') ?></option>
                <option value="dark" <?= (($settings['theme'] ?? 'light') === 'dark') ? 'selected' : '' ?>><?= __('settings.theme.dark') ?></option>
                <option value="custom" <?= (($settings['theme'] ?? 'light') === 'custom') ? 'selected' : '' ?>><?= __('settings.theme.custom') ?></option>
            </select>
        </label>
        <label class="field">
            <span><?= __('settings.field.theme_custom_url') ?></span>
            <input type="text" name="theme_custom_url" value="<?= htmlspecialchars($settings['theme_custom_url'] ?? '') ?>" placeholder="<?= __('settings.placeholder.theme_custom_url') ?>">
        </label>
        <label class="field">
            <span><?= __('settings.field.site_url') ?></span>
            <input type="text" name="site_url" value <?= htmlspecialchars($settings['site_url'] ?? '') ?> placeholder="<?= __('settings.placeholder.site_url') ?>">
        </label>
        <label class="field">
            <span><?= __('settings.field.contact_email') ?></span>
            <input type="text" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
        </label>
        <label class="field">
            <span><?= __('settings.field.locale_mode') ?></span>
            <select name="locale_mode">
                <option value="ru" <?= (($settings['locale_mode'] ?? 'multi') === 'ru') ? 'selected' : '' ?>><?= __('settings.locale.ru') ?></option>
                <option value="en" <?= (($settings['locale_mode'] ?? 'multi') === 'en') ? 'selected' : '' ?>><?= __('settings.locale.en') ?></option>
                <option value="multi" <?= (($settings['locale_mode'] ?? 'multi') === 'multi') ? 'selected' : '' ?>><?= __('settings.locale.multi') ?></option>
            </select>
        </label>
        <label class="field checkbox">
            <input type="checkbox" name="footer_copy_enabled" value="1" <?= !empty($settings['footer_copy_enabled']) ? 'checked' : '' ?>>
            <span><?= __('settings.field.footer_copy_enabled') ?></span>
        </label>
    </div>

    <div class="grid two">
        <div class="stack">
            <h3><?= __('settings.section.uploads') ?></h3>
            <label class="field">
                <span><?= __('settings.field.upload_max_mb') ?></span>
                <input type="number" name="upload_max_mb" value="<?= htmlspecialchars((int)(($settings['upload_max_bytes'] ?? (5*1024*1024)) / (1024*1024))) ?>">
            </label>
            <label class="field">
                <span><?= __('settings.field.upload_max_width') ?></span>
                <input type="number" name="upload_max_w" value="<?= htmlspecialchars($settings['upload_max_width'] ?? 8000) ?>">
            </label>
            <label class="field">
                <span><?= __('settings.field.upload_max_height') ?></span>
                <input type="number" name="upload_max_h" value="<?= htmlspecialchars($settings['upload_max_height'] ?? 8000) ?>">
            </label>
            <label class="field">
                <span><?= __('settings.field.gallery_open_mode') ?></span>
                <select name="gallery_open_mode">
                    <option value="lightbox" <?= (($settings['gallery_open_mode'] ?? 'lightbox') === 'lightbox') ? 'selected' : '' ?>><?= __('settings.gallery.mode.lightbox') ?></option>
                    <option value="page" <?= (($settings['gallery_open_mode'] ?? 'lightbox') === 'page') ? 'selected' : '' ?>><?= __('settings.gallery.mode.page') ?></option>
                </select>
            </label>
        </div>
        <div class="stack">
            <h3><?= __('settings.section.captcha') ?></h3>
            <label class="field">
                <span><?= __('settings.field.captcha_provider') ?></span>
                <select name="captcha_provider">
                    <option value="none" <?= (($settings['captcha_provider'] ?? 'none') === 'none') ? 'selected' : '' ?>><?= __('settings.captcha.none') ?></option>
                    <option value="google" <?= (($settings['captcha_provider'] ?? 'none') === 'google') ? 'selected' : '' ?>><?= __('settings.captcha.google') ?></option>
                    <option value="yandex" <?= (($settings['captcha_provider'] ?? 'none') === 'yandex') ? 'selected' : '' ?>><?= __('settings.captcha.yandex') ?></option>
                </select>
            </label>
            <label class="field">
                <span><?= __('settings.field.captcha_site_key') ?></span>
                <input type="text" name="captcha_site_key" value="<?= htmlspecialchars($settings['captcha_site_key'] ?? '') ?>">
            </label>
            <label class="field">
                <span><?= __('settings.field.captcha_secret_key') ?></span>
                <input type="text" name="captcha_secret_key" value="<?= htmlspecialchars($settings['captcha_secret_key'] ?? '') ?>">
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="captcha_login_enabled" value="1" <?= !empty($settings['captcha_login_enabled']) ? 'checked' : '' ?>>
                <span><?= __('settings.field.captcha_login_enabled') ?></span>
            </label>
        </div>
    </div>

    <div class="card subtle stack">
        <h3><?= __('settings.section.security') ?></h3>
        <label class="field">
            <span><?= __('settings.field.admin_guard_key') ?></span>
            <input type="text" name="admin_guard_key" value="<?= htmlspecialchars($settings['admin_guard_key'] ?? '') ?>" placeholder="<?= __('settings.placeholder.admin_guard_key') ?>">
            <span class="muted"><?= __('settings.help.admin_guard_key') ?></span>
        </label>
        <label class="field">
            <span><?= __('settings.field.admin_ip_regex') ?></span>
            <input type="text" name="admin_ip_regex" value="<?= htmlspecialchars($settings['admin_ip_regex'] ?? '') ?>" placeholder="<?= __('settings.placeholder.admin_ip_regex') ?>">
            <span class="muted"><?= __('settings.help.admin_ip_regex') ?></span>
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('settings.field.admin_max_attempts') ?></span>
                <input type="number" name="admin_max_attempts" value="<?= htmlspecialchars($settings['admin_max_attempts'] ?? 5) ?>" min="1" max="20">
            </label>
            <label class="field">
                <span><?= __('settings.field.admin_lock_minutes') ?></span>
                <input type="number" name="admin_lock_minutes" value="<?= htmlspecialchars($settings['admin_lock_minutes'] ?? 5) ?>" min="1" max="120">
            </label>
        </div>
    </div>

    <div class="card subtle stack">
        <h3><?= __('settings.section.breadcrumbs') ?></h3>
        <label class="field checkbox">
            <input type="checkbox" name="breadcrumbs_enabled" value="1" <?= ($settings['breadcrumbs_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span><?= __('settings.field.breadcrumbs_enabled') ?></span>
        </label>
        <label class="field">
            <span><?= __('settings.field.breadcrumb_home') ?></span>
            <input type="text" name="breadcrumb_home" value="<?= htmlspecialchars($settings['breadcrumb_home'] ?? 'Home') ?>">
        </label>
        <label class="field">
            <span><?= __('settings.field.breadcrumbs_custom') ?></span>
            <textarea name="breadcrumbs_custom" rows="4" placeholder='<?= __('settings.placeholder.breadcrumbs_custom') ?>'><?= htmlspecialchars($settings['breadcrumbs_custom'] ?? '') ?></textarea>
            <span class="muted"><?= __('settings.help.breadcrumbs_custom') ?></span>
        </label>
    </div>

    <div class="card subtle stack menu-builder form-dark">
        <h3><?= __('settings.section.menu') ?></h3>
        <p class="muted"><?= __('settings.menu.helper_text') ?></p>
        <input type="hidden" name="menu_schema" id="menu-schema-input">
        <div class="alert danger" id="menu-error" style="display:none;"></div>
        <div class="table-wrap">
            <table class="data" id="menu-table">
                <thead>
                    <tr>
                        <th style="width:32px;">↕</th>
                        <th><?= __('settings.menu.header.label_ru') ?></th>
                        <th><?= __('settings.menu.header.label_en') ?></th>
                        <th><?= __('settings.menu.header.url') ?></th>
                        <th><?= __('settings.menu.header.enabled') ?></th>
                        <th><?= __('settings.menu.header.admin_only') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" class="btn ghost" id="menu-add"><?= __('settings.menu.add') ?></button>
        </div>
    </div>

    </div> <!-- end basic -->

    <div class="tab-pane" data-pane="infra" style="display:none;">
        <div class="grid two">
            <div class="stack">
                <h3><?= __('settings.section.mail') ?></h3>
                <?php $mailDriver = $settings['mail_driver'] ?? 'smtp'; ?>
                <label class="field">
                    <span><?= __('settings.field.mail_driver') ?></span>
                    <select name="mail_driver">
                        <option value="smtp" <?= $mailDriver === 'smtp' ? 'selected' : '' ?>><?= __('settings.mail.driver.smtp') ?></option>
                        <option value="sendmail" <?= $mailDriver === 'sendmail' ? 'selected' : '' ?>><?= __('settings.mail.driver.sendmail') ?></option>
                        <option value="php" <?= $mailDriver === 'php' ? 'selected' : '' ?>><?= __('settings.mail.driver.php') ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= __('settings.field.mail_host') ?></span>
                    <input type="text" name="mail_host" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_host'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_host') ?>">
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.mail_port') ?></span>
                        <input type="number" name="mail_port" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_port'] ?? 587) ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.mail_secure') ?></span>
                        <?php $secure = $settings['mail_secure'] ?? 'tls'; ?>
                        <select name="mail_secure" data-mail-field="smtp">
                            <option value="none" <?= $secure === 'none' ? 'selected' : '' ?>><?= __('settings.mail.secure.none') ?></option>
                            <option value="ssl" <?= $secure === 'ssl' ? 'selected' : '' ?>><?= __('settings.mail.secure.ssl') ?></option>
                            <option value="tls" <?= $secure === 'tls' ? 'selected' : '' ?>><?= __('settings.mail.secure.tls') ?></option>
                        </select>
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.mail_user') ?></span>
                    <input type="text" name="mail_user" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_user'] ?? '') ?>">
                </label>
                <label class="field">
                    <span><?= __('settings.field.mail_pass') ?></span>
                    <input type="password" name="mail_pass" data-mail-field="smtp" value="<?= htmlspecialchars($settings['mail_pass'] ?? '') ?>">
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.mail_from') ?></span>
                        <input type="text" name="mail_from" value="<?= htmlspecialchars($settings['mail_from'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_from') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.mail_from_name') ?></span>
                        <input type="text" name="mail_from_name" value="<?= htmlspecialchars($settings['mail_from_name'] ?? '') ?>" placeholder="<?= __('settings.placeholder.mail_from_name') ?>">
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.mail_template') ?></span>
                    <textarea name="mail_template" rows="3" placeholder="<?= __('settings.placeholder.mail_template') ?>"><?= htmlspecialchars($settings['mail_template'] ?? '') ?></textarea>
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.mail_test_to') ?></span>
                        <input type="email" name="mail_test_to" value="">
                    </label>
                    <label class="field">
                        <span>&nbsp;</span>
                        <span class="muted"><?= __('settings.help.mail_test_hint') ?></span>
                    </label>
                </div>
            </div>
            <div class="stack">
                <h3><?= __('settings.section.sessions') ?></h3>
                <?php $driver = $settings['session_driver'] ?? 'files'; ?>
                <label class="field">
                    <span><?= __('settings.field.session_driver') ?></span>
                    <select name="session_driver">
                        <option value="files" <?= $driver === 'files' ? 'selected' : '' ?>><?= __('settings.session.driver.files') ?></option>
                        <option value="redis" <?= $driver === 'redis' ? 'selected' : '' ?>><?= __('settings.session.driver.redis') ?></option>
                        <option value="memcached" <?= $driver === 'memcached' ? 'selected' : '' ?>><?= __('settings.session.driver.memcached') ?></option>
                    </select>
                </label>
                <label class="field">
                    <span><?= __('settings.field.session_files_path') ?></span>
                    <input type="text" name="session_path" value="<?= htmlspecialchars($settings['session_path'] ?? (APP_ROOT . '/storage/tmp/sessions')) ?>">
                </label>
                <div class="grid two">
                    <label class="field">
                        <span><?= __('settings.field.session_redis_host') ?></span>
                        <input type="text" name="session_redis_host" value="<?= htmlspecialchars($settings['session_redis_host'] ?? '127.0.0.1') ?>">
                    </label>
                    <label class="field">
                        <span><?= __('settings.field.session_redis_port') ?></span>
                        <input type="number" name="session_redis_port" value="<?= htmlspecialchars($settings['session_redis_port'] ?? 6379) ?>">
                    </label>
                </div>
                <label class="field">
                    <span><?= __('settings.field.session_redis_db') ?></span>
                    <input type="number" name="session_redis_db" value="<?= htmlspecialchars($settings['session_redis_db'] ?? 0) ?>">
                </label>
                <label class="field">
                    <span><?= __('settings.field.session_memcached_servers') ?></span>
                    <input type="text" name="session_memcached_servers" value="<?= htmlspecialchars($settings['session_memcached_servers'] ?? '') ?>" placeholder="<?= __('settings.placeholder.session_memcached_servers') ?>">
                </label>
            </div>
        </div>
    </div> <!-- end infra -->

    <div class="form-actions">
        <button type="submit" class="btn primary"><?= __('settings.action.save') ?></button>
        <span class="muted"><?= __('settings.note.apply_immediately') ?></span>
    </div>
</form>

<template id="menu-row-template">
    <tr draggable="true">
        <td class="drag-cell">≡</td>
        <td><input type="text" class="menu-ru" placeholder="<?= __('settings.placeholder.menu_label_ru') ?>" required></td>
        <td><input type="text" class="menu-en" placeholder="<?= __('settings.placeholder.menu_label_en') ?>" required></td>
        <td><input type="text" class="menu-url" placeholder="<?= __('settings.placeholder.menu_url') ?>" required></td>
        <td style="text-align:center;"><input type="checkbox" class="menu-enabled" checked></td>
        <td style="text-align:center;"><input type="checkbox" class="menu-admin"></td>
        <td class="actions">
            <button type="button" class="btn ghost small menu-up">↑</button>
            <button type="button" class="btn ghost small menu-down">↓</button>
            <button type="button" class="btn danger small menu-remove">×</button>
        </td>
    </tr>
</template>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const data = <?= json_encode($menuItems ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const tpl = document.querySelector('#menu-row-template');
        const tbody = document.querySelector('#menu-table tbody');
        const hidden = document.getElementById('menu-schema-input');
        const errorBox = document.getElementById('menu-error');
        function syncHidden() {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const items = rows.map(r => ({
                label_ru: r.querySelector('.menu-ru').value.trim(),
                label_en: r.querySelector('.menu-en').value.trim(),
                url: r.querySelector('.menu-url').value.trim(),
                enabled: r.querySelector('.menu-enabled').checked,
                requires_admin: r.querySelector('.menu-admin').checked,
            })).filter(r => r.url !== '');
            hidden.value = JSON.stringify(items, null, 2);
        }
        function addRow(item = {}) {
            const row = tpl.content.firstElementChild.cloneNode(true);
            row.addEventListener('dragstart', handleDragStart);
            row.addEventListener('dragover', handleDragOver);
            row.addEventListener('drop', handleDrop);
            row.addEventListener('dragend', handleDragEnd);
            row.querySelector('.menu-ru').value = item.label_ru || '';
            row.querySelector('.menu-en').value = item.label_en || '';
            row.querySelector('.menu-url').value = item.url || '';
            row.querySelector('.menu-enabled').checked = item.enabled !== false;
            row.querySelector('.menu-admin').checked = !!item.requires_admin;
            row.querySelectorAll('input').forEach(input => {
                input.addEventListener('input', syncHidden);
                input.addEventListener('change', syncHidden);
            });
            row.querySelector('.menu-remove').addEventListener('click', () => {
                row.remove();
                syncHidden();
            });
            row.querySelector('.menu-up').addEventListener('click', () => {
                if (row.previousElementSibling) tbody.insertBefore(row, row.previousElementSibling);
                syncHidden();
            });
            row.querySelector('.menu-down').addEventListener('click', () => {
                if (row.nextElementSibling) tbody.insertBefore(row.nextElementSibling, row);
                syncHidden();
            });
            tbody.appendChild(row);
            syncHidden();
        }
        data.forEach(addRow);
        document.getElementById('menu-add').addEventListener('click', () => addRow());
        document.querySelector('form').addEventListener('submit', (e) => {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            let hasError = false;
            const items = rows.map(r => {
                const entry = {
                    label_ru: r.querySelector('.menu-ru').value.trim(),
                    label_en: r.querySelector('.menu-en').value.trim(),
                    url: r.querySelector('.menu-url').value.trim(),
                    enabled: r.querySelector('.menu-enabled').checked,
                    requires_admin: r.querySelector('.menu-admin').checked,
                };
                const urlField = r.querySelector('.menu-url');
                if (!entry.url) {
                    urlField.classList.add('error');
                    hasError = true;
                } else {
                    urlField.classList.remove('error');
                }
                return entry;
            });
            if (hasError) {
                e.preventDefault();
                errorBox.textContent = <?= json_encode(__('settings.error.menu_url_required')) ?>;
                errorBox.style.display = 'block';
                return;
            }
            errorBox.style.display = 'none';
            hidden.value = JSON.stringify(items, null, 2);
        });
    let dragSrc = null;
    function handleDragStart(e) {
        dragSrc = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    }
    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const target = this;
        if (dragSrc && target !== dragSrc) {
            const rect = target.getBoundingClientRect();
            const before = (e.clientY - rect.top) < (rect.height / 2);
            if (before) {
                tbody.insertBefore(dragSrc, target);
            } else {
                tbody.insertBefore(dragSrc, target.nextSibling);
            }
        }
    }
    function handleDrop(e) {
        e.stopPropagation();
        return false;
    }
    function handleDragEnd() {
        this.classList.remove('dragging');
        dragSrc = null;
        syncHidden();
    }
    syncHidden();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mailDriver = document.querySelector('select[name="mail_driver"]');
    const smtpFields = document.querySelectorAll('[data-mail-field="smtp"]');
    const toggleSmtp = () => {
        const mode = mailDriver.value;
        const show = mode === 'smtp';
        smtpFields.forEach(el => {
            const wrapper = el.closest('.field');
            if (wrapper) wrapper.style.display = show ? '' : 'none';
        });
    };
    mailDriver?.addEventListener('change', toggleSmtp);
    toggleSmtp();

    const tabs = document.querySelectorAll('.tab-btn');
    const panes = document.querySelectorAll('.tab-pane');
    tabs.forEach(btn => btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const target = btn.dataset.tab;
        panes.forEach(p => p.style.display = (p.dataset.pane === target) ? 'block' : 'none');
    }));
});
</script>
<?php
$title = 'Settings';
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
