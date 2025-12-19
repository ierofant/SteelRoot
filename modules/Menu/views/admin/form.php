<div class="card stack glass">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('menu.admin.title') ?></p>
            <h3><?= htmlspecialchars($title ?? '') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($adminPrefix ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin')) ?>/menu"><?= __('menu.admin.back') ?></a>
    </div>
    <form method="post" action="<?= htmlspecialchars($action ?? '') ?>" class="stack" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field">
                <span><?= __('menu.field.label_ru') ?></span>
                <input type="text" name="label_ru" value="<?= htmlspecialchars($item['label_ru'] ?? '') ?>" required>
            </label>
            <label class="field">
                <span><?= __('menu.field.label_en') ?></span>
                <input type="text" name="label_en" value="<?= htmlspecialchars($item['label_en'] ?? '') ?>" required>
            </label>
        </div>
        <label class="field">
            <span>URL</span>
            <input type="text" name="url" value="<?= htmlspecialchars($item['url'] ?? '') ?>" required placeholder="/path">
        </label>
        <div class="grid two">
            <label class="field">
                <span><?= __('menu.field.title_ru') ?></span>
                <input type="text" name="title_ru" value="<?= htmlspecialchars($item['title_ru'] ?? '') ?>">
            </label>
            <label class="field">
                <span><?= __('menu.field.title_en') ?></span>
                <input type="text" name="title_en" value="<?= htmlspecialchars($item['title_en'] ?? '') ?>">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span><?= __('menu.field.description_ru') ?></span>
                <textarea name="description_ru" rows="3"><?= htmlspecialchars($item['description_ru'] ?? '') ?></textarea>
            </label>
            <label class="field">
                <span><?= __('menu.field.description_en') ?></span>
                <textarea name="description_en" rows="3"><?= htmlspecialchars($item['description_en'] ?? '') ?></textarea>
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span><?= __('menu.field.canonical_url') ?></span>
                <input type="text" name="canonical_url" value="<?= htmlspecialchars($item['canonical_url'] ?? '') ?>" placeholder="https://example.com/page">
            </label>
            <label class="field">
                <span><?= __('menu.field.image_url') ?></span>
                <input type="text" name="image_url" value="<?= htmlspecialchars($item['image_url'] ?? '') ?>" placeholder="https://example.com/og.jpg">
            </label>
        </div>
        <div class="grid two">
            <label class="field">
                <span><?= __('menu.field.image_upload') ?></span>
                <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp">
            </label>
            <div class="field">
                <span><?= __('menu.field.image_preview') ?></span>
                <?php if (!empty($item['image_url'])): ?>
                    <div class="menu-image-preview">
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="OG image" loading="lazy">
                    </div>
                <?php else: ?>
                    <div class="menu-image-preview placeholder"><?= __('menu.preview.default') ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid three">
            <label class="field">
                <span><?= __('menu.field.position') ?></span>
                <input type="number" name="position" value="<?= htmlspecialchars((int)($item['position'] ?? 0)) ?>">
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="enabled" value="1" <?= !empty($item['enabled']) ? 'checked' : '' ?>>
                <span><?= __('menu.field.enabled') ?></span>
            </label>
            <label class="field checkbox">
                <input type="checkbox" name="admin_only" value="1" <?= !empty($item['admin_only']) ? 'checked' : '' ?>>
                <span><?= __('menu.field.admin_only') ?></span>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('menu.admin.save') ?></button>
        </div>
    </form>
</div>
