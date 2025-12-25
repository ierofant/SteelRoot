<?php
$locale = $currentLocale ?? 'en';
$items = $menuItems ?? [];
$isAdmin = !empty($isAdmin);
if (empty($items)) {
    $items = [
        ['url' => '/', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Главная' : 'Home'],
        ['url' => '/contact', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Контакты' : 'Contact'],
    ];
}
?>
<ul class="menu">
    <?php foreach ($items as $item): ?>
        <?php if (empty($item['enabled'])) continue; ?>
        <?php if (!empty($item['admin_only']) && !$isAdmin) continue; ?>
        <?php $label = $item['label'] ?? ''; ?>
        <?php if ($label === '' && isset($item['label_ru'], $item['label_en'])): ?>
            <?php $label = $locale === 'ru' ? $item['label_ru'] : $item['label_en']; ?>
        <?php endif; ?>
        <?php $children = is_array($item['children'] ?? null) ? $item['children'] : []; ?>
        <li class="menu-item <?= !empty($children) ? 'has-children' : '' ?>">
            <a class="menu-link" href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($label) ?></a>
            <?php if (!empty($children)): ?>
                <ul class="submenu">
                    <?php foreach ($children as $child): ?>
                        <?php if (empty($child['enabled'])) continue; ?>
                        <?php if (!empty($child['admin_only']) && !$isAdmin) continue; ?>
                        <?php $childLabel = $child['label'] ?? ''; ?>
                        <?php if ($childLabel === '' && isset($child['label_ru'], $child['label_en'])): ?>
                            <?php $childLabel = $locale === 'ru' ? $child['label_ru'] : $child['label_en']; ?>
                        <?php endif; ?>
                        <li class="menu-item">
                            <a class="menu-link" href="<?= htmlspecialchars($child['url'] ?? '#') ?>"><?= htmlspecialchars($childLabel) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
