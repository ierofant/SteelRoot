<?php
$topicGroups = is_array($topicGroups ?? null) ? $topicGroups : [];
$items = is_array($items ?? null) ? $items : [];
$primaryGroup = $topicGroups[0]['slug'] ?? 'all';
?>
<section class="faq-page" data-faq-tabs>
    <div class="faq-hero">
        <div class="faq-hero__copy">
            <p class="faq-eyebrow">Tattoo Guide</p>
            <h1>FAQ</h1>
            <p class="faq-lead">Вопросы о записи, эскизе, подготовке, заживлении и безопасности татуировки.</p>
        </div>
        <div class="faq-hero__meta">
            <div class="faq-stat">
                <strong><?= (int) count($items) ?></strong>
                <span>актуальных ответов</span>
            </div>
            <div class="faq-stat">
                <strong><?= (int) max(count($topicGroups) - 1, 1) ?></strong>
                <span>тематических разделов</span>
            </div>
        </div>
    </div>

    <?php if (!empty($topicGroups)): ?>
        <div class="faq-tabs" role="tablist" aria-label="FAQ topics">
            <?php foreach ($topicGroups as $index => $group): ?>
                <?php $tabId = 'faq-tab-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $group['slug']); ?>
                <button
                    type="button"
                    class="faq-tab<?= $index === 0 ? ' is-active' : '' ?>"
                    role="tab"
                    id="<?= htmlspecialchars($tabId) ?>"
                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                    aria-controls="<?= htmlspecialchars($tabId . '-panel') ?>"
                    data-tab-target="<?= htmlspecialchars($group['slug']) ?>"
                >
                    <span><?= htmlspecialchars($group['label'] ?? '') ?></span>
                    <small><?= (int) count($group['items'] ?? []) ?></small>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="faq-panels">
            <?php foreach ($topicGroups as $index => $group): ?>
                <?php $tabId = 'faq-tab-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $group['slug']); ?>
                <section
                    class="faq-panel<?= $index === 0 ? ' is-active' : '' ?>"
                    role="tabpanel"
                    id="<?= htmlspecialchars($tabId . '-panel') ?>"
                    aria-labelledby="<?= htmlspecialchars($tabId) ?>"
                    data-tab-panel="<?= htmlspecialchars($group['slug']) ?>"
                >
                    <div class="faq-panel__intro">
                        <div>
                            <p class="faq-panel__eyebrow"><?= htmlspecialchars($group['label'] ?? '') ?></p>
                            <h2><?= htmlspecialchars($group['description'] ?? '') ?></h2>
                        </div>
                        <div class="faq-panel__count"><?= (int) count($group['items'] ?? []) ?> ответов</div>
                    </div>

                    <div class="faq-grid">
                        <?php foreach (($group['items'] ?? []) as $item): ?>
                            <article class="faq-card">
                                <div class="faq-card__badge"><?= htmlspecialchars($group['label'] ?? '') ?></div>
                                <h3><?= htmlspecialchars($item['question'] ?? '') ?></h3>
                                <div class="faq-card__answer">
                                    <?php foreach (preg_split("/\r\n|\r|\n/", (string) ($item['answer'] ?? '')) as $paragraph): ?>
                                        <?php if (trim($paragraph) === '') { continue; } ?>
                                        <p><?= htmlspecialchars($paragraph) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="faq-empty">
            <h2>FAQ пока пустой</h2>
            <p>Опубликованные вопросы появятся здесь после заполнения модуля.</p>
        </div>
    <?php endif; ?>
</section>
<script>
document.documentElement.classList.add('js');
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-faq-tabs]');
    if (!root) {
        return;
    }

    var tabs = root.querySelectorAll('[data-tab-target]');
    var panels = root.querySelectorAll('[data-tab-panel]');

    function activateTab(target) {
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-tab-target') === target;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-tab-panel') === target;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-tab-target'));
        });
    });

    activateTab('<?= htmlspecialchars($primaryGroup, ENT_QUOTES, 'UTF-8') ?>');
});
</script>
