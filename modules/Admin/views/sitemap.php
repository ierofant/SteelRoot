<?php
$ap      = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$config  = $config  ?? [];
$providers = $providers ?? [];

$c = fn(string $key, mixed $default = '') => (string)($config[$key] ?? $default);
$checked = fn(string $key): string => ($c($key) === '1') ? 'checked' : '';

$freqOptions = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
$priorityOptions = ['1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1'];

function sitemapFreqSelect(string $name, string $current, array $options): string {
    $html = '<select name="' . htmlspecialchars($name) . '" class="sm-select">';
    foreach ($options as $opt) {
        $sel = $current === $opt ? ' selected' : '';
        $html .= '<option value="' . $opt . '"' . $sel . '>' . $opt . '</option>';
    }
    $html .= '</select>';
    return $html;
}

function sitemapPrioritySelect(string $name, string $current, array $options): string {
    $html = '<select name="' . htmlspecialchars($name) . '" class="sm-select">';
    foreach ($options as $opt) {
        $sel = rtrim(number_format((float)$current, 1), '0') === rtrim(number_format((float)$opt, 1), '0') ? ' selected' : '';
        $sel = ((float)$current === (float)$opt) ? ' selected' : '';
        $html .= '<option value="' . $opt . '"' . $sel . '>' . $opt . '</option>';
    }
    $html .= '</select>';
    return $html;
}

ob_start();
?>
<style>
.sitemap-layout {
    display: grid;
    grid-template-columns: 1fr 260px;
    gap: var(--space-4);
    align-items: start;
}
@media (max-width: 860px) {
    .sitemap-layout { grid-template-columns: 1fr; }
    .sitemap-sidebar { position: static !important; }
}
.sitemap-sidebar { position: sticky; top: 1.5rem; display: flex; flex-direction: column; gap: var(--space-3); }

/* Section table */
.sm-table { width: 100%; border-collapse: collapse; }
.sm-table th {
    text-align: left;
    font-size: .75rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .4rem .75rem;
    border-bottom: 1px solid var(--border);
}
.sm-table td {
    padding: .6rem .75rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: .875rem;
}
.sm-table tr:last-child td { border-bottom: none; }
.sm-table tr:hover td { background: rgba(255,255,255,.02); }

/* Compact selects */
.sm-select {
    padding: .3rem .5rem;
    font-size: .8rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg);
    color: var(--text);
    cursor: pointer;
    min-width: 90px;
}
.sm-select:focus { outline: 1px solid var(--accent); }

/* Section label */
.sm-label { display: flex; align-items: center; gap: .5rem; }
.sm-label strong { font-size: .875rem; color: var(--text); }
.sm-desc { font-size: .75rem; color: var(--muted); margin-top: .15rem; line-height: 1.3; }

/* Module status badge */
.mod-badge {
    font-size: .7rem;
    padding: .1rem .45rem;
    border-radius: 99px;
    font-weight: 500;
    flex-shrink: 0;
}
.mod-badge.on  { background: rgba(34,197,94,.15); color: #22c55e; border: 1px solid rgba(34,197,94,.3); }
.mod-badge.off { background: rgba(148,163,184,.12); color: var(--muted); border: 1px solid var(--border); }

/* URL count badge */
.url-count {
    font-size: .75rem;
    color: var(--muted);
    font-variant-numeric: tabular-nums;
    min-width: 40px;
    text-align: right;
}
.url-count.loaded { color: var(--accent); }

/* Sidebar info */
.sm-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-2) 0;
    border-bottom: 1px solid var(--border);
    font-size: .825rem;
    gap: .5rem;
}
.sm-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.sm-info-row span { color: var(--muted); flex-shrink: 0; }
.sm-info-row strong { color: var(--text); text-align: right; }
.sm-info-row a { color: var(--accent); text-decoration: none; }
.sm-info-row a:hover { text-decoration: underline; }

/* Preview total */
#sm-total {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent);
    line-height: 1;
    font-variant-numeric: tabular-nums;
}
.sm-total-label { font-size: .75rem; color: var(--muted); margin-top: .25rem; }
</style>

<form method="post" action="<?= $ap ?>/sitemap" id="sitemap-form">
<input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

<div class="sitemap-layout">

<?php /* ── LEFT: FORM ──────────────────────── */ ?>
<div class="stack">

    <?php /* Global settings */ ?>
    <details class="card" open>
        <summary><strong>Global Settings</strong></summary>
        <div class="stack" style="padding-top:var(--space-3)">
            <div class="grid two">
                <label class="field">
                    <span>Cache TTL <small class="muted">(seconds)</small></span>
                    <input type="number" name="sitemap_cache_ttl"
                           value="<?= htmlspecialchars((string)$config['sitemap_cache_ttl']) ?>"
                           min="60" max="86400" step="60">
                    <small class="muted">600 = 10 min. Cleared automatically on save.</small>
                </label>
            </div>
        </div>
    </details>

    <?php /* Core pages */ ?>
    <div class="card">
        <div class="toolbar" style="margin-bottom:var(--space-3)">
            <h4 style="margin:0">Core Pages</h4>
        </div>
        <table class="sm-table">
            <thead>
                <tr>
                    <th style="width:40%">Section</th>
                    <th>Priority</th>
                    <th>Changefreq</th>
                    <th>Type</th>
                    <th class="url-count" title="URL count (click Preview)">URLs</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $coreSections = [
                'home'    => ['label' => 'Homepage',    'desc' => 'Site root URL  /'],
                'contact' => ['label' => 'Contact',     'desc' => 'Contact page  /contact'],
                'tags'    => ['label' => 'Tags',         'desc' => 'Tag listing and tag pages  /tags/…'],
            ];
            foreach ($coreSections as $key => $info):
            ?>
            <tr>
                <td>
                    <label class="sm-label">
                        <input type="checkbox"
                               name="sitemap_include_<?= $key ?>" value="1"
                               <?= $checked("sitemap_include_{$key}") ?>
                               style="flex-shrink:0">
                        <div>
                            <strong><?= htmlspecialchars($info['label']) ?></strong>
                            <div class="sm-desc"><?= htmlspecialchars($info['desc']) ?></div>
                        </div>
                    </label>
                </td>
                <td><?= sitemapPrioritySelect("sitemap_priority_{$key}", $c("sitemap_priority_{$key}"), $priorityOptions) ?></td>
                <td><?= sitemapFreqSelect("sitemap_changefreq_{$key}", $c("sitemap_changefreq_{$key}"), $freqOptions) ?></td>
                <td><span class="pill">Core</span></td>
                <td class="url-count" id="count-<?= $key ?>">—</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php /* Module sections */ ?>
    <?php if (!empty($providers)): ?>
    <div class="card">
        <div class="toolbar" style="margin-bottom:var(--space-3)">
            <h4 style="margin:0">Module Sections
                <small class="muted" style="font-weight:400;font-size:.8rem">
                    — auto-discovered from enabled modules
                </small>
            </h4>
        </div>
        <table class="sm-table">
            <thead>
                <tr>
                    <th style="width:40%">Module</th>
                    <th>Priority</th>
                    <th>Changefreq</th>
                    <th>Status</th>
                    <th class="url-count" title="URL count (click Preview)">URLs</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($providers as $key => $prov): ?>
            <tr>
                <td>
                    <label class="sm-label">
                        <input type="checkbox"
                               name="sitemap_include_<?= htmlspecialchars($key) ?>" value="1"
                               <?= $checked("sitemap_include_{$key}") ?>
                               style="flex-shrink:0"
                               <?= !$prov['module_on'] ? 'disabled title="Module is disabled"' : '' ?>>
                        <div>
                            <strong><?= htmlspecialchars($prov['label']) ?></strong>
                            <?php if ($prov['description']): ?>
                                <div class="sm-desc"><?= htmlspecialchars($prov['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                </td>
                <td><?= sitemapPrioritySelect("sitemap_priority_{$key}", $c("sitemap_priority_{$key}", $prov['priority']), $priorityOptions) ?></td>
                <td><?= sitemapFreqSelect("sitemap_changefreq_{$key}", $c("sitemap_changefreq_{$key}", $prov['changefreq']), $freqOptions) ?></td>
                <td>
                    <?php if ($prov['module_on']): ?>
                        <span class="mod-badge on">Active</span>
                    <?php else: ?>
                        <span class="mod-badge off">Disabled</span>
                    <?php endif; ?>
                </td>
                <td class="url-count" id="count-<?= htmlspecialchars($key) ?>">—</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="card">
        <p class="muted">No module sitemap providers discovered.</p>
    </div>
    <?php endif; ?>

    <div class="toolbar">
        <button type="submit" class="btn primary">Save Settings</button>
        <a class="btn ghost" href="/sitemap.xml" target="_blank" rel="noopener">View sitemap.xml ↗</a>
    </div>

</div>

<?php /* ── RIGHT: SIDEBAR ──────────────────── */ ?>
<aside class="sitemap-sidebar">

    <div class="card" style="text-align:center;padding:var(--space-4)">
        <div id="sm-total">—</div>
        <div class="sm-total-label">Total URLs</div>
        <button type="button" class="btn ghost small" id="preview-btn"
                style="margin-top:var(--space-3)">Preview count</button>
        <div id="preview-status" class="muted" style="font-size:.75rem;margin-top:.5rem"></div>
    </div>

    <div class="card stack">
        <div class="sm-info-row">
            <span>Cache TTL</span>
            <strong><?= (int)$config['sitemap_cache_ttl'] ?>s</strong>
        </div>
        <div class="sm-info-row">
            <span>Sitemap</span>
            <a href="/sitemap.xml" target="_blank">/sitemap.xml ↗</a>
        </div>
        <div class="sm-info-row">
            <span>Modules</span>
            <strong><?= count($providers) ?> providers</strong>
        </div>
        <div class="sm-info-row">
            <span>Active</span>
            <strong><?= count(array_filter($providers, fn($p) => $p['module_on'])) ?> / <?= count($providers) ?></strong>
        </div>
    </div>

    <form method="post" action="<?= $ap ?>/sitemap/clear-cache">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="btn ghost" style="width:100%">Clear Cache</button>
    </form>

</aside>

</div><!-- /.sitemap-layout -->
</form>

<script>
(function () {
    'use strict';

    var previewBtn    = document.getElementById('preview-btn');
    var previewStatus = document.getElementById('preview-status');
    var totalEl       = document.getElementById('sm-total');
    var ap            = <?= json_encode($ap) ?>;

    if (!previewBtn) return;

    previewBtn.addEventListener('click', function () {
        previewBtn.disabled = true;
        previewBtn.textContent = 'Counting…';
        if (previewStatus) previewStatus.textContent = '';

        fetch(ap + '/sitemap/preview', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // Update total
                if (totalEl) {
                    totalEl.textContent = data._total !== undefined ? data._total : '?';
                }
                // Update per-section counts
                Object.keys(data).forEach(function (key) {
                    if (key === '_total') return;
                    var cell = document.getElementById('count-' + key);
                    if (!cell) return;
                    var n = data[key];
                    cell.classList.add('loaded');
                    if (n < 0) {
                        cell.textContent = 'err';
                        cell.style.color = '#f87171';
                    } else {
                        cell.textContent = n;
                    }
                });
                if (previewStatus) previewStatus.textContent = 'Done.';
            })
            .catch(function () {
                if (previewStatus) previewStatus.textContent = 'Request failed.';
            })
            .finally(function () {
                previewBtn.disabled = false;
                previewBtn.textContent = 'Refresh';
            });
    });
}());
</script>
<?php
$content = ob_get_clean();
$title   = 'Sitemap';
include __DIR__ . '/layout.php';
?>
