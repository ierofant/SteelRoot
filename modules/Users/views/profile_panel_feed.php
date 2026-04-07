<?php $panelSections = $panelSections ?? []; ?>
<?php if (!empty($panelSections)): ?>
    <section class="profile-panel-feed">
        <div class="profile-panel-feed__head">
            <p class="profile-panel-kicker"><?= htmlspecialchars(__('users.panel.kicker')) ?></p>
            <h3><?= htmlspecialchars(__('users.panel.title')) ?></h3>
        </div>
        <div class="profile-panel-feed__sections">
            <?php foreach ($panelSections as $section): ?>
                <?php $items = (array)($section['items'] ?? []); ?>
                <?php if (empty($items)): continue; endif; ?>
                <section class="profile-panel-section">
                    <div class="profile-panel-section__title"><?= htmlspecialchars(__((string)($section['title_key'] ?? 'users.panel.section.new_works'))) ?></div>
                    <ul class="profile-panel-list">
                        <?php foreach ($items as $item): ?>
                            <li class="profile-panel-list__item">
                                <a href="<?= htmlspecialchars((string)($item['url'] ?? '#')) ?>">
                                    <strong><?= htmlspecialchars((string)($item['title'] ?? '')) ?></strong>
                                    <?php if (!empty($item['meta'])): ?><span><?= htmlspecialchars((string)$item['meta']) ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <section class="profile-panel-feed profile-panel-feed--empty">
        <div class="profile-panel-feed__head">
            <p class="profile-panel-kicker"><?= htmlspecialchars(__('users.panel.kicker')) ?></p>
            <h3><?= htmlspecialchars(__('users.panel.empty.title')) ?></h3>
            <p class="profile-panel-empty-copy"><?= htmlspecialchars(__('users.panel.empty.body')) ?></p>
        </div>
    </section>
<?php endif; ?>
