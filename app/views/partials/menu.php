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
        <?php
            $childrenRaw = is_array($item['children'] ?? null) ? $item['children'] : [];
            $children = array_values(array_filter($childrenRaw, static function ($child) use ($isAdmin) {
                if (!is_array($child)) {
                    return false;
                }
                if (empty($child['enabled'])) {
                    return false;
                }
                if (!empty($child['admin_only']) && !$isAdmin) {
                    return false;
                }
                return true;
            }));
        ?>
        <?php
            $isAnchor = !empty($item['is_anchor']) && !empty($children);
            $icon = htmlspecialchars($item['icon'] ?? '');
            $iconHtml = $icon !== '' ? '<span class="menu-icon" aria-hidden="true">' . $icon . '</span>' : '';
        ?>
        <li class="menu-item <?= !empty($children) ? 'has-children' : '' ?><?= $isAnchor ? ' menu-item--anchor' : '' ?>">
            <?php if (($item['url'] ?? '') === '/search'): ?>
                <a class="menu-link menu-search-icon" href="/search" title="<?= htmlspecialchars($label) ?>" aria-label="<?= htmlspecialchars($label) ?>" data-search-trigger>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </a>
            <?php elseif ($isAnchor): ?>
                <span class="menu-link menu-link--anchor" tabindex="0"><?= htmlspecialchars($label) ?><?= $iconHtml ?><svg class="menu-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" aria-hidden="true"><polyline points="6 9 12 15 18 9" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
            <?php else: ?>
                <a class="menu-link" href="<?= htmlspecialchars($item['url'] ?? '#') ?>"><?= htmlspecialchars($label) ?><?= $iconHtml ?></a>
            <?php endif; ?>
            <?php if (!empty($children)): ?>
                <ul class="submenu">
                    <?php foreach ($children as $child): ?>
                        <?php if (empty($child['enabled'])) continue; ?>
                        <?php if (!empty($child['admin_only']) && !$isAdmin) continue; ?>
                        <?php $childLabel = $child['label'] ?? ''; ?>
                        <?php if ($childLabel === '' && isset($child['label_ru'], $child['label_en'])): ?>
                            <?php $childLabel = $locale === 'ru' ? $child['label_ru'] : $child['label_en']; ?>
                        <?php endif; ?>
                        <?php $childIcon = htmlspecialchars($child['icon'] ?? ''); ?>
                        <?php $childIconHtml = $childIcon !== '' ? '<span class="menu-icon" aria-hidden="true">' . $childIcon . '</span>' : ''; ?>
                        <li class="menu-item">
                            <a class="menu-link" href="<?= htmlspecialchars($child['url'] ?? '#') ?>"><?= htmlspecialchars($childLabel) ?><?= $childIconHtml ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
