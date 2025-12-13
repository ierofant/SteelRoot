<?php ob_start(); ?>
<style>
    .public-profile {max-width:640px;margin:32px auto;padding:24px;border-radius:18px;background:linear-gradient(145deg,#0f1320,#121829);border:1px solid rgba(255,255,255,0.05);box-shadow:0 16px 40px rgba(0,0,0,0.35);color:#e7ecff;}
    .public-profile .avatar {width:96px;height:96px;border-radius:26px;background:linear-gradient(135deg,#1c1f2d,#292f4f);background-size:cover;background-position:center;display:grid;place-items:center;color:#dce4ff;font-size:32px;font-weight:700;border:1px solid rgba(255,255,255,0.08);}
    .public-profile .pill {display:inline-block;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#cfd6f3;font-size:13px;}
</style>
<?php $u = $user ?? []; $letter = strtoupper(substr($u['name'] ?? 'U', 0, 1)); ?>
<section class="public-profile">
    <div style="display:flex;align-items:center;gap:18px;">
        <div class="avatar" style="<?= !empty($u['avatar']) ? "background-image:url('".htmlspecialchars($u['avatar'])."')" : '' ?>">
            <?= empty($u['avatar']) ? htmlspecialchars($letter) : '' ?>
        </div>
        <div>
            <h1 style="margin:0;"><?= htmlspecialchars($u['name'] ?? 'User') ?></h1>
            <div class="pill"><?= htmlspecialchars(ucfirst($u['role'] ?? 'user')) ?></div>
        </div>
    </div>
</section>
<?php $content = ob_get_clean(); include APP_ROOT . '/app/views/layout.php'; ?>
