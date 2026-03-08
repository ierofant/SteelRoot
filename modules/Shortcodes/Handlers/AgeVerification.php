<?php
namespace Modules\Shortcodes\Handlers;

/**
 * Handler for {age_verification} shortcode.
 * Renders a fullscreen age-gate overlay.
 * JS behaviour lives in /assets/js/age-verification.js.
 */
class AgeVerification
{
    public static function render(string $content): string
    {
        return <<<HTML
<div class="age-verification-overlay" id="ageVerification" role="dialog" aria-modal="true">
    <div class="age-verification-modal">
        <div class="age-verification-content">
            <span class="age-icon" aria-hidden="true">🔞</span>
            <h2>Подтверждение возраста</h2>
            <p>Данный контент предназначен для лиц старше 18 лет.<br>
               Продолжая просмотр, вы подтверждаете свой возраст.</p>
            <div class="age-buttons">
                <button class="age-confirm" data-age-accept>Мне есть 18 лет</button>
                <button class="age-decline" data-age-decline>Выход</button>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
