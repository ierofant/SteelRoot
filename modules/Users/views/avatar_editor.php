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
<form id="avatar-form" action="/profile/avatar/crop" method="POST" enctype="multipart/form-data" class="u-hide">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <input type="file" name="avatar" id="hidden-file">
    <input type="hidden" name="crop_x" id="crop_x">
    <input type="hidden" name="crop_y" id="crop_y">
    <input type="hidden" name="crop_w" id="crop_w">
    <input type="hidden" name="crop_h" id="crop_h">
    <input type="hidden" name="crop_scale" id="crop_scale">
</form>
<script src="/modules/Users/assets/js/avatar-cropper.js"></script>
