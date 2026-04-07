<?php
use Core\Asset;

$error   = $error ?? null;
$success = $success ?? null;
$email   = $email ?? '';
$remember = !empty($remember);
$captcha  = $captcha ?? [];
$googleMode = strtolower(trim((string)($captcha['google_mode'] ?? 'v2')));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <?= Asset::styleTag('/modules/Users/assets/css/users-auth.css') ?>
</head>
<body>
<div class="users-auth">
    <div class="users-auth-box">
        <div class="users-auth-logo">TattooRoot</div>

        <?php if ($error): ?>
            <div class="ua-alert ua-alert--danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="ua-alert ua-alert--success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="users-auth-card">
            <form id="auth-login-form" method="POST" action="/login" novalidate
                  data-captcha-provider="<?= htmlspecialchars((string)($captcha['provider'] ?? 'none')) ?>"
                  data-captcha-mode="<?= htmlspecialchars($googleMode) ?>"
                  data-captcha-sitekey="<?= htmlspecialchars((string)($captcha['site_key'] ?? '')) ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <div class="users-auth-hp" aria-hidden="true">
                    <label>Leave blank <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="ua-field">
                    <label class="ua-label" for="f-email">Email</label>
                    <input id="f-email" type="email" name="email" autocomplete="email"
                           value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="ua-field">
                    <label class="ua-label" for="f-pass">Пароль</label>
                    <div class="ua-pass-wrap">
                        <input id="f-pass" type="password" name="password"
                               autocomplete="current-password" required>
                        <button type="button" id="ua-pass-toggle" class="ua-pass-btn"
                                aria-label="Показать пароль">◉</button>
                    </div>
                </div>

                <label class="ua-remember">
                    <input type="checkbox" name="remember" value="1"<?= $remember ? ' checked' : '' ?>>
                    <span>Запомнить меня</span>
                </label>

                <?php if (!empty($captcha) && ($captcha['provider'] ?? 'none') === 'google' && $googleMode === 'v3' && !empty($captcha['site_key'])): ?>
                    <input type="hidden" name="g-recaptcha-response" value="">
                    <script src="https://www.google.com/recaptcha/api.js?render=<?= urlencode((string)$captcha['site_key']) ?>" async defer></script>
                <?php elseif (!empty($captcha) && ($captcha['provider'] ?? 'none') === 'google' && !empty($captcha['site_key'])): ?>
                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captcha['site_key']) ?>"></div>
                    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <?php elseif (!empty($captcha) && ($captcha['provider'] ?? 'none') === 'yandex' && !empty($captcha['site_key'])): ?>
                    <div class="smart-captcha" data-sitekey="<?= htmlspecialchars($captcha['site_key']) ?>"></div>
                    <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
                <?php endif; ?>

                <button type="submit" class="ua-btn">Войти</button>
            </form>
        </div>

        <div class="ua-footer">
            <a href="/">← На главную</a><br>
            <a href="/forgot-password">Забыли пароль?</a> &nbsp;·&nbsp; <a href="/register">Регистрация</a>
        </div>
    </div>
</div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
(function () {
    var pass = document.getElementById('f-pass');
    var tog  = document.getElementById('ua-pass-toggle');
    if (pass && tog) {
        tog.addEventListener('click', function () {
            var hidden = pass.type === 'password';
            pass.type = hidden ? 'text' : 'password';
            tog.textContent = hidden ? '◎' : '◉';
        });
    }
    var form = document.getElementById('auth-login-form');
    if (!form) return;
    var provider = form.dataset.captchaProvider || 'none';
    var mode     = form.dataset.captchaMode || 'v2';
    var sitekey  = form.dataset.captchaSitekey || '';
    var tokenEl  = form.querySelector('input[name="g-recaptcha-response"]');
    if (provider !== 'google' || mode !== 'v3' || !sitekey || !tokenEl) return;
    var pending = false;
    form.addEventListener('submit', function (e) {
        if (tokenEl.value && form.dataset.ready === '1') return;
        e.preventDefault();
        if (pending) return;
        pending = true;
        var run = function () {
            if (!window.grecaptcha || typeof grecaptcha.execute !== 'function') { pending = false; return; }
            grecaptcha.execute(sitekey, { action: 'login' }).then(function (t) {
                tokenEl.value = t; form.dataset.ready = '1'; pending = false;
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }).catch(function () { pending = false; });
        };
        window.grecaptcha && grecaptcha.ready ? grecaptcha.ready(run) : setTimeout(run, 0);
    });
})();
</script>
</body>
</html>
