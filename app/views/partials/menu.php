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
<?php foreach ($items as $item): ?>
    <?php if (empty($item['enabled'])) continue; ?>
    <?php if (!empty($item['admin_only']) && !$isAdmin) continue; ?>
    <?php $label = $item['label'] ?? ''; ?>
    <?php if ($label === '' && isset($item['label_ru'], $item['label_en'])): ?>
        <?php $label = $locale === 'ru' ? $item['label_ru'] : $item['label_en']; ?>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($label) ?></a>
<?php endforeach; ?>
