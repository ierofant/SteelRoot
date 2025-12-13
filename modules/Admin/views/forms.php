<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$fields = $fields ?? [];
ob_start();
?>
    <div class="card stack">
        <div class="card-header">
            <div>
                <p class="eyebrow"><?= __('forms.title') ?></p>
                <h3><?= __('forms.subtitle') ?></h3>
        </div>
        <div class="form-actions" style="gap:8px;">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>"><?= __('forms.action.back_admin') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms/embeds"><?= __('forms.embed.tab') ?></a>
        </div>
    </div>
    <p class="muted"><?= __('forms.description') ?></p>
    <?php if (!empty($saved)): ?>
        <div class="alert success"><?= __('forms.saved') ?></div>
    <?php endif; ?>
    <form method="post" id="form-builder" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="schema" id="schema-input">

        <div class="table-wrap">
            <table class="data" id="fields-table">
                <thead>
                    <tr>
                        <th>Имя</th>
                        <th>Метка</th>
                        <th>Тип</th>
                        <th><?= __('forms.table.required') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= __('forms.table.types_hint') ?></p>

        <div class="form-actions">
            <button type="button" class="btn ghost" id="add-field"><?= __('forms.action.add_field') ?></button>
            <button type="submit" class="btn primary"><?= __('forms.action.save') ?></button>
        </div>
</div>

<template id="row-template">
    <tr>
        <td><input type="text" class="fb-name" placeholder="<?= __('forms.placeholder.name') ?>" required></td>
        <td><input type="text" class="fb-label" placeholder="<?= __('forms.placeholder.label') ?>" required></td>
        <td>
            <select class="fb-type">
                <option value="text">text</option>
                <option value="email">email</option>
                <option value="textarea">textarea</option>
                <option value="number">number</option>
                <option value="file">file</option>
            </select>
        </td>
        <td style="text-align:center;"><input type="checkbox" class="fb-required"></td>
        <td class="actions">
            <button type="button" class="btn ghost small move-up"><?= __('forms.action.up') ?></button>
            <button type="button" class="btn ghost small move-down"><?= __('forms.action.down') ?></button>
            <button type="button" class="btn danger small remove-row"><?= __('forms.action.remove') ?></button>
        </td>
    </tr>
</template>

        <div class="card stack">
            <div class="card-header">
                <div>
                    <p class="eyebrow"><?= __('forms.security.title') ?></p>
                    <h3><?= __('forms.security.subtitle') ?></h3>
                </div>
            </div>
            <label class="field">
                <span><?= __('forms.security.blacklist') ?></span>
                <textarea name="contact_blacklist" rows="4" placeholder="<?= __('forms.security.blacklist_placeholder') ?>"><?= htmlspecialchars($blacklist ?? '') ?></textarea>
                <span class="muted"><?= __('forms.security.blacklist_hint') ?></span>
            </label>
            <label class="field">
                <span><?= __('forms.security.regex') ?></span>
                <input type="text" name="contact_block_regex" value="<?= htmlspecialchars($blockRegex ?? '') ?>" placeholder="/(spam|http)/i">
                <span class="muted"><?= __('forms.security.regex_hint') ?></span>
            </label>
            <label class="field">
                <span><?= __('forms.security.domains') ?></span>
                <textarea name="contact_block_domains" rows="3" placeholder="<?= __('forms.security.domains_placeholder') ?>"><?= htmlspecialchars($blockDomains ?? '') ?></textarea>
                <span class="muted"><?= __('forms.security.domains_hint') ?></span>
            </label>
            <div class="form-actions">
                <button type="submit" class="btn primary"><?= __('forms.action.save') ?></button>
            </div>
        </div>
    </form>

<script>
    const fieldsData = <?= json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const tbody = document.querySelector('#fields-table tbody');
    const tpl = document.querySelector('#row-template');

    function addRow(data = {}) {
        const row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelector('.fb-name').value = data.name || '';
        row.querySelector('.fb-label').value = data.label || '';
        row.querySelector('.fb-type').value = data.type || 'text';
        row.querySelector('.fb-required').checked = !!data.required;
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
        row.querySelector('.move-up').addEventListener('click', () => {
            if (row.previousElementSibling) {
                tbody.insertBefore(row, row.previousElementSibling);
            }
        });
        row.querySelector('.move-down').addEventListener('click', () => {
            if (row.nextElementSibling) {
                tbody.insertBefore(row.nextElementSibling, row);
            }
        });
        tbody.appendChild(row);
    }

    fieldsData.forEach(f => addRow(f));

    document.querySelector('#add-field').addEventListener('click', () => addRow());

    document.querySelector('#form-builder').addEventListener('submit', (e) => {
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const schema = rows.map(r => ({
            name: r.querySelector('.fb-name').value.trim(),
            label: r.querySelector('.fb-label').value.trim(),
            type: r.querySelector('.fb-type').value,
            required: r.querySelector('.fb-required').checked
        })).filter(f => f.name !== '' && f.label !== '');
        if (schema.length === 0) {
            e.preventDefault();
            alert(<?= json_encode(__('forms.error.empty')) ?>);
            return;
        }
        document.querySelector('#schema-input').value = JSON.stringify(schema, null, 2);
    });
</script>
<?php
$title = __('forms.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
