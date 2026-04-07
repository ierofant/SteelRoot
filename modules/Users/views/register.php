<?php
use Core\Asset;

$error   = $error ?? null;
$success = $success ?? null;
$captcha = $captcha ?? [];
$googleMode = strtolower(trim((string)($captcha['google_mode'] ?? 'v2')));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
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

        <?php if (!$success): ?>
        <div class="users-auth-card">
            <form id="auth-register-form" method="POST" action="/register" novalidate
                  data-captcha-provider="<?= htmlspecialchars((string)($captcha['provider'] ?? 'none')) ?>"
                  data-captcha-mode="<?= htmlspecialchars($googleMode) ?>"
                  data-captcha-sitekey="<?= htmlspecialchars((string)($captcha['site_key'] ?? '')) ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <div class="users-auth-hp" aria-hidden="true">
                    <label>Leave blank <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="ua-field">
                    <label class="ua-label" for="f-name">Имя</label>
                    <input id="f-name" type="text" name="name" required
                           value="<?= htmlspecialchars($name ?? '') ?>">
                </div>
                <div class="ua-field">
                    <label class="ua-label" for="f-dname">Отображаемое имя</label>
                    <input id="f-dname" type="text" name="display_name"
                           value="<?= htmlspecialchars($display_name ?? '') ?>">
                </div>
                <div class="ua-field">
                    <label class="ua-label" for="f-email">Email</label>
                    <input id="f-email" type="email" name="email" autocomplete="email" required
                           value="<?= htmlspecialchars($email ?? '') ?>">
                </div>
                <div class="ua-field">
                    <label class="ua-label" for="f-pass">Пароль</label>
                    <input id="f-pass" type="password" name="password"
                           autocomplete="new-password" required>
                </div>
                <div class="ua-field">
                    <label class="ua-label" for="f-pass2">Повторите пароль</label>
                    <input id="f-pass2" type="password" name="password_confirm"
                           autocomplete="new-password" required>
                </div>

                <label class="ua-remember">
                    <input type="checkbox" name="policy_ack" value="1" required>
                    <span>Я принимаю <a href="https://tattootoday.org/confidential" target="_blank" rel="noopener noreferrer">политику конфиденциальности</a></span>
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

                <button type="submit" class="ua-btn">Зарегистрироваться</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="ua-footer">
            <a href="/">← На главную</a><br>
            Уже есть аккаунт? <a href="/login">Войти</a>
        </div>
    </div>
</div>
<script nonce="<?= htmlspecialchars($cspNonce ?? '') ?>">
(function () {
    var form = document.getElementById('auth-register-form');
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
            grecaptcha.execute(sitekey, { action: 'register' }).then(function (t) {
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
