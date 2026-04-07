<?php
$ap       = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$settings = $settings ?? [];

$s = fn(string $key, mixed $default = '') => htmlspecialchars((string)($settings[$key] ?? $default));

$enabled     = !empty($settings['pwa_enabled']) && $settings['pwa_enabled'] !== '0';
$display     = $settings['pwa_display']     ?? 'standalone';
$orient      = $settings['pwa_orientation'] ?? 'any';
$strategy    = $settings['pwa_cache_strategy'] ?? 'network-first';
$symPos      = $settings['symbol_position'] ?? 'before';

// Cache list: stored comma-separated → show as one-per-line in textarea
$cacheLines = implode("\n", array_filter(array_map('trim',
    explode(',', $settings['pwa_cache_list'] ?? '/,/offline,/assets/css/app.css,/assets/css/pwa-offline.css,/assets/js/pwa-init.js,/assets/js/popup.js,/assets/js/profile-panel.js,/assets/js/gallery-lightbox.js,/modules/Gallery/assets/js/gallery.js,/manifest.json')
)));

ob_start();
?>
<style>
.pwa-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--space-4);
    align-items: start;
}
@media (max-width: 920px) {
    .pwa-layout { grid-template-columns: 1fr; }
    .pwa-sidebar-panel { position: static; }
}
.pwa-sidebar-panel { position: sticky; top: 1.5rem; display: flex; flex-direction: column; gap: var(--space-3); }

/* Status bar */
.pwa-status {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: rgba(255,255,255,.03);
    flex-wrap: wrap;
    margin-bottom: var(--space-4);
    font-size: .875rem;
}
.pwa-status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
    background: var(--muted);
}
.pwa-status-dot.on  { background: #22c55e; box-shadow: 0 0 6px #22c55e88; }
.pwa-status-dot.off { background: var(--muted); }
.pwa-status-label   { font-weight: 600; color: var(--text); }
.pwa-status-divider { color: var(--border); }
.pwa-status a       { color: var(--accent); text-decoration: none; font-size: .8rem; }
.pwa-status a:hover { text-decoration: underline; }

/* Color picker pair */
.color-pair {
    display: flex;
    gap: .5rem;
    align-items: center;
}
.color-pair input[type="color"] {
    width: 44px;
    height: 38px;
    padding: 2px 3px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg);
    cursor: pointer;
    flex-shrink: 0;
}
.color-pair input[type="text"] { flex: 1; }

/* Icon row with thumbnail */
.icon-row { display: flex; gap: var(--space-3); align-items: flex-end; }
.icon-row .field { flex: 1; }
.icon-thumb {
    width: 56px; height: 56px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    object-fit: contain;
    background: #0a111e;
    flex-shrink: 0;
}
.icon-thumb-empty {
    width: 56px; height: 56px;
    border: 1px dashed var(--border);
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); font-size: .65rem; text-align: center;
    line-height: 1.3;
    flex-shrink: 0;
    background: transparent;
}

/* SW version row */
.sw-version-row { display: flex; gap: .5rem; align-items: flex-end; }
.sw-version-row .field { flex: 1; }
.sw-version-row .btn   { flex-shrink: 0; }

/* Sidebar cards */
.pwa-info-row {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    padding: var(--space-2) 0;
    border-bottom: 1px solid var(--border);
    font-size: .825rem;
}
.pwa-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.pwa-info-row strong { color: var(--muted); min-width: 76px; flex-shrink: 0; font-weight: 500; }
.pwa-info-row span, .pwa-info-row a { color: var(--text); word-break: break-all; }
.pwa-info-row a { color: var(--accent); text-decoration: none; }
.pwa-info-row a:hover { text-decoration: underline; }

/* Manifest preview */
.pwa-preview-wrap { position: relative; }
.pwa-preview {
    margin: 0;
    padding: var(--space-3);
    background: #060d18;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: .7rem;
    font-family: 'Courier New', monospace;
    color: #7dd3fc;
    line-height: 1.6;
    overflow: auto;
    max-height: 380px;
    white-space: pre;
    tab-size: 2;
}
.pwa-copy-btn {
    position: absolute;
    top: .5rem; right: .5rem;
    padding: .2rem .55rem;
    font-size: .7rem;
    cursor: pointer;
}
</style>

<form method="post" action="<?= $ap ?>/pwa" id="pwa-form">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

<?php /* ── STATUS BAR ───────────────────────────────── */ ?>
<div class="pwa-status">
    <span class="pwa-status-dot <?= $enabled ? 'on' : 'off' ?>"></span>
    <span class="pwa-status-label">PWA <?= $enabled ? 'Enabled' : 'Disabled' ?></span>
    <?php if ($enabled): ?>
        <span class="pwa-status-divider">|</span>
        <a href="/manifest.json" target="_blank" rel="noopener">manifest.json ↗</a>
        <a href="/sw.js" target="_blank" rel="noopener">sw.js ↗</a>
    <?php endif; ?>
    <span style="margin-left:auto">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:500">
            <input type="checkbox" name="pwa_enabled" value="1" <?= $enabled ? 'checked' : '' ?>
                   id="pwa-enabled-toggle">
            Enable PWA
        </label>
    </span>
</div>

<div class="pwa-layout">

<?php /* ── LEFT: FORM ──────────────────────────────── */ ?>
<div class="stack">

    <?php /* Identity */ ?>
    <details class="card" open>
        <summary><strong>Identity</strong></summary>
        <div class="stack">
            <div class="grid two">
                <label class="field">
                    <span>App Name</span>
                    <input type="text" name="pwa_name" id="f-name"
                           value="<?= $s('pwa_name') ?>" placeholder="My App" maxlength="100">
                </label>
                <label class="field">
                    <span>Short Name <small class="muted">(12 chars max)</small></span>
                    <input type="text" name="pwa_short_name" id="f-short"
                           value="<?= $s('pwa_short_name') ?>" placeholder="App" maxlength="20">
                </label>
            </div>
            <label class="field">
                <span>Description</span>
                <input type="text" name="pwa_description" id="f-desc"
                       value="<?= $s('pwa_description') ?>" placeholder="Short description of your app" maxlength="300">
            </label>
            <div class="grid two">
                <label class="field">
                    <span>Language</span>
                    <select name="pwa_lang" id="f-lang">
                        <?php foreach (['en' => 'English', 'ru' => 'Russian', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish'] as $lc => $ln): ?>
                            <option value="<?= $lc ?>" <?= ($settings['pwa_lang'] ?? 'en') === $lc ? 'selected' : '' ?>><?= $ln ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Scope</span>
                    <input type="text" name="pwa_scope" id="f-scope"
                           value="<?= $s('pwa_scope', '/') ?>" placeholder="/">
                </label>
            </div>
        </div>
    </details>

    <?php /* Display */ ?>
    <details class="card" open>
        <summary><strong>Display</strong></summary>
        <div class="stack">
            <div class="grid two">
                <label class="field">
                    <span>Start URL</span>
                    <input type="text" name="pwa_start_url" id="f-starturl"
                           value="<?= $s('pwa_start_url', '/') ?>" placeholder="/">
                </label>
                <label class="field">
                    <span>Display Mode</span>
                    <select name="pwa_display" id="f-display">
                        <option value="standalone" <?= $display === 'standalone' ? 'selected' : '' ?>>Standalone — no browser chrome</option>
                        <option value="minimal-ui" <?= $display === 'minimal-ui' ? 'selected' : '' ?>>Minimal UI — back/reload only</option>
                        <option value="fullscreen" <?= $display === 'fullscreen' ? 'selected' : '' ?>>Fullscreen</option>
                        <option value="browser"    <?= $display === 'browser'    ? 'selected' : '' ?>>Browser tab</option>
                    </select>
                </label>
            </div>
            <div class="grid two">
                <label class="field">
                    <span>Orientation</span>
                    <select name="pwa_orientation" id="f-orient">
                        <option value="any"               <?= $orient === 'any'               ? 'selected' : '' ?>>Any</option>
                        <option value="portrait"          <?= $orient === 'portrait'          ? 'selected' : '' ?>>Portrait</option>
                        <option value="landscape"         <?= $orient === 'landscape'         ? 'selected' : '' ?>>Landscape</option>
                        <option value="portrait-primary"  <?= $orient === 'portrait-primary'  ? 'selected' : '' ?>>Portrait primary</option>
                        <option value="landscape-primary" <?= $orient === 'landscape-primary' ? 'selected' : '' ?>>Landscape primary</option>
                    </select>
                </label>
            </div>
        </div>
    </details>

    <?php /* Colors */ ?>
    <details class="card">
        <summary><strong>Colors</strong></summary>
        <div class="stack">
            <div class="grid two">
                <label class="field">
                    <span>Theme Color <small class="muted">browser toolbar</small></span>
                    <div class="color-pair">
                        <input type="color" id="theme-picker" value="<?= $s('pwa_theme_color', '#1f6feb') ?>">
                        <input type="text"  id="f-theme" name="pwa_theme_color"
                               value="<?= $s('pwa_theme_color', '#1f6feb') ?>"
                               placeholder="#1f6feb" maxlength="9" pattern="#[0-9a-fA-F]{3,8}">
                    </div>
                </label>
                <label class="field">
                    <span>Background Color <small class="muted">splash screen</small></span>
                    <div class="color-pair">
                        <input type="color" id="bg-picker" value="<?= $s('pwa_bg_color', '#ffffff') ?>">
                        <input type="text"  id="f-bg" name="pwa_bg_color"
                               value="<?= $s('pwa_bg_color', '#ffffff') ?>"
                               placeholder="#ffffff" maxlength="9" pattern="#[0-9a-fA-F]{3,8}">
                    </div>
                </label>
            </div>
        </div>
    </details>

    <?php /* Icons */ ?>
    <details class="card">
        <summary><strong>Icons</strong></summary>
        <div class="stack">
            <?php
            $iconDefs = [
                ['pwa_icon_192',     'icon192',    '192×192', '192×192 — home screen, task switcher'],
                ['pwa_icon',         'icon512',    '512×512', '512×512 — splash screen, store listing'],
                ['pwa_icon_maskable','iconmask',   'Maskable','512×512 — adaptive icon (padded safe zone)'],
            ];
            foreach ($iconDefs as [$field, $id, $size, $label]):
                $val = $settings[$field] ?? '';
            ?>
            <div class="icon-row">
                <?php if ($val): ?>
                    <img class="icon-thumb" id="<?= $id ?>-thumb"
                         src="<?= htmlspecialchars($val) ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                         alt="">
                    <span class="icon-thumb-empty" id="<?= $id ?>-empty" style="display:none"><?= $size ?></span>
                <?php else: ?>
                    <img class="icon-thumb" id="<?= $id ?>-thumb" src="" alt="" style="display:none"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span class="icon-thumb-empty" id="<?= $id ?>-empty"><?= $size ?></span>
                <?php endif; ?>
                <label class="field">
                    <span><?= htmlspecialchars($label) ?></span>
                    <input type="text" name="<?= $field ?>" id="<?= $id ?>-url"
                           value="<?= htmlspecialchars($val) ?>"
                           placeholder="/assets/icons/icon-<?= strtolower($size) ?>.png">
                </label>
            </div>
            <?php endforeach; ?>
            <p class="muted" style="font-size:.8rem">
                Icons should be square PNGs served over HTTPS.
                <a href="<?= $ap ?>/files" target="_blank">File manager ↗</a>
            </p>
        </div>
    </details>

    <?php /* Service Worker */ ?>
    <details class="card" open>
        <summary><strong>Service Worker &amp; Caching</strong></summary>
        <div class="stack">
            <div class="grid two">
                <label class="field">
                    <span>Cache Version <small class="muted">bump to invalidate old cache</small></span>
                    <div class="sw-version-row">
                        <label class="field" style="margin:0">
                            <input type="text" name="pwa_sw_version" id="f-swver"
                                   value="<?= $s('pwa_sw_version', 'v2') ?>" placeholder="v2" maxlength="32">
                        </label>
                        <button type="button" class="btn ghost small" id="bump-btn" title="Increment version">+1</button>
                    </div>
                </label>
                <label class="field">
                    <span>Fetch Strategy</span>
                    <select name="pwa_cache_strategy" id="f-strategy">
                        <option value="network-first"       <?= $strategy === 'network-first'       ? 'selected' : '' ?>>Network first (freshest, offline fallback)</option>
                        <option value="cache-first"         <?= $strategy === 'cache-first'         ? 'selected' : '' ?>>Cache first (fastest, stale possible)</option>
                        <option value="stale-while-revalidate" <?= $strategy === 'stale-while-revalidate' ? 'selected' : '' ?>>Stale-while-revalidate (instant + background update)</option>
                    </select>
                </label>
            </div>
            <label class="field">
                <span>Offline Fallback Page</span>
                <input type="text" name="pwa_offline_page" id="f-offline"
                       value="<?= $s('pwa_offline_page', '/offline') ?>" placeholder="/offline">
            </label>
            <label class="field">
                <span>Pre-cache URLs <small class="muted">one per line — fetched at install time</small></span>
                <textarea name="pwa_cache_list" id="f-cache" rows="6"
                          placeholder="/&#10;/offline&#10;/assets/css/app.css&#10;/assets/css/pwa-offline.css&#10;/assets/js/pwa-init.js&#10;/assets/js/popup.js&#10;/assets/js/profile-panel.js&#10;/assets/js/gallery-lightbox.js&#10;/modules/Gallery/assets/js/gallery.js&#10;/manifest.json"><?= htmlspecialchars($cacheLines) ?></textarea>
            </label>
        </div>
    </details>

    <details class="card" open>
        <summary><strong>Offline Screen</strong></summary>
        <div class="stack">
            <label class="field">
                <span>Offline Title</span>
                <input type="text" name="pwa_offline_title"
                       value="<?= $s('pwa_offline_title', 'Offline mode') ?>"
                       placeholder="Offline mode" maxlength="120">
            </label>
            <label class="field">
                <span>Offline Message</span>
                <textarea name="pwa_offline_message" rows="4"
                          placeholder="The connection is unavailable right now."><?= htmlspecialchars((string)($settings['pwa_offline_message'] ?? 'The connection is unavailable right now.')) ?></textarea>
            </label>
            <label class="field">
                <span>Retry Button Label</span>
                <input type="text" name="pwa_offline_button"
                       value="<?= $s('pwa_offline_button', 'Try again') ?>"
                       placeholder="Try again" maxlength="60">
            </label>
            <p class="muted" style="font-size:.8rem">
                This content is used by the PWA offline fallback page served from <code>/offline</code>.
                HTML documents stay network-first with offline fallback to avoid stale logged-in pages.
            </p>
        </div>
    </details>

    <div class="toolbar">
        <button type="submit" class="btn primary">Save Settings</button>
        <a class="btn ghost" href="/" target="_blank">View Site ↗</a>
    </div>
</div>

<?php /* ── RIGHT: SIDEBAR ────────────────────────────── */ ?>
<aside class="pwa-sidebar-panel">

    <div class="card stack">
        <h4 style="margin:0 0 var(--space-2)">Status</h4>
        <div class="pwa-info-row">
            <strong>PWA</strong>
            <?php if ($enabled): ?>
                <span class="pill status-active">Active</span>
            <?php else: ?>
                <span class="pill status-archived">Disabled</span>
            <?php endif; ?>
        </div>
        <div class="pwa-info-row">
            <strong>Manifest</strong>
            <?php if ($enabled): ?>
                <a href="/manifest.json" target="_blank">/manifest.json ↗</a>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </div>
        <div class="pwa-info-row">
            <strong>SW</strong>
            <?php if ($enabled): ?>
                <a href="/sw.js" target="_blank">/sw.js ↗</a>
            <?php else: ?>
                <span class="muted">—</span>
            <?php endif; ?>
        </div>
        <div class="pwa-info-row">
            <strong>Strategy</strong>
            <span><?= htmlspecialchars($strategy) ?></span>
        </div>
        <div class="pwa-info-row">
            <strong>Version</strong>
            <span><?= $s('pwa_sw_version', 'v2') ?></span>
        </div>
        <div class="pwa-info-row">
            <strong>Offline</strong>
            <a href="<?= htmlspecialchars($s('pwa_offline_page', '/offline')) ?>" target="_blank"><?= htmlspecialchars($s('pwa_offline_page', '/offline')) ?> ↗</a>
        </div>
    </div>

    <div class="card">
        <h4 style="margin:0 0 var(--space-2)">Manifest Preview</h4>
        <div class="pwa-preview-wrap">
            <pre class="pwa-preview" id="pwa-preview"></pre>
            <button type="button" class="btn ghost small pwa-copy-btn" id="copy-btn">Copy</button>
        </div>
    </div>

</aside>

</div><!-- /.pwa-layout -->
</form>

<script>
(function () {
    'use strict';

    /* ── Collect form field values ─────────────────────── */
    function g(name) {
        var el = document.getElementById('f-' + name) || document.querySelector('[name="' + name + '"]');
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked ? '1' : '0';
        return el.value || '';
    }

    /* ── Build manifest object from current form state ─── */
    function buildManifest() {
        var m = {
            name:             g('name')     || 'SteelRoot',
            short_name:       g('short')    || g('name') || 'SteelRoot',
            start_url:        g('starturl') || '/',
            scope:            g('scope')    || '/',
            display:          g('display')  || 'standalone',
            orientation:      g('orient')   || 'any',
            theme_color:      g('theme')    || '#1f6feb',
            background_color: g('bg')       || '#ffffff',
            lang:             g('lang')     || 'en',
        };
        var desc = g('desc');
        if (desc) m.description = desc;

        var icons = [];
        var i192  = document.getElementById('icon192-url')  ? document.getElementById('icon192-url').value  : '';
        var i512  = document.getElementById('icon512-url')  ? document.getElementById('icon512-url').value  : '';
        var imask = document.getElementById('iconmask-url') ? document.getElementById('iconmask-url').value : '';
        if (i192)  icons.push({ src: i192,  sizes: '192x192', type: 'image/png', purpose: 'any' });
        if (i512)  icons.push({ src: i512,  sizes: '512x512', type: 'image/png', purpose: 'any' });
        if (imask) icons.push({ src: imask, sizes: '512x512', type: 'image/png', purpose: 'maskable' });
        if (icons.length) m.icons = icons;

        return m;
    }

    function updatePreview() {
        var pre = document.getElementById('pwa-preview');
        if (!pre) return;
        pre.textContent = JSON.stringify(buildManifest(), null, 2);
    }

    /* ── Listen to all form inputs ─────────────────────── */
    var form = document.getElementById('pwa-form');
    if (form) {
        form.addEventListener('input', updatePreview);
        form.addEventListener('change', updatePreview);
    }
    updatePreview();

    /* ── Color pickers synced with text inputs ──────────── */
    function bindColorPair(pickerId, textId) {
        var picker = document.getElementById(pickerId);
        var text   = document.getElementById(textId);
        if (!picker || !text) return;
        picker.addEventListener('input', function () {
            text.value = picker.value;
            updatePreview();
        });
        text.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{3,8}$/.test(text.value)) {
                picker.value = text.value;
            }
            updatePreview();
        });
    }
    bindColorPair('theme-picker', 'f-theme');
    bindColorPair('bg-picker',    'f-bg');

    /* ── Icon URL → live thumbnail preview ─────────────── */
    function bindIconPreview(inputId, thumbId, emptyId) {
        var input = document.getElementById(inputId);
        var thumb = document.getElementById(thumbId);
        var empty = document.getElementById(emptyId);
        if (!input || !thumb) return;
        input.addEventListener('change', function () {
            if (input.value.trim()) {
                thumb.src = input.value.trim();
                thumb.style.display = '';
                if (empty) empty.style.display = 'none';
            } else {
                thumb.style.display = 'none';
                if (empty) empty.style.display = 'flex';
            }
            updatePreview();
        });
        thumb.addEventListener('error', function () {
            thumb.style.display = 'none';
            if (empty) empty.style.display = 'flex';
        });
    }
    bindIconPreview('icon192-url',  'icon192-thumb',  'icon192-empty');
    bindIconPreview('icon512-url',  'icon512-thumb',  'icon512-empty');
    bindIconPreview('iconmask-url', 'iconmask-thumb', 'iconmask-empty');

    /* ── Bump SW version ────────────────────────────────── */
    var bumpBtn = document.getElementById('bump-btn');
    var verInput = document.getElementById('f-swver');
    if (bumpBtn && verInput) {
        bumpBtn.addEventListener('click', function () {
            verInput.value = verInput.value.replace(/(\d+)(?!.*\d)/, function (n) {
                return String(parseInt(n, 10) + 1);
            });
            if (verInput.value === verInput.defaultValue) {
                verInput.value = (verInput.value || 'v2') + '-1';
            }
            updatePreview();
        });
    }

    /* ── Copy manifest JSON ─────────────────────────────── */
    var copyBtn = document.getElementById('copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var pre = document.getElementById('pwa-preview');
            if (!pre) return;
            navigator.clipboard.writeText(pre.textContent).then(function () {
                copyBtn.textContent = 'Copied!';
                setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1800);
            }).catch(function () {
                copyBtn.textContent = 'Failed';
            });
        });
    }

    /* ── Enable toggle updates status dot live ──────────── */
    var toggle = document.getElementById('pwa-enabled-toggle');
    var dot    = document.querySelector('.pwa-status-dot');
    var label  = document.querySelector('.pwa-status-label');
    if (toggle && dot && label) {
        toggle.addEventListener('change', function () {
            dot.classList.toggle('on',  toggle.checked);
            dot.classList.toggle('off', !toggle.checked);
            label.textContent = 'PWA ' + (toggle.checked ? 'Enabled' : 'Disabled');
        });
    }
}());
</script>
<?php
$content = ob_get_clean();
$title   = 'PWA Settings';
include __DIR__ . '/layout.php';
?>
