<?php
$title = $title ?? 'Register';
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
        <div class="logo">Create account</div>
        <?php include __DIR__ . '/partials/alerts.php'; ?>
        <form method="POST" action="/register" novalidate>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <div class="input-group">
                <label>Name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($name ?? '') ?>">
            </div>
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($email ?? '') ?>">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" autocomplete="new-password" required>
            </div>
            <div class="input-group">
                <label>Confirm password</label>
                <input type="password" name="password_confirm" autocomplete="new-password" required>
            </div>
            <div class="input-group">
                <label>Captcha</label>
                <div class="captcha-placeholder" style="padding:10px;border:1px dashed rgba(255,255,255,0.2);border-radius:10px;color:#a8b3d9;text-align:center;">[ captcha goes here ]</div>
            </div>
            <button class="login-btn" type="submit">Register</button>
            <div class="footer-note">Already have an account? <a href="/login">Sign in</a></div>
        </form>
    </div>
</div>
</body>
</html>
