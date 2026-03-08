/**
 * Age Verification
 * Loaded only on pages with {age_verification} shortcode.
 * Consent stored in localStorage for 30 days.
 */
(function () {
    'use strict';

    var KEY      = 'age_verified';
    var MAX_AGE  = 30 * 24 * 60 * 60 * 1000;

    function verified() {
        try {
            var d = JSON.parse(localStorage.getItem(KEY) || 'null');
            return d && d.ts && (Date.now() - d.ts < MAX_AGE);
        } catch (e) { return false; }
    }

    function accept() {
        try { localStorage.setItem(KEY, JSON.stringify({ ts: Date.now() })); } catch (e) {}
        hide();
    }

    function decline() {
        if (document.referrer && document.referrer.indexOf(location.host) !== -1) {
            history.back();
        } else {
            location.href = '/';
        }
    }

    function hide() {
        var el = document.getElementById('ageVerification');
        if (!el) return;
        el.classList.add('hidden');
        document.body.style.overflow = '';
        setTimeout(function () { el && el.parentNode && el.parentNode.removeChild(el); }, 300);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var overlay = document.getElementById('ageVerification');
        if (!overlay) return;

        if (verified()) {
            overlay.parentNode && overlay.parentNode.removeChild(overlay);
            return;
        }

        document.body.style.overflow = 'hidden';

        var btn = overlay.querySelector('[data-age-accept]');
        if (btn) btn.addEventListener('click', accept);

        var out = overlay.querySelector('[data-age-decline]');
        if (out) out.addEventListener('click', decline);
    });
})();
