<?php
/** @var \Core\Search\SearchProviderInterface[] $providers */
$providers = $providers ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search</title>
    <style>
        body {margin:0;font-family:'Inter','Segoe UI',system-ui,sans-serif;background:radial-gradient(circle at 30% 20%,rgba(255,79,139,0.08),transparent 25%),#0c0f1a;color:#e8ebff;}
        .shell {max-width:1000px;margin:20px auto;padding:16px;}
        .tabs {display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
        .tab {padding:10px 14px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);cursor:pointer;background:rgba(255,255,255,0.04);position:relative;}
        .tab.active {background:linear-gradient(120deg,#ff4f8b,#c86bfa);color:#fff;box-shadow:0 10px 24px rgba(255,79,139,0.35);}
        .tab input {display:none;}
        .filters {background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:14px;margin-bottom:14px;}
        .filters h3 {margin:0 0 10px;}
        .results {display:grid;gap:10px;}
        .card {padding:12px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);}
        .card h4 {margin:0 0 6px;}
        .card p {margin:0;color:#b8c0e0;}
        .controls {display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
        .controls input {padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);color:#e8ebff;min-width:200px;}
        .controls button {padding:10px 14px;border:none;border-radius:10px;background:linear-gradient(120deg,#ff4f8b,#c86bfa);color:#fff;cursor:pointer;font-weight:700;}
    </style>
</head>
<body>
<div class="shell">
    <div class="tabs" id="providerTabs">
        <?php foreach ($providers as $key => $provider): ?>
            <label class="tab" data-key="<?= htmlspecialchars($provider->getKey()) ?>">
                <input type="checkbox" class="provider-check" value="<?= htmlspecialchars($provider->getKey()) ?>" checked style="display:none;">
                <?= htmlspecialchars($provider->getLabel()) ?>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="controls">
        <input type="text" id="query" placeholder="Поиск...">
        <button id="searchBtn">Искать</button>
    </div>
    <div class="filters" id="filtersBox"></div>
    <div class="results" id="results"></div>
</div>
<script>
(() => {
    const providers = <?= json_encode(array_map(fn($p)=>[
        'key'=>$p->getKey(),
        'label'=>$p->getLabel(),
        'options'=>$p->getOptions()
    ], $providers), JSON_UNESCAPED_UNICODE) ?>;
    const tabs = document.querySelectorAll('.tab');
    const filtersBox = document.getElementById('filtersBox');
    const resultsBox = document.getElementById('results');
    const queryInput = document.getElementById('query');
    let current = providers[0]?.key || '';
    const activeProviders = new Set(providers.map(p => p.key));

    const renderFilters = () => {
        const p = providers.find(x => x.key === current);
        if (!p || !p.options || Object.keys(p.options).length === 0) {
            filtersBox.innerHTML = '<em>Нет дополнительных фильтров</em>';
            return;
        }
        let html = '';
        Object.entries(p.options).forEach(([k, opt]) => {
            if (opt.type === 'select' && Array.isArray(opt.options)) {
                html += `<label style=\"display:block;margin-bottom:8px;\"><span>${opt.label||k}</span><select data-filter=\"${k}\" style=\"width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);color:#e8ebff;\">${opt.options.map(o=>`<option value=\"${o.value||o}\">${o.label||o}</option>`).join('')}</select></label>`;
            }
        });
        filtersBox.innerHTML = html;
    };

    tabs.forEach(tab => tab.addEventListener('click', (e) => {
        const key = tab.dataset.key;
        if (!key) return;
        // toggle checkbox state
        if (activeProviders.has(key)) {
            activeProviders.delete(key);
            tab.classList.remove('active');
        } else {
            activeProviders.add(key);
            tab.classList.add('active');
        }
        // set current to any active provider for filters preview
        const firstActive = providers.find(p => activeProviders.has(p.key));
        current = firstActive ? firstActive.key : '';
        renderFilters();
    }));
    tabs.forEach(t => t.classList.add('active'));
    renderFilters();

    const runSearch = async () => {
        const q = queryInput.value.trim();
        if (!q || !current) return;
        const filters = {};
        filtersBox.querySelectorAll('[data-filter]').forEach(el => {
            filters[el.dataset.filter] = el.value;
        });
        const checked = Array.from(activeProviders);
        const res = await fetch('/search/advanced/apply', {
            method: 'POST',
            headers: {'Accept':'application/json','Content-Type':'application/json'},
            body: JSON.stringify({q, provider: current, providers: checked, filters})
        });
        if (!res.ok) return;
        const data = await res.json();
        resultsBox.innerHTML = (data.results || []).map(item => `
            <div class=\"card\">
                <h4><a href=\"${item.url}\" style=\"color:#fff;text-decoration:none;\">${item.title}</a></h4>
                <p>${item.snippet || ''}</p>
                ${item.meta ? `<div style=\"color:#9aa4c2;font-size:12px;\">${item.meta}</div>` : ''}
            </div>
        `).join('');
    };

    document.getElementById('searchBtn').addEventListener('click', runSearch);
})();
</script>
</body>
</html>
