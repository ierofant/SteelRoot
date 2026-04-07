<?php
$name = $name ?? 'друг';
$link = $link ?? '#';
?>
Здравствуйте, <?= htmlspecialchars($name) ?>!

Подтвердите аккаунт TattooRoot по ссылке:
<?= htmlspecialchars($link) ?>

Если вы не регистрировались, просто игнорируйте это письмо.
