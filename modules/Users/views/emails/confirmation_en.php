<?php
$name = $name ?? 'friend';
$link = $link ?? '#';
?>
Hello <?= htmlspecialchars($name) ?>,

Please confirm your SteelRoot account by clicking the link:
<?= htmlspecialchars($link) ?>

If you did not request this, ignore the email.
