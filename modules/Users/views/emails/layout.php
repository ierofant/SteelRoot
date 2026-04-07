<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?= htmlspecialchars($subject ?? '') ?></title>
<style>
body { margin: 0; padding: 0; background: #0d0d0d; }
a    { color: #9b1a1a; text-decoration: none; }
a:hover { text-decoration: underline; }

.shell {
    background: #0d0d0d;
    padding: 40px 16px;
    font-family: Georgia, "Times New Roman", serif;
}
.card {
    max-width: 560px;
    margin: 0 auto;
    background: #111111;
    border-top: 3px solid #9b1a1a;
}
.email-header {
    padding: 28px 40px 20px;
    border-bottom: 1px solid #1c1c1c;
}
.email-site-name {
    font-family: "Courier New", Courier, monospace;
    font-size: 12px;
    letter-spacing: 0.28em;
    text-transform: uppercase;
    color: #9b1a1a;
    margin: 0;
}
.email-body {
    padding: 36px 40px;
    color: #cdc8c0;
    font-size: 16px;
    line-height: 1.7;
}
.email-body h1 {
    font-size: 22px;
    font-weight: normal;
    color: #ede8e0;
    margin: 0 0 22px;
    letter-spacing: 0.03em;
}
.email-body p {
    margin: 0 0 18px;
}
.email-body p:last-child {
    margin-bottom: 0;
}
.divider {
    margin: 24px 0;
    border: 0;
    border-top: 1px solid #1c1c1c;
    height: 0;
    text-align: center;
    overflow: visible;
    line-height: 0;
}
.divider-gem {
    display: inline-block;
    background: #111111;
    color: #2e2820;
    font-size: 11px;
    padding: 0 10px;
    position: relative;
    top: -6px;
    letter-spacing: 0.1em;
}
.btn-wrap {
    margin: 28px 0;
}
.btn {
    display: inline-block;
    padding: 13px 32px;
    background: #9b1a1a;
    color: #f0ece4;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    text-decoration: none;
}
.link-fallback {
    display: block;
    word-break: break-all;
    font-family: "Courier New", Courier, monospace;
    font-size: 12px;
    color: #5a5248;
}
.email-footer {
    padding: 18px 40px 26px;
    border-top: 1px solid #181818;
}
.email-footer p {
    margin: 0;
    font-family: "Courier New", Courier, monospace;
    font-size: 11px;
    color: #3a3530;
    line-height: 1.6;
}
@media (max-width: 600px) {
    .email-header,
    .email-body,
    .email-footer { padding-left: 24px; padding-right: 24px; }
}
</style>
</head>
<body>
<div class="shell">
    <div class="card">
        <div class="email-header">
            <p class="email-site-name"><?= htmlspecialchars($site_name ?? 'TattooRoot') ?></p>
        </div>
        <div class="email-body">
            <?= $content ?? '' ?>
        </div>
        <div class="email-footer">
            <p>// automated message — do not reply</p>
            <p>// <?= htmlspecialchars($site_name ?? 'TattooRoot') ?></p>
        </div>
    </div>
</div>
</body>
</html>
