<?php ob_start(); ?>
<style>
    .profile-grid {display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;}
    .panel {background:linear-gradient(145deg,#101018,#181c29);border:1px solid rgba(255,255,255,0.05);border-radius:18px;padding:18px;box-shadow:0 10px 40px rgba(0,0,0,0.35);}
    .panel h3 {margin:0 0 12px;font-size:18px;color:#e8ecff;}
    .form-field {display:flex;flex-direction:column;gap:6px;margin-bottom:12px;}
    .form-field label {font-size:13px;color:#9da7c2;}
    .form-field input {padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:#0f131f;color:#f3f5ff;}
    .form-field textarea {padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:#0f131f;color:#f3f5ff;min-height:96px;}
    .btn {display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:none;cursor:pointer;background:linear-gradient(120deg,#4f6cff,#7c3aed);color:#fff;font-weight:600;box-shadow:0 8px 30px rgba(79,108,255,0.25);}
    .btn.ghost {background:transparent;border:1px solid rgba(255,255,255,0.15);}
    .avatar-block {display:flex;align-items:center;gap:14px;}
    .avatar-preview {width:96px;height:96px;border-radius:18px;overflow:hidden;border:1px solid rgba(255,255,255,0.08);background:radial-gradient(circle at 30% 30%,rgba(255,255,255,0.08),rgba(255,255,255,0.02));display:grid;place-items:center;color:#cdd5f5;font-size:32px;}
    .alert {padding:12px 14px;border-radius:14px;margin-bottom:12px;}
    .alert.success {background:rgba(46,204,113,0.1);border:1px solid rgba(46,204,113,0.4);color:#b6f3cf;}
    .alert.danger {background:rgba(231,76,60,0.1);border:1px solid rgba(231,76,60,0.4);color:#f6c1b6;}
    @media (max-width: 820px) { .profile-grid {grid-template-columns:1fr;} }
</style>
<?php $user = $user ?? null; ?>
<section class="profile-page" style="max-width:1100px;margin:24px auto;padding:10px 14px;">
    <div style="margin-bottom:16px;">
        <p class="eyebrow" style="color:#8fa1d8;letter-spacing:0.08em;text-transform:uppercase;font-size:11px;">Account</p>
        <h1 style="color:#f5f7ff;margin:6px 0 0;font-size:26px;">Profile</h1>
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
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <button class="btn" type="submit">Save</button>
                    <a class="btn ghost" href="/profile/avatar/editor">Изменить аватар</a>
                    <a class="btn ghost" href="/logout" onclick="event.preventDefault();document.getElementById('logout-form').submit();">Logout</a>
                </div>
            </form>
            <form id="logout-form" method="POST" action="/logout" style="display:none;">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Core\Csrf::token('logout')) ?>">
            </form>
        </div>
        <?php include __DIR__ . '/profile_avatar.php'; ?>
    </div>
</section>
<?php $content = ob_get_clean(); include APP_ROOT . '/app/views/layout.php'; ?>
