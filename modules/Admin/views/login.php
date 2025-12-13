<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$theme = $settingsAll['theme'] ?? 'dark';
$errorText = $error ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme === 'light' ? 'light' : 'dark') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SteelRoot Admin Login</title>
    <link rel="stylesheet" href="/assets/css/admin-login.css?v=1">
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">SteelRoot Admin</div>
        <form method="POST" action="<?= htmlspecialchars($ap) ?>/login" novalidate>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="user" autocomplete="username" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="pass" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" aria-label="Show password"></button>
                </div>
            </div>
            <?php if (!empty($captcha) && $captcha['provider'] === 'google' && !empty($captcha['site_key'])): ?>
                <div class="input-group">
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captcha['site_key']) ?>"></div>
                </div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <?php elseif (!empty($captcha) && $captcha['provider'] === 'yandex' && !empty($captcha['site_key'])): ?>
                <div class="input-group">
                    <div class="smart-captcha" data-sitekey="<?= htmlspecialchars($captcha['site_key']) ?>"></div>
                </div>
                <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
            <?php endif; ?>
            <button class="login-btn" type="submit">Sign In</button>
            <div class="error-message<?= $errorText !== '' ? ' animate' : '' ?>"><?= htmlspecialchars($errorText) ?></div>
        </form>
        <div class="footer-note">© SteelRoot Framework</div>
    </div>
</div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
    (function() {
        const userInput = document.querySelector('input[name="user"]');
        if (userInput) {
            userInput.focus();
        }
        const toggle = document.querySelector('.toggle-pass');
        const passInput = document.querySelector('input[name="pass"]');
        if (toggle && passInput) {
            toggle.addEventListener('click', () => {
                const isHidden = passInput.getAttribute('type') === 'password';
                passInput.setAttribute('type', isHidden ? 'text' : 'password');
                toggle.classList.toggle('active', isHidden);
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        }
    })();
</script>
</body>
</html>
