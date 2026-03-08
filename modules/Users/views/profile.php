<?php $user = $user ?? null; ?>
<section class="profile-page profile-page-shell">
    <div class="profile-page-header">
        <p class="eyebrow profile-page-kicker">Account</p>
        <h1 class="profile-page-title">Profile</h1>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($message) && $message !== 'updated' && $message !== 'avatar'): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (($message ?? '') === 'updated'): ?><div class="alert success">Profile updated</div><?php endif; ?>
    <?php if (($message ?? '') === 'avatar'): ?><div class="alert success">Avatar updated</div><?php endif; ?>
    <div class="profile-grid">
        <div class="panel">
            <h3>Details</h3>
            <form method="POST" action="/profile/update">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <div class="form-field">
                    <label>Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label>Profile visibility</label>
                    <select name="profile_visibility">
                        <?php foreach (($visibilityOptions ?? ['public','private']) as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>"<?= ($user['profile_visibility'] ?? 'public') === $opt ? ' selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Signature (plain text, 300 chars max)</label>
                    <textarea name="signature" maxlength="300" rows="3"><?= htmlspecialchars($user['signature'] ?? '') ?></textarea>
                </div>
                <div class="form-field">
                    <label>New password (optional)</label>
                    <input type="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-field">
                    <label>Confirm password</label>
                    <input type="password" name="password_confirm" autocomplete="new-password">
                </div>
                <div class="profile-page-actions">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn ghost" href="/profile/avatar/editor">Изменить аватар</a>
                    <a class="btn ghost" href="/logout" onclick="event.preventDefault();document.getElementById('logout-form').submit();">Logout</a>
                </div>
            </form>
            <form id="logout-form" method="POST" action="/logout" class="u-hide">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($logoutToken ?? '') ?>">
            </form>
        </div>
        <?php include __DIR__ . '/profile_avatar.php'; ?>
    </div>
</section>
