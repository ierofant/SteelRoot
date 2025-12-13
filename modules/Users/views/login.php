<?php
$title = $title ?? 'Login';
$error = $error ?? null;
$success = $success ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="/assets/css/admin-login.css?v=1">
    <style>.alert{margin-bottom:10px;border-radius:12px;padding:10px 12px;text-align:center}.alert.danger{background:rgba(231,76,60,0.12);color:#f6c1b6;border:1px solid rgba(231,76,60,0.4)}.alert.success{background:rgba(46,204,113,0.12);color:#b6f3cf;border:1px solid rgba(46,204,113,0.4)}</style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">SteelRoot Users</div>
        <?php include __DIR__ . '/partials/alerts.php'; ?>
        <form method="POST" action="/login" novalidate>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" autocomplete="email" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" aria-label="Show password"></button>
                </div>
            </div>
            <button class="login-btn" type="submit">Sign In</button>
            <div class="footer-note">No account? <a href="/register">Register</a></div>
        </form>
        <div class="error-message"></div>
    </div>
</div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
    (function() {
        const toggle = document.querySelector('.toggle-pass');
        const passInput = document.querySelector('input[name="password"]');
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
