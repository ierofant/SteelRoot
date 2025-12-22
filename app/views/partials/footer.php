<?php
$s = $settings ?? [];
$loc = $currentLocale ?? 'en';
$site = $s['site_name'] ?? 'SteelRoot';
$defaults = [
    'en' => [
        ['title' => 'About', 'body' => 'SteelRoot — modular starter to build content-rich sites.'],
        ['title' => 'Contact', 'body' => 'Reach us via contact form or email.'],
        ['title' => 'Updates', 'body' => 'New modules, gallery drops and articles weekly.'],
    ],
    'ru' => [
        ['title' => 'О проекте', 'body' => 'SteelRoot — модульный стартовый шаблон для контентных сайтов.'],
        ['title' => 'Связь', 'body' => 'Пишите через форму контактов или на почту.'],
        ['title' => 'Обновления', 'body' => 'Новые модули, галерея и статьи каждую неделю.'],
    ],
];
$fallbackCols = $defaults[$loc] ?? $defaults['en'];
$col = function (int $i) use ($s, $loc, $fallbackCols) {
    $tKey = $loc === 'ru' ? "footer_col{$i}_title_ru" : "footer_col{$i}_title_en";
    $bKey = $loc === 'ru' ? "footer_col{$i}_body_ru" : "footer_col{$i}_body_en";
    $title = trim($s[$tKey] ?? '');
    $body = trim($s[$bKey] ?? '');
    if ($title === '' && $body === '') {
        return $fallbackCols[$i - 1] ?? ['title' => '', 'body' => ''];
    }
    return ['title' => $title, 'body' => $body];
};
$c1 = $col(1); $c2 = $col(2); $c3 = $col(3);
?>
<footer class="footer">
    <div class="footer-cols">
        <?php foreach ([$c1,$c2,$c3] as $c): ?>
            <div class="footer-col">
                <?php if (!empty($c['title'])): ?><h4><?= htmlspecialchars($c['title']) ?></h4><?php endif; ?>
                <?php if (!empty($c['body'])): ?><div class="footer-body"><?= $c['body'] ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="footer-copy">&copy; <?= date('Y') ?> <?= htmlspecialchars($site) ?></p>
    <?php if (!empty($s['footer_copy_enabled'])): ?>
        <p class="footer-credit">crafted with care by <a href="mailto:ierofant@vivaldi.net" target="_blank" rel="nofollow">ierofant</a> • <a href="https://staffstyle.ru" target="_blank" rel="nofollow">staffstyle.ru</a></p>
    <?php endif; ?>
</footer>
<?php
$usersEnabled = !empty($usersEnabled);
?>
<?php if ($usersEnabled): ?>
    <link rel="stylesheet" href="/assets/css/profile-fab.css">
    <link rel="stylesheet" href="/assets/css/profile-panel.css">
    <link rel="stylesheet" href="/assets/css/user-slot.css">
    <script src="/assets/js/profile-panel.js"></script>
<?php endif; ?>
<?php
// Popups module: cookie popup auto loader via settings (popups_cookie_*)
$cookieEnabled = ($s['popups_cookie_enabled'] ?? '0') === '1';
if ($cookieEnabled): ?>
    <div
        data-cookie-popup="1"
        data-cookie-enabled="1"
        data-cookie-text="<?= htmlspecialchars($s['popups_cookie_text'] ?? '') ?>"
        data-cookie-button="<?= htmlspecialchars($s['popups_cookie_button'] ?? '') ?>"
        data-cookie-position="<?= htmlspecialchars($s['popups_cookie_position'] ?? 'bottom-right') ?>"
        data-cookie-store="<?= htmlspecialchars($s['popups_cookie_store'] ?? 'local') ?>"
        data-cookie-key="<?= htmlspecialchars($s['popups_cookie_key'] ?? 'cookie_policy_accepted') ?>"
    ></div>
    <script src="/assets/js/popup_cookie.js"></script>
<?php endif; ?>
