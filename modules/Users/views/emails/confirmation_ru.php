<?php
$name = $name ?? 'друг';
$link = $link ?? '#';
?>
Здравствуйте, <?= htmlspecialchars($name) ?>!

Подтвердите аккаунт SteelRoot по ссылке:
<?= htmlspecialchars($link) ?>

Если вы не регистрировались, просто игнорируйте это письмо.
