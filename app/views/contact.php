<?php
ob_start();
$captcha = $GLOBALS['settingsAll'] ?? [];
?>
<section class="contact-hero">
    <div>
        <p class="eyebrow">Свяжитесь</p>
        <h1>Контакты</h1>
        <p class="muted">Оставьте сообщение — мы ответим в ближайшее время.</p>
    </div>
</section>

<section class="contact-grid">
    <div class="contact-card stack">
        <?php if (!empty($errors)): ?>
            <div class="alert danger">
                <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($sent)): ?>
            <div class="alert success">Сообщение отправлено.</div>
        <?php endif; ?>
        <form method="post" class="stack" <?= !empty($hasFile) ? 'enctype="multipart/form-data"' : '' ?>>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <?php foreach ($fields as $field): ?>
                <label class="field">
                    <span><?= htmlspecialchars($field['label']) ?></span>
                    <?php if (($field['type'] ?? 'text') === 'textarea'): ?>
                        <textarea name="<?= htmlspecialchars($field['name']) ?>" <?= !empty($field['required']) ? 'required' : '' ?> rows="4"></textarea>
                    <?php elseif (($field['type'] ?? '') === 'file'): ?>
                        <input type="file" name="<?= htmlspecialchars($field['name']) ?>" <?= !empty($field['required']) ? 'required' : '' ?> accept="image/*,application/pdf,.doc,.docx">
                    <?php else: ?>
                        <input type="<?= htmlspecialchars($field['type'] ?? 'text') ?>" name="<?= htmlspecialchars($field['name']) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
            <?php if (!empty($captcha['captcha_provider']) && $captcha['captcha_provider'] === 'google' && !empty($captcha['captcha_site_key'])): ?>
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($captcha['captcha_site_key']) ?>"></div>
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <?php elseif (!empty($captcha['captcha_provider']) && !empty($captcha['captcha_site_key'])): ?>
                <script src="https://smartcaptcha.yandexcloud.net/captcha.js" defer></script>
                <div class="smart-captcha" data-sitekey="<?= htmlspecialchars($captcha['captcha_site_key']) ?>"></div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn primary">Отправить</button>
                <p class="muted">Мы используем форму только для связи, без спама.</p>
            </div>
        </form>
    </div>
    <div class="contact-card stack contact-info">
        <p class="eyebrow">Контакты</p>
        <h3>Всегда на связи</h3>
        <p class="muted">Заполните форму или напишите напрямую.</p>
        <div class="info-line">
            <span>Почта</span>
            <strong><?= htmlspecialchars($GLOBALS['settingsAll']['contact_email'] ?? 'hello@example.com') ?></strong>
        </div>
        <div class="info-line">
            <span>Сайт</span>
            <strong><?= htmlspecialchars($GLOBALS['settingsAll']['site_url'] ?? 'https://example.com') ?></strong>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
// If Renderer layout is active, just echo content (layout wraps it).
if (isset($this) && method_exists($this, 'hasContentTemplate') && $this->hasContentTemplate()) {
    echo $content;
} else {
    include __DIR__ . '/layout.php';
}
?>
