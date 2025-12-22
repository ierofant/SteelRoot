<style>
    .avatar-editor-shell {max-width:900px;margin:20px auto;padding:20px;}
    .editor-card {background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.08);backdrop-filter:blur(16px);border-radius:22px;padding:18px;box-shadow:0 18px 50px rgba(0,0,0,0.45);}
    .editor-grid {display:grid;grid-template-columns:1.2fr 0.8fr;gap:18px;}
    .preview-area {position:relative;overflow:hidden;border-radius:18px;background:#0e121d;height:420px;border:1px solid rgba(255,255,255,0.06);}
    .preview-area .mask {position:absolute;inset:0;border-radius:50%;pointer-events:none;border:2px dashed rgba(255,255,255,0.15);box-shadow:0 0 0 999px rgba(0,0,0,0.35);}
    .preview-img {position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);user-select:none;cursor:grab;max-width:none;max-height:none;}
    .panel {background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.07);border-radius:14px;padding:14px;}
    .panel h3 {margin:0 0 8px;font-size:16px;color:#e9ecff;}
    .field {margin:10px 0;}
    .field input[type="file"] {width:100%;color:#cfd6f3;}
    .slider {width:100%;}
    .actions {display:flex;gap:10px;margin-top:10px;}
    .btn {padding:12px 14px;border:none;border-radius:14px;cursor:pointer;font-weight:700;color:#fff;background:linear-gradient(120deg,#ff4f8b,#c86bfa);box-shadow:0 10px 32px rgba(255,79,139,0.25);}
    .btn.ghost {background:transparent;border:1px solid rgba(255,255,255,0.2);color:#cfd6f3;box-shadow:none;}
    .error {color:#ff8b8b;font-size:13px;min-height:16px;}
    @media (max-width: 900px){.editor-grid{grid-template-columns:1fr;} .preview-area{height:360px;}}
</style>
<div class="avatar-editor-shell">
    <div class="editor-card">
        <div class="editor-grid">
            <div class="preview-area" id="avatar-canvas">
                <?php $avatarSrc = $user['avatar'] ?? 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='; ?>
                <img id="avatar-image" class="preview-img" alt="preview" draggable="false" data-current="<?= htmlspecialchars($user['avatar'] ?? '') ?>" src="<?= htmlspecialchars($avatarSrc) ?>" />
                <div class="mask"></div>
            </div>
            <div class="panel">
                <h3>Загрузка</h3>
                <div class="field">
                    <input type="file" id="avatar-file" accept="image/*">
                </div>
                <div class="field">
                    <label for="zoom">Масштаб</label>
                    <input type="range" id="zoom" class="slider" min="0.5" max="3" step="0.01" value="1">
                </div>
                <div class="error" id="err-box"></div>
                <div class="actions">
                    <button class="btn" id="save-avatar">Сохранить аватар</button>
                    <a class="btn ghost" href="/profile">Отмена</a>
                </div>
            </div>
        </div>
    </div>
</div>
<form id="avatar-form" action="/profile/avatar/crop" method="POST" enctype="multipart/form-data" style="display:none;">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <input type="file" name="avatar" id="hidden-file">
    <input type="hidden" name="crop_x" id="crop_x">
    <input type="hidden" name="crop_y" id="crop_y">
    <input type="hidden" name="crop_w" id="crop_w">
    <input type="hidden" name="crop_h" id="crop_h">
    <input type="hidden" name="crop_scale" id="crop_scale">
</form>
<script src="/modules/Users/assets/js/avatar-cropper.js"></script>
