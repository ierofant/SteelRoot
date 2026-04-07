<?php
$name      = htmlspecialchars($name ?? '');
$link      = htmlspecialchars($link ?? '#');
$site_name = $site_name ?? 'TattooRoot';
$subject   = $subject ?? '';

ob_start(); ?>
<div style="margin:0 0 14px;">
    <span style="display:inline-block;padding:7px 12px;border:1px solid #34241f;background:#181312;color:#d7b08a;font-family:'Courier New',Courier,monospace;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;">Security Request</span>
</div>
<h1>Reset your password</h1>
<?php if ($name !== ''): ?><p style="font-size:18px;color:#f0ebe3;">Hi <?= $name ?>,</p><?php endif; ?>
<p style="font-size:17px;color:#e5ded5;">We received a request to reset the password for your <?= htmlspecialchars($site_name) ?> account.</p>
<p style="margin-bottom:0;color:#b9b1a7;">Use the button below to create a new password and get back into your account.</p>
<div class="btn-wrap" style="margin:32px 0 26px;">
    <a href="<?= $link ?>" class="btn" style="background:linear-gradient(135deg,#c62525 0%,#8f1414 100%);box-shadow:0 10px 30px rgba(155,26,26,0.28);padding:15px 34px;">Reset password</a>
</div>
<div style="margin:0 0 24px;padding:18px 20px;border:1px solid #241d1a;background:#161312;">
    <p style="margin:0 0 8px;font-family:'Courier New',Courier,monospace;font-size:11px;letter-spacing:0.14em;text-transform:uppercase;color:#a37755;">What you need to know</p>
    <p style="margin:0 0 8px;color:#d3cbc1;">This reset link stays active for <strong style="color:#f2ede6;">1 hour</strong>.</p>
    <p style="margin:0;color:#d3cbc1;">If the button does not open, use the secure link below.</p>
</div>
<div class="divider"><span class="divider-gem">&#9670;</span></div>
<p style="margin-bottom:10px;color:#cfc6bc;">Direct reset link:</p>
<p><span class="link-fallback" style="background:#151515;border:1px solid #1f1f1f;padding:14px 16px;color:#8d6dff;"><?= $link ?></span></p>
<div style="margin-top:26px;padding-top:22px;border-top:1px solid #1c1c1c;">
    <p style="margin-bottom:8px;color:#f0ebe3;">Did not request this?</p>
    <p style="margin:0;color:#9f978d;">You can safely ignore this email. Your current password will keep working until you choose a new one.</p>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
