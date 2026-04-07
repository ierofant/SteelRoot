<?php
use Core\Asset;

$error   = $error   ?? null;
$success = $success ?? null;
$token   = $token   ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый пароль</title>
    <?= Asset::styleTag('/assets/css/admin-login.css') ?>
    <style>
        .alert{margin-bottom:10px;border-radius:12px;padding:10px 12px;text-align:center}
        .alert.danger{background:rgba(231,76,60,0.12);color:#f6c1b6;border:1px solid rgba(231,76,60,0.4)}
        .alert.success{background:rgba(46,204,113,0.12);color:#b6f3cf;border:1px solid rgba(46,204,113,0.4)}
        .alert.success a{color:#86efac;text-decoration:underline}
        .hint{font-size:12px;color:#6b7280;margin-top:4px}
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="logo">Новый пароль</div>

        <?php if ($error): ?>
            <div class="alert danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= $success ?></div>
            <div class="footer-note"><a href="/login">← Войти</a></div>
        <?php elseif ($token !== ''): ?>
        <form method="POST" action="/reset-password" novalidate>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="token"  value="<?= htmlspecialchars($token) ?>">

            <div class="input-group">
                <label>Новый пароль</label>
                <div class="password-wrapper">
                    <input type="password" name="password" autocomplete="new-password" required minlength="8">
                    <button type="button" class="toggle-pass" aria-label="Показать пароль"></button>
                </div>
                <span class="hint">Минимум 8 символов</span>
            </div>

            <div class="input-group">
                <label>Повторите пароль</label>
                <div class="password-wrapper">
                    <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8">
                    <button type="button" class="toggle-pass" aria-label="Показать пароль"></button>
                </div>
            </div>

            <div class="input-group">
                <label>Проверка: <?= htmlspecialchars($captchaQ ?? '') ?></label>
                <input type="number" name="captcha" min="0" max="99" required placeholder="Ответ">
            </div>

            <button class="login-btn" type="submit">Сохранить пароль</button>
            <div class="footer-note"><a href="/login">← Вернуться к входу</a></div>
        </form>
        <?php else: ?>
            <div class="alert danger"><?= htmlspecialchars($error ?? 'Недействительная ссылка') ?></div>
            <div class="footer-note"><a href="/forgot-password">Запросить снова</a></div>
        <?php endif; ?>
    </div>
</div>
<script>
    document.querySelectorAll('.toggle-pass').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = this.previousElementSibling;
            var hidden = input.getAttribute('type') === 'password';
            input.setAttribute('type', hidden ? 'text' : 'password');
            this.classList.toggle('active', hidden);
        });
    });
</script>
</body>
</html>
