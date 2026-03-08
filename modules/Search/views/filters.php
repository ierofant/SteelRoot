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
    <link rel="stylesheet" href="/assets/css/app.css?v=20260122">
</head>
<body class="search-filters-page">
<div class="shell">
    <div class="tabs" id="providerTabs">
        <?php foreach ($providers as $key => $provider): ?>
            <label class="tab" data-key="<?= htmlspecialchars($provider->getKey()) ?>">
                <input type="checkbox" class="provider-check u-hide" value="<?= htmlspecialchars($provider->getKey()) ?>" checked>
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
                html += `<label class=\"search-filter-row\"><span>${opt.label||k}</span><select data-filter=\"${k}\" class=\"search-filter-select\">${opt.options.map(o=>`<option value=\"${o.value||o}\">${o.label||o}</option>`).join('')}</select></label>`;
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
                <h4><a href=\"${item.url}\" class=\"search-result-link\">${item.title}</a></h4>
                <p>${item.snippet || ''}</p>
                ${item.meta ? `<div class=\"search-result-meta\">${item.meta}</div>` : ''}
            </div>
        `).join('');
    };

    document.getElementById('searchBtn').addEventListener('click', runSearch);
})();
</script>
</body>
</html>
