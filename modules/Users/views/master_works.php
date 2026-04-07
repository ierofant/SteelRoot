<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$suggestedTags = $suggestedTags ?? [];
?>
<?= \Core\Asset::styleTag('/modules/Users/assets/css/users.css') ?>
<section class="users-shell users-dashboard">
    <header class="users-card users-card--soft">
        <p class="users-eyebrow"><?= __('users.master_works.eyebrow') ?></p>
        <h1><?= __('users.master_works.title') ?></h1>
        <p class="users-muted"><?= __('users.master_works.subtitle') ?></p>
        <div class="users-meta-row">
            <span class="users-pill"><?= __('users.master_works.folder') ?>: /<?= htmlspecialchars((string)($folderName ?? '')) ?>/</span>
            <a class="users-button users-button--ghost" href="/profile"><?= __('users.master_works.back') ?></a>
        </div>
    </header>

    <?php if (!empty($message)): ?><div class="users-alert users-alert--success"><?= htmlspecialchars((string)$message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="users-alert users-alert--danger"><?= htmlspecialchars((string)$error) ?></div><?php endif; ?>

    <div class="users-grid">
        <section class="users-card">
            <div class="users-card__header">
                <div>
                    <p class="users-eyebrow"><?= __('users.master_works.form.eyebrow') ?></p>
                    <h2><?= __('users.master_works.form.title') ?></h2>
                </div>
            </div>
            <form method="post" action="/profile/works" enctype="multipart/form-data" class="users-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <label class="users-field">
                    <span><?= __('users.master_works.form.file') ?></span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                </label>
                <div class="users-form-grid">
                    <label class="users-field">
                        <span><?= __('users.master_works.form.title_en') ?></span>
                        <input type="text" name="title_en">
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_works.form.title_ru') ?></span>
                        <input type="text" name="title_ru">
                    </label>
                </div>
                <div class="users-form-grid">
                    <label class="users-field">
                        <span><?= __('users.master_works.form.description_en') ?></span>
                        <textarea name="description_en" rows="4"></textarea>
                    </label>
                    <label class="users-field">
                        <span><?= __('users.master_works.form.description_ru') ?></span>
                        <textarea name="description_ru" rows="4"></textarea>
                    </label>
                </div>
                <label class="users-field">
                    <span><?= __('users.master_works.form.tags') ?></span>
                    <input type="text" name="tags" id="master-works-tags" list="master-works-tags-list" placeholder="#blackwork #tiger #hand">
                    <small class="users-muted"><?= __('users.master_works.form.tags_hint') ?></small>
                </label>
                <?php if ($suggestedTags !== []): ?>
                    <datalist id="master-works-tags-list">
                        <?php foreach ($suggestedTags as $tag): ?>
                            <option value="#<?= htmlspecialchars(ltrim((string)($tag['name'] ?? $tag['slug'] ?? ''), '#')) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div class="users-tag-cloud">
                        <?php foreach ($suggestedTags as $tag): ?>
                            <?php $tagName = ltrim((string)($tag['name'] ?? $tag['slug'] ?? ''), '#'); ?>
                            <?php if ($tagName === '') { continue; } ?>
                            <button type="button" class="users-tag-chip" data-tag-pick="#<?= htmlspecialchars($tagName) ?>">#<?= htmlspecialchars($tagName) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <label class="users-field">
                    <span><?= __('users.master_works.form.categories') ?></span>
                    <select name="category_ids[]" multiple size="<?= max(4, min(8, count($categories ?? []))) ?>">
                        <?php foreach (($categories ?? []) as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars((string)($cat['name_ru'] ?: $cat['name_en'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="users-actions">
                    <button type="submit" class="users-button"><?= __('users.master_works.form.submit') ?></button>
                </div>
            </form>
        </section>

        <aside class="users-card">
            <p class="users-eyebrow"><?= __('users.master_works.list.eyebrow') ?></p>
            <h2><?= __('users.master_works.list.title') ?></h2>
            <?php if (!empty($submissions)): ?>
                <ul class="users-list users-list--comments">
                    <?php foreach ($submissions as $item): ?>
                        <li>
                            <strong><?= htmlspecialchars((string)($item['title_ru'] ?: ($item['title_en'] ?: ('#' . $item['id'])))) ?></strong>
                            <span class="users-pill"><?= htmlspecialchars((string)($item['status'] ?? 'approved')) ?></span>
                            <span class="users-muted"><?= htmlspecialchars((string)($item['created_at'] ?? '')) ?></span>
                            <?php if (!empty($item['status_note'])): ?><p><?= htmlspecialchars((string)$item['status_note']) ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="users-muted"><?= __('users.master_works.list.empty') ?></p>
            <?php endif; ?>
        </aside>
    </div>
</section>
<script>
(function () {
    const input = document.getElementById('master-works-tags');
    if (!input) {
        return;
    }

    const normalize = (value) => {
        const tokens = value.match(/#[\p{L}\p{N}_-]+|[^\s,;#]+/gu) || [];
        const unique = [];
        const seen = new Set();

        for (const token of tokens) {
            const cleaned = token.replace(/^#+/, '').trim();
            if (!cleaned) {
                continue;
            }
            const key = cleaned.toLowerCase();
            if (seen.has(key)) {
                continue;
            }
            seen.add(key);
            unique.push('#' + cleaned);
            if (unique.length >= 7) {
                break;
            }
        }

        return unique.join(' ');
    };

    input.addEventListener('blur', function () {
        this.value = normalize(this.value);
    });

    document.querySelectorAll('[data-tag-pick]').forEach((button) => {
        button.addEventListener('click', function () {
            input.value = normalize((input.value + ' ' + this.getAttribute('data-tag-pick')).trim());
            input.focus();
        });
    });
})();
</script>
