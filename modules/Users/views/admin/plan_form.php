<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$plan = $plan ?? [];
$caps = json_decode((string)($plan['capabilities_json'] ?? ''), true);
if (!is_array($caps)) {
    $caps = [];
}
$selectedCaps = array_fill_keys($caps, true);
$capabilityOptions = [
    'gallery.submit' => 'Can upload works',
    'gallery.publish' => 'Can publish directly',
    'profile.extended' => 'Extended profile',
    'profile.links' => 'Studio website',
    'profile.contacts' => 'Public contacts',
    'profile.verified' => 'Verified features',
];
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow">Master plans</p>
            <h3><?= htmlspecialchars($title ?? 'Master plan') ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/plans">Back</a>
    </div>
    <?php if (!empty($error)): ?><div class="alert danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <div class="grid two">
            <label class="field"><span>Name</span><input type="text" name="name" value="<?= htmlspecialchars((string)($plan['name'] ?? '')) ?>" required></label>
            <label class="field"><span>Slug</span><input type="text" name="slug" value="<?= htmlspecialchars((string)($plan['slug'] ?? '')) ?>" required></label>
            <label class="field"><span>Price</span><input type="text" name="price" value="<?= htmlspecialchars((string)($plan['price'] ?? '')) ?>"></label>
            <label class="field"><span>Currency</span><input type="text" name="currency" value="<?= htmlspecialchars((string)($plan['currency'] ?? 'USD')) ?>"></label>
            <label class="field"><span>Period label</span><input type="text" name="period_label" value="<?= htmlspecialchars((string)($plan['period_label'] ?? '')) ?>"></label>
            <label class="field"><span>Duration days</span><input type="number" name="duration_days" value="<?= (int)($plan['duration_days'] ?? 0) ?>"></label>
            <label class="field"><span>Gallery limit</span><input type="number" name="gallery_limit" value="<?= (int)($plan['gallery_limit'] ?? 0) ?>"></label>
            <label class="field"><span>Pinned works limit</span><input type="number" name="pinned_works_limit" value="<?= (int)($plan['pinned_works_limit'] ?? 0) ?>"></label>
            <label class="field"><span>Priority boost</span><input type="number" name="priority_boost" value="<?= (int)($plan['priority_boost'] ?? 0) ?>"></label>
            <label class="field"><span>Sort order</span><input type="number" name="sort_order" value="<?= (int)($plan['sort_order'] ?? 0) ?>"></label>
        </div>
        <label class="field">
            <span>Description</span>
            <textarea name="description" rows="4"><?= htmlspecialchars((string)($plan['description'] ?? '')) ?></textarea>
        </label>
        <div class="users-admin-permissions">
            <label class="field checkbox"><input type="checkbox" name="active" value="1" <?= !empty($plan['active']) ? 'checked' : '' ?>><span>Active</span></label>
            <label class="field checkbox"><input type="checkbox" name="featured" value="1" <?= !empty($plan['featured']) ? 'checked' : '' ?>><span>Featured</span></label>
            <label class="field checkbox"><input type="checkbox" name="allow_cover" value="1" <?= !empty($plan['allow_cover']) ? 'checked' : '' ?>><span>Allow cover</span></label>
            <label class="field checkbox"><input type="checkbox" name="allow_contacts" value="1" <?= !empty($plan['allow_contacts']) ? 'checked' : '' ?>><span>Allow contacts</span></label>
            <label class="field checkbox"><input type="checkbox" name="allow_social_links" value="1" <?= !empty($plan['allow_social_links']) ? 'checked' : '' ?>><span>Allow social links</span></label>
            <label class="field checkbox"><input type="checkbox" name="allow_ratings" value="1" <?= !empty($plan['allow_ratings']) ? 'checked' : '' ?>><span>Allow ratings</span></label>
        </div>
        <div class="card soft stack">
            <p class="eyebrow">Capabilities</p>
            <div class="users-admin-permissions">
                <?php foreach ($capabilityOptions as $key => $label): ?>
                    <label class="field checkbox">
                        <input type="checkbox" name="capabilities[]" value="<?= htmlspecialchars($key) ?>" <?= isset($selectedCaps[$key]) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-actions users-admin-actions">
            <button type="submit" class="btn primary">Save</button>
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users/plans">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
