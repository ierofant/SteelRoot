<?php
$locale = $currentLocale ?? ($GLOBALS['currentLocale'] ?? 'en');
$modulesManager = $modules ?? ($GLOBALS['modules'] ?? null);
$menuEnabled = false;
if ($modulesManager && method_exists($modulesManager, 'isEnabled')) {
    $menuEnabled = $modulesManager->isEnabled('menu');
}
if (!$menuEnabled) {
    $settingsAll = $GLOBALS['settingsAll'] ?? [];
    $rawEnabled = $settingsAll['modules_enabled'] ?? '';
    if ($rawEnabled) {
        $decoded = json_decode($rawEnabled, true);
        if (is_array($decoded) && in_array('menu', array_map('strtolower', $decoded), true)) {
            $menuEnabled = true;
        }
    }
}
$items = $menuItems ?? ($GLOBALS['menuItemsPublic'] ?? []);
if (empty($items) && !$menuEnabled) {
    $items = [
        ['url' => '/', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Главная' : 'Home'],
        ['url' => '/contact', 'enabled' => true, 'admin_only' => false, 'label' => $locale === 'ru' ? 'Контакты' : 'Contact'],
    ];
}
?>
<nav class="main-nav">
    <?php foreach ($items as $item): ?>
        <?php if (empty($item['enabled'])) continue; ?>
        <?php if (!empty($item['admin_only']) && empty($_SESSION['admin_auth'])) continue; ?>
        <?php $label = $item['label'] ?? ''; ?>
        <?php if ($label === '' && isset($item['label_ru'], $item['label_en'])): ?>
            <?php $label = $locale === 'ru' ? $item['label_ru'] : $item['label_en']; ?>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
</nav>
