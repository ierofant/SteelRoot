<?php
$user = $user ?? [];
$avatar = $user['avatar'] ?? null;
$letterSource = (string)($user['name'] ?? 'U');
$letter = function_exists('mb_strtoupper') && function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($letterSource, 0, 1))
    : strtoupper(substr($letterSource, 0, 1));
?>
<div class="panel">
    <h3>Avatar</h3>
    <div class="avatar-block">
        <div class="avatar-preview" style="<?= $avatar ? "background-image:url('".htmlspecialchars($avatar)."')" : '' ?>;background-size:cover;background-position:center;background-repeat:no-repeat;">
            <?php if (!$avatar): ?>
                <span><?= htmlspecialchars($letter) ?></span>
            <?php endif; ?>
        </div>
        <form method="POST" action="/profile/avatar" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($avatarToken ?? '') ?>">
            <input type="file" name="avatar" accept="image/*" required>
            <button class="btn primary" type="submit">Upload</button>
        </form>
    </div>
</div>
