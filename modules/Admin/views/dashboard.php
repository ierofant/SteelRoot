<?php ob_start(); ?>
<?php $ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin'; ?>
<?php
$baseBlocks = [
    'summary' => __('dashboard.block.summary'),
    'content' => __('dashboard.block.content'),
    'service' => __('dashboard.block.service'),
    'all-modules' => __('dashboard.block.all_modules'),
];
?>
<style>
.dash-controls {display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;}
.dash-controls .btn {padding:10px 14px;}
.dash-config {display:none;}
.dash-config.open {display:block;}
.dash-config .option {display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.06);}
.dash-config label {display:flex;align-items:center;gap:8px;}
.dash-config small {color:var(--text-muted,#8b8f98);}
.dash-block .remove-block {position:absolute;top:12px;right:12px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);border-radius:10px;padding:6px 10px;color:inherit;cursor:pointer;transition:all .2s ease;}
.dash-block .remove-block:hover {background:rgba(255,255,255,0.18);}
.dash-block.dragging {opacity:0.6;}
.hidden-block {display:none !important;}
.dash-config .add-form {display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;}
.dash-config .add-form .full {grid-column:1/-1;}
.dash-config .module-chips {display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
.dash-config .module-chips .btn {padding:8px 10px;}
</style>
<div id="dashboard-blocks" class="stack" style="gap:12px;">
    <div class="dash-controls">
        <div class="chip-row">
            <span class="pill"><?= __('dashboard.controls.dragdrop') ?></span>
            <span class="pill"><?= __('dashboard.controls.visibility') ?></span>
        </div>
        <div class="chip-row" style="gap:8px;">
            <button class="btn ghost" id="dash-config-toggle"><?= __('dashboard.controls.configure') ?></button>
            <button class="btn ghost" id="dash-reset"><?= __('dashboard.controls.reset') ?></button>
        </div>
    </div>
    <section class="card glass dash-block" data-block="summary" draggable="true">
        <button type="button" class="remove-block" aria-label="<?= __('dashboard.a11y.remove_block') ?>" data-remove="summary">×</button>
        <div class="dash-hero">
            <div>
                <p class="eyebrow"><?= __('dashboard.hero.tag') ?></p>
                <h2><?= __('dashboard.hero.title') ?></h2>
                <p class="muted"><?= __('dashboard.hero.subtitle') ?></p>
                <div class="chip-row">
                    <span class="pill">v<?= htmlspecialchars($settings['theme_version'] ?? '1.0') ?></span>
                    <span class="pill"><?= __('dashboard.hero.product') ?></span>
                </div>
            </div>
            <div class="orb-widget">
                <div class="orb-core"></div>
                <div class="orb-ring"></div>
                <p class="muted"><?= __('dashboard.hero.modules', ['count' => (int)($stats['modules'] ?? 0)]) ?></p>
            </div>
        </div>
        <div class="grid three mini-cards">
            <div class="stat-card pulse">
                <p class="muted"><?= __('dashboard.stats.articles') ?></p>
                <h3><?= (int)($stats['articles'] ?? 0) ?></h3>
                <span class="muted small">👁 <?= (int)($stats['views_articles'] ?? 0) ?> · ❤ <?= (int)($stats['likes_articles'] ?? 0) ?></span>
            </div>
            <div class="stat-card pulse">
                <p class="muted"><?= __('dashboard.stats.gallery') ?></p>
                <h3><?= (int)($stats['gallery'] ?? 0) ?></h3>
                <span class="muted small">👁 <?= (int)($stats['views_gallery'] ?? 0) ?> · ❤ <?= (int)($stats['likes_gallery'] ?? 0) ?></span>
            </div>
            <div class="stat-card pulse">
                <p class="muted"><?= __('dashboard.stats.admins') ?></p>
                <h3><?= (int)($stats['users'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="quick-actions">
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/settings"><?= __('dashboard.actions.settings') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/articles"><?= __('dashboard.actions.articles') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/gallery/upload"><?= __('dashboard.actions.gallery') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/homepage"><?= __('dashboard.actions.homepage_builder') ?></a>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/cache"><?= __('dashboard.actions.cache') ?></a>
        </div>
    </section>

    <section class="grid two dash-block" data-block="content" draggable="true">
        <button type="button" class="remove-block" aria-label="<?= __('dashboard.a11y.remove_block') ?>" data-remove="content">×</button>
        <div class="card stack glass">
            <p class="eyebrow"><?= __('dashboard.content.eyebrow') ?></p>
            <h3><?= __('dashboard.content.title') ?></h3>
            <div class="link-list">
                <a href="<?= htmlspecialchars($ap) ?>/articles"><?= __('dashboard.content.links.articles') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/attachments"><?= __('dashboard.content.links.attachments') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/gallery/upload"><?= __('dashboard.content.links.gallery') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/forms"><?= __('dashboard.content.links.forms') ?></a>
            </div>
        </div>
        <div class="card stack glass">
            <p class="eyebrow"><?= __('dashboard.interface.eyebrow') ?></p>
            <h3><?= __('dashboard.interface.title') ?></h3>
            <div class="link-list">
                <a href="<?= htmlspecialchars($ap) ?>/homepage"><?= __('dashboard.interface.links.homepage') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/pwa"><?= __('dashboard.interface.links.pwa') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/settings"><?= __('dashboard.interface.links.settings') ?></a>
                <a href="<?= htmlspecialchars($ap) ?>/cache"><?= __('dashboard.interface.links.cache') ?></a>
            </div>
        </div>
    </section>

    <section class="card stack glass dash-block" data-block="service" draggable="true">
        <button type="button" class="remove-block" aria-label="<?= __('dashboard.a11y.remove_block') ?>" data-remove="service">×</button>
        <p class="eyebrow"><?= __('dashboard.service.eyebrow') ?></p>
        <div class="grid three mini-cards">
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/users">
                <p class="eyebrow"><?= __('dashboard.service.users.eyebrow') ?></p>
                <h3><?= __('dashboard.service.users.title') ?></h3>
                <p class="muted"><?= __('dashboard.service.users.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/files">
                <p class="eyebrow"><?= __('dashboard.service.files.eyebrow') ?></p>
                <h3><?= __('dashboard.service.files.title') ?></h3>
                <p class="muted"><?= __('dashboard.service.files.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/cache">
                <p class="eyebrow"><?= __('dashboard.service.cache.eyebrow') ?></p>
                <h3><?= __('dashboard.service.cache.title') ?></h3>
                <p class="muted"><?= __('dashboard.service.cache.desc') ?></p>
            </a>
        </div>
    </section>

    <section class="card stack glass dash-block" data-block="all-modules" draggable="true">
        <button type="button" class="remove-block" aria-label="<?= __('dashboard.a11y.remove_block') ?>" data-remove="all-modules">×</button>
        <p class="eyebrow"><?= __('dashboard.all.eyebrow') ?></p>
        <h3><?= __('dashboard.all.title') ?></h3>
        <div class="grid three mini-cards">
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/settings">
                <h4><?= __('dashboard.all.settings.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.settings.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/articles">
                <h4><?= __('dashboard.all.articles.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.articles.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/attachments">
                <h4><?= __('dashboard.all.attachments.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.attachments.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/gallery/upload">
                <h4><?= __('dashboard.all.gallery.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.gallery.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/files">
                <h4><?= __('dashboard.all.files.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.files.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/forms">
                <h4><?= __('dashboard.all.forms.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.forms.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/homepage">
                <h4><?= __('dashboard.all.homepage.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.homepage.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/pwa">
                <h4><?= __('dashboard.all.pwa.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.pwa.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/cache">
                <h4><?= __('dashboard.all.cache.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.cache.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/profile">
                <h4><?= __('dashboard.all.profile.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.profile.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/sitemap">
                <h4><?= __('dashboard.all.sitemap.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.sitemap.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/search">
                <h4><?= __('dashboard.all.search.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.search.desc') ?></p>
            </a>
            <a class="card link-card" href="<?= htmlspecialchars($ap) ?>/theme">
                <h4><?= __('dashboard.all.theme.title') ?></h4>
                <p class="muted"><?= __('dashboard.all.theme.desc') ?></p>
            </a>
        </div>
    </section>
    <div id="custom-blocks-slot"></div>
    <section id="dash-config" class="card glass dash-config dash-block form-dark" data-block="config" draggable="false">
        <p class="eyebrow"><?= __('dashboard.config.eyebrow') ?></p>
        <h3><?= __('dashboard.config.title') ?></h3>
        <div id="dash-config-list" class="stack" style="gap:8px;"></div>
        <div class="stack" style="gap:6px;margin-top:14px;">
            <p class="eyebrow"><?= __('dashboard.config.new_block') ?></p>
            <div class="add-form">
                <label class="full"><?= __('dashboard.form.title') ?>
                    <input type="text" id="dash-new-title" placeholder="<?= __('dashboard.form.placeholder.title') ?>">
                </label>
                <label class="full"><?= __('dashboard.form.note') ?>
                    <input type="text" id="dash-new-note" placeholder="<?= __('dashboard.form.placeholder.note') ?>">
                </label>
                <label><?= __('dashboard.form.link_text') ?>
                    <input type="text" id="dash-new-link-label" placeholder="<?= __('dashboard.form.placeholder.link_label') ?>">
                </label>
                <label><?= __('dashboard.form.link_url') ?>
                    <input type="text" id="dash-new-link-url" placeholder="<?= htmlspecialchars(__('dashboard.form.placeholder.link_url', ['url' => $ap . '/modules'])) ?>">
                </label>
            </div>
            <div class="chip-row" style="gap:8px;">
                <button class="btn primary" id="dash-add-block"><?= __('dashboard.form.add_block') ?></button>
                <button class="btn ghost" id="dash-clear-form"><?= __('dashboard.form.clear') ?></button>
            </div>
            <?php if (!empty($modules ?? [])): ?>
            <div class="stack" style="gap:6px;">
                <p class="eyebrow"><?= __('dashboard.config.fast_from_modules') ?></p>
                <div class="module-chips" id="dash-module-chips">
                    <?php foreach ($modules as $mod): ?>
                        <button class="btn ghost"
                                data-title="<?= htmlspecialchars($mod['name']) ?>"
                                data-url="<?= htmlspecialchars($ap) ?>/modules?open=<?= urlencode($mod['slug']) ?>"
                                data-enabled="<?= $mod['enabled'] ? '1' : '0' ?>">
                            <?= htmlspecialchars($mod['name']) ?><?= $mod['enabled'] ? '' : htmlspecialchars(__('dashboard.module.disabled_suffix')) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="chip-row" style="margin-top:12px;">
            <button class="btn ghost" id="dash-config-close"><?= __('dashboard.config.close') ?></button>
        </div>
    </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.getElementById('dashboard-blocks');
    const configEl = document.getElementById('dash-config');
    const baseBlockNames = <?= json_encode($baseBlocks, JSON_UNESCAPED_UNICODE) ?>;
    let customBlocks = loadCustomBlocks();
    let blockNames = Object.assign({}, baseBlockNames, customBlocks.reduce((acc, b) => { acc[b.id] = b.title; return acc; }, {}));
    const orderKey = 'sr_admin_dash_order';
    const hiddenKey = 'sr_admin_dash_hidden';
    let defaultOrder = <?= json_encode(array_keys($baseBlocks)) ?>.concat(customBlocks.map(b => b.id));
    const hiddenSet = new Set((localStorage.getItem(hiddenKey) || '').split(',').filter(Boolean));
    const savedOrder = (localStorage.getItem(orderKey) || '').split(',').filter(Boolean);
    const orderToApply = savedOrder.length ? savedOrder : defaultOrder;
    const i18n = {
        blockPrefix: <?= json_encode(__('dashboard.config.block_prefix')) ?>,
        show: <?= json_encode(__('dashboard.config.show')) ?>,
        delete: <?= json_encode(__('dashboard.config.delete')) ?>,
        openLabel: <?= json_encode(__('dashboard.form.default_link_label')) ?>,
        openWithTitle: <?= json_encode(__('dashboard.form.default_link_label_with_title')) ?>,
        moduleEnabled: <?= json_encode(__('dashboard.module.enabled_note')) ?>,
        moduleDisabled: <?= json_encode(__('dashboard.module.disabled_note')) ?>,
        removeAria: <?= json_encode(__('dashboard.a11y.remove_block')) ?>
    };

    renderCustomBlocks();

    orderToApply.forEach(key => {
        const el = wrap.querySelector(`[data-block="${key}"]`);
        if (el) wrap.appendChild(el);
    });
    applyHidden();
    initConfigPanel();
    enableRemoveButtons();
    enableDrag();

    function enableDrag() {
        let dragSrc = null;
        Array.from(wrap.querySelectorAll('.dash-block')).forEach(el => {
            if (el.dataset.block === 'config' || el.dataset.dragInit) return;
            el.dataset.dragInit = '1';
            el.setAttribute('draggable', 'true');
            el.addEventListener('dragstart', function(e) {
                dragSrc = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            el.addEventListener('dragover', function(e) {
                e.preventDefault();
                const target = this;
                if (dragSrc && dragSrc !== target) {
                    const rect = target.getBoundingClientRect();
                    const before = (e.clientY - rect.top) < (rect.height / 2);
                    if (before) wrap.insertBefore(dragSrc, target);
                    else wrap.insertBefore(dragSrc, target.nextSibling);
                }
            });
            el.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                dragSrc = null;
                saveOrder();
            });
        });
    }

    function saveOrder() {
        const newOrder = Array.from(wrap.querySelectorAll('.dash-block'))
            .filter(el => !hiddenSet.has(el.dataset.block) && el.dataset.block !== 'config')
            .map(el => el.dataset.block);
        localStorage.setItem(orderKey, newOrder.join(','));
    }

    function applyHidden() {
        Array.from(wrap.querySelectorAll('.dash-block')).forEach(el => {
            const key = el.dataset.block;
            if (key === 'config') return;
            if (hiddenSet.has(key)) {
                el.classList.add('hidden-block');
            } else {
                el.classList.remove('hidden-block');
            }
        });
    }

    function enableRemoveButtons() {
        wrap.querySelectorAll('.remove-block').forEach(btn => {
            if (btn.dataset.removeInit) return;
            btn.dataset.removeInit = '1';
            btn.addEventListener('click', () => {
                const key = btn.dataset.remove;
                if (key && key.startsWith('custom-')) {
                    removeCustomBlock(key);
                } else {
                    hiddenSet.add(key);
                    localStorage.setItem(hiddenKey, Array.from(hiddenSet).join(','));
                    applyHidden();
                    saveOrder();
                    renderConfigList();
                }
            });
        });
    }

    function initConfigPanel() {
        const panel = document.getElementById('dash-config');
        const toggle = document.getElementById('dash-config-toggle');
        const close = document.getElementById('dash-config-close');
        toggle?.addEventListener('click', () => panel.classList.toggle('open'));
        close?.addEventListener('click', () => panel.classList.remove('open'));
        initAddBlockForm();
        renderConfigList();
    }

    function renderConfigList() {
        const list = document.getElementById('dash-config-list');
        if (!list) return;
        list.innerHTML = '';
        const visibleOrder = defaultOrder.concat(customBlocks.filter(cb => !defaultOrder.includes(cb.id)).map(cb => cb.id));
        visibleOrder.forEach(key => {
            const row = document.createElement('div');
            row.className = 'option';
            const label = document.createElement('label');
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !hiddenSet.has(key);
            cb.addEventListener('change', () => {
                if (cb.checked) hiddenSet.delete(key); else hiddenSet.add(key);
                localStorage.setItem(hiddenKey, Array.from(hiddenSet).join(','));
                applyHidden();
                saveOrder();
            });
            label.appendChild(cb);
            const span = document.createElement('span');
            span.textContent = i18n.blockPrefix + (blockNames[key] || key);
            label.appendChild(span);
            row.appendChild(label);
            const controls = document.createElement('div');
            controls.className = 'chip-row';
            const resetBtn = document.createElement('button');
            resetBtn.className = 'btn ghost';
            resetBtn.textContent = i18n.show;
            resetBtn.addEventListener('click', () => {
                hiddenSet.delete(key);
                localStorage.setItem(hiddenKey, Array.from(hiddenSet).join(','));
                applyHidden();
                saveOrder();
                cb.checked = true;
            });
            controls.appendChild(resetBtn);
            if (key.startsWith('custom-')) {
                const removeBtn = document.createElement('button');
                removeBtn.className = 'btn ghost';
                removeBtn.textContent = i18n.delete;
                removeBtn.addEventListener('click', () => removeCustomBlock(key));
                controls.appendChild(removeBtn);
            }
            row.appendChild(controls);
            list.appendChild(row);
        });
    }

    function renderCustomBlocks() {
        customBlocks.forEach(block => addCustomBlock(block));
    }

    function addCustomBlock(block) {
        const section = document.createElement('section');
        section.className = 'card stack glass dash-block';
        section.dataset.block = block.id;
        section.setAttribute('draggable', 'true');
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-block';
        removeBtn.dataset.remove = block.id;
        removeBtn.textContent = '×';
        removeBtn.setAttribute('aria-label', i18n.removeAria);
        section.appendChild(removeBtn);
        const title = document.createElement('h3');
        title.textContent = block.title;
        section.appendChild(title);
        if (block.note) {
            const p = document.createElement('p');
            p.className = 'muted';
            p.textContent = block.note;
            section.appendChild(p);
        }
        if (Array.isArray(block.links) && block.links.length) {
            const list = document.createElement('div');
            list.className = 'link-list';
            block.links.forEach(link => {
                const a = document.createElement('a');
                a.href = link.url;
                a.textContent = link.label || link.url;
                list.appendChild(a);
            });
            section.appendChild(list);
        }
        wrap.insertBefore(section, configEl);
        enableRemoveButtons();
        enableDrag();
    }

    function removeCustomBlock(key) {
        customBlocks = customBlocks.filter(b => b.id !== key);
        persistCustomBlocks();
        const el = wrap.querySelector(`[data-block="${key}"]`);
        if (el) el.remove();
        hiddenSet.delete(key);
        defaultOrder = defaultOrder.filter(k => k !== key);
        if (blockNames[key]) delete blockNames[key];
        localStorage.setItem(hiddenKey, Array.from(hiddenSet).join(','));
        renderConfigList();
        saveOrder();
    }

    function loadCustomBlocks() {
        try {
            const raw = localStorage.getItem('sr_admin_dash_custom_blocks');
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function persistCustomBlocks() {
        localStorage.setItem('sr_admin_dash_custom_blocks', JSON.stringify(customBlocks));
    }

    function initAddBlockForm() {
        const title = document.getElementById('dash-new-title');
        const note = document.getElementById('dash-new-note');
        const linkLabel = document.getElementById('dash-new-link-label');
        const linkUrl = document.getElementById('dash-new-link-url');
        const addBtn = document.getElementById('dash-add-block');
        const clearBtn = document.getElementById('dash-clear-form');
        addBtn?.addEventListener('click', () => {
            const t = (title?.value || '').trim();
            const url = (linkUrl?.value || '').trim();
            const label = (linkLabel?.value || t || i18n.openLabel);
            if (!t || !url) {
                addBtn?.classList.add('shake');
                setTimeout(() => addBtn?.classList.remove('shake'), 400);
                return;
            }
            const block = {
                id: 'custom-' + Date.now(),
                title: t,
                note: (note?.value || '').trim(),
                links: [{ label, url }]
            };
            customBlocks.push(block);
            persistCustomBlocks();
            blockNames[block.id] = block.title;
            defaultOrder.push(block.id);
            addCustomBlock(block);
            applyHidden();
            renderConfigList();
            saveOrder();
            clearForm();
        });
        clearBtn?.addEventListener('click', clearForm);
        const chips = document.getElementById('dash-module-chips');
        chips?.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                if (title) title.value = btn.dataset.title || '';
                if (linkLabel) linkLabel.value = (i18n.openWithTitle || '').replace('{title}', btn.dataset.title || '');
                if (linkUrl) linkUrl.value = btn.dataset.url || '';
                if (note) note.value = btn.dataset.enabled === '1' ? i18n.moduleEnabled : i18n.moduleDisabled;
            });
        });
        function clearForm() {
            if (title) title.value = '';
            if (note) note.value = '';
            if (linkLabel) linkLabel.value = '';
            if (linkUrl) linkUrl.value = '';
        }
    }

    document.getElementById('dash-reset')?.addEventListener('click', () => {
        localStorage.removeItem(orderKey);
        localStorage.removeItem(hiddenKey);
        localStorage.removeItem('sr_admin_dash_custom_blocks');
        hiddenSet.clear();
        customBlocks = [];
        defaultOrder = Object.keys(baseBlockNames);
        blockNames = Object.assign({}, baseBlockNames);
        Array.from(wrap.querySelectorAll('.dash-block')).forEach(el => {
            if (el.dataset.block && el.dataset.block.startsWith('custom-')) {
                el.remove();
            }
        });
        defaultOrder.forEach(key => {
            const el = wrap.querySelector(`[data-block="${key}"]`);
            if (el) wrap.appendChild(el);
        });
        applyHidden();
        renderConfigList();
        enableDrag();
    });
});
</script>
<?php
$title = __('dashboard.title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
