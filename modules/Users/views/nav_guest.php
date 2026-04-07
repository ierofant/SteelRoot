<?php use Core\Asset; ?>
<nav class="user-nav-guest">
    <a href="/login" class="btn-chip">Login</a>
    <a href="/register" class="btn-chip primary">Register</a>
</nav>
<?php if (!defined('TT_GUEST_NAV_SCRIPT_INCLUDED')): ?>
<?php define('TT_GUEST_NAV_SCRIPT_INCLUDED', true); ?>
<?= Asset::scriptTag('/assets/js/guest-nav.js', ['defer' => true]) ?>
<?php endif; ?>
