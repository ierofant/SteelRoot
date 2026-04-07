<?php
$name      = htmlspecialchars($name      ?? '');
$message   = htmlspecialchars($message   ?? '');
$site_name = $site_name ?? 'TattooRoot';
$subject   = $subject   ?? '';

ob_start(); ?>
<h1>Notification</h1>
<?php if ($name !== ''): ?><p>Hey <?= $name ?>,</p><?php endif; ?>
<p><?= $message ?></p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
