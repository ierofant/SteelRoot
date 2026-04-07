<?php
use Core\Asset;

$error   = $error   ?? null;
$success = $success ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля</title>
    <?= Asset::styleTag('/assets/css/admin-login.css') ?>
    <style>
        .alert{margin-bottom:10px;border-radius:12px;padding:10px 12px;text-align:center}
        .alert.danger{background:rgba(231,76,60,0.12);color:#f6c1b6;border:1px solid rgba(231,76,60,0.4)}
        .alert.success{background:rgba(46,204,113,0.12);color:#b6f3cf;border:1px solid rgba(46,204,113,0.4)}
        .alert.success a{color:#86efac;text-decoration:underline}
        .captcha-row{display:flex;align-items:center;gap:12px}
        .captcha-label{font-size:14px;color:#a78bfa;white-space:nowrap;font-weight:600}
        .captcha-row input{width:80px;flex-shrink:0}
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">Сброс пароля</div>

        <?php if ($error): ?>
            <div class="alert danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
            <div class="footer-note"><a href="/login">← Вернуться к входу</a></div>
        <?php else: ?>
        <form method="POST" action="/forgot-password" novalidate>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

            <div class="input-group">
                <label>Email аккаунта</label>
                <input type="email" name="email" autocomplete="email" required placeholder="your@email.com">
            </div>

            <div class="input-group">
                <label>Проверка: <?= htmlspecialchars($captchaQ ?? '') ?></label>
                <input type="number" name="captcha" min="0" max="99" required placeholder="Ответ">
            </div>

            <button class="login-btn" type="submit">Отправить ссылку</button>
            <div class="footer-note"><a href="/login">← Вернуться к входу</a></div>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
