<?php
$name      = htmlspecialchars($name      ?? 'friend');
$link      = htmlspecialchars($link      ?? '#');
$site_name = $site_name ?? 'TattooRoot';
$subject   = $subject   ?? '';

ob_start(); ?>
<h1>Confirm your account</h1>
<p>Hey <?= $name ?>,</p>
<p>One step to go. Confirm your registration on <?= htmlspecialchars($site_name) ?> by clicking the button below.</p>
<div class="btn-wrap"><a href="<?= $link ?>" class="btn">Confirm email</a></div>
<div class="divider"><span class="divider-gem">&#9670;</span></div>
<p>Or copy and paste this link:</p>
<p><span class="link-fallback"><?= $link ?></span></p>
<p>The link expires in 24 hours. If you did not create an account, ignore this message.</p>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
