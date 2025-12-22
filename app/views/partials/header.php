<?php
$settings = $settings ?? [];
$locale = $currentLocale ?? 'en';
$localeMode = $localeMode ?? ($settings['locale_mode'] ?? 'multi');
$availableLocales = $availableLocales ?? ['en', 'ru'];
$adminPrefix = $adminPrefix ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin');
$menuDecoded = $menuItems ?? [];
$menuEnabled = !empty($menuEnabled);
$isAdmin = !empty($isAdmin);
$queryParams = $queryParams ?? [];
$currentPath = $requestPath ?? '/';
if (!$menuEnabled) {
    $rawEnabled = $settings['modules_enabled'] ?? '';
    if ($rawEnabled) {
        $decoded = json_decode($rawEnabled, true);
        if (is_array($decoded) && in_array('menu', array_map('strtolower', $decoded), true)) {
            $menuEnabled = true;
        }
    }
}
if ((!is_array($menuDecoded) || empty($menuDecoded)) && !$menuEnabled) {
    $menuDecoded = [
        ['label_ru' => 'Главная', 'label_en' => 'Home', 'url' => '/', 'enabled' => true, 'admin_only' => false],
        ['label_ru' => 'Контакты', 'label_en' => 'Contact', 'url' => '/contact', 'enabled' => true, 'admin_only' => false],
        ['label_ru' => 'Статьи', 'label_en' => 'Articles', 'url' => '/articles', 'enabled' => true, 'admin_only' => false],
        ['label_ru' => 'Галерея', 'label_en' => 'Gallery', 'url' => '/gallery', 'enabled' => true, 'admin_only' => false],
        ['label_ru' => 'Поиск', 'label_en' => 'Search', 'url' => '/search', 'enabled' => true, 'admin_only' => false],
        ['label_ru' => 'Админ', 'label_en' => 'Admin', 'url' => $adminPrefix, 'enabled' => true, 'admin_only' => true],
    ];
}
$langSwitchEnabled = ($localeMode === 'multi');
$buildLangUrl = function (string $code, string $path, array $queryParams): string {
    $qs = $queryParams;
    $qs['lang'] = $code;
    $query = http_build_query($qs);
    return $path . ($query ? '?' . $query : '');
};
?>
<header class="topbar">
    <div class="brand">
        <?php if (!empty($settings['theme_logo'])): ?>
            <img src="<?= htmlspecialchars($settings['theme_logo']) ?>" alt="Logo" style="height:32px;">
        <?php else: ?>
            <span style="font-weight:800;">SteelRoot</span>
        <?php endif; ?>
    </div>
    <div class="right-tools" style="display:flex;align-items:center;gap:10px; margin-left:auto;">
        <?php if ($langSwitchEnabled): ?>
            <div class="lang-switch">
                <?php foreach ($availableLocales as $code): ?>
                    <a class="<?= $code === $locale ? 'active' : '' ?>" href="<?= htmlspecialchars($buildLangUrl($code, $currentPath, $queryParams)) ?>"><?= htmlspecialchars(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button class="menu-toggle" onclick="(function(){const nav=document.querySelector('.topbar nav');const isOpen=nav.classList.toggle('open');document.body.classList.toggle('menu-open', isOpen);})();">Menu</button>
    </div>
    <nav class="main-nav">
        <button class="menu-close" onclick="(function(){const nav=document.querySelector('.topbar nav');nav.classList.remove('open');document.body.classList.remove('menu-open');})();">×</button>
        <?php
            $menuItems = $menuDecoded;
            include APP_ROOT . '/app/views/partials/menu.php';
        ?>
        <div class="nav-right">
            <?php if (class_exists(\Core\Slot::class)) { \Core\Slot::render('user-nav'); } ?>
        </div>
    </nav>
</header>
<div class="mobile-user-fab" hidden>
    <?php if (class_exists(\Core\Slot::class)) { \Core\Slot::render('user-nav'); } ?>
</div>
