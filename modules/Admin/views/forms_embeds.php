<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
ob_start();
?>
<div class="card stack">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('forms.embed.title') ?></p>
            <h3><?= __('forms.embed.subtitle') ?></h3>
        </div>
        <div class="form-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms"><?= __('forms.title') ?></a>
            <a class="btn primary" href="<?= htmlspecialchars($ap) ?>/forms/embeds/create"><?= __('forms.embed.action.create') ?></a>
        </div>
    </div>
    <section class="form-section">
        <p class="muted"><?= __('forms.embed.description') ?></p>
        <div class="grid two">
            <div>
                <p class="eyebrow"><?= __('forms.embed.fields.embed') ?></p>
                <code>{{ form:example_slug }}</code>
            </div>
            <div>
                <p class="eyebrow"><?= __('forms.embed.fields.status') ?></p>
                <span class="pill muted"><?= __('forms.embed.enabled') ?> / <?= __('forms.embed.disabled') ?></span>
            </div>
        </div>
    </section>

    <section class="form-section stack">
        <header>
            <p class="eyebrow"><?= __('forms.embed.section.heading') ?></p>
            <h4><?= __('forms.embed.section.subheading') ?></h4>
        </header>
        <div class="forms-grid">
            <?php foreach ($forms as $form): ?>
                <article class="embed-card">
                    <header>
                        <p class="eyebrow">#<?= (int)$form['id'] ?></p>
                        <h3><?= htmlspecialchars($form['name'] ?? __('forms.embed.untitled')) ?></h3>
                        <p class="muted"><?= htmlspecialchars($form['slug'] ?? '') ?></p>
                    </header>
                    <div class="embed-card__details">
                        <div>
                            <span class="pill"><?= __('forms.embed.fields.status') ?></span>
                            <strong><?= !empty($form['enabled']) ? __('forms.embed.enabled') : __('forms.embed.disabled') ?></strong>
                        </div>
                        <div>
                            <span class="pill"><?= __('forms.embed.fields.embed') ?></span>
                            <code>{{ form:<?= htmlspecialchars($form['slug'] ?? '') ?> }}</code>
                        </div>
                    </div>
                    <div class="embed-card__actions">
                        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/forms/embeds/edit/<?= (int)$form['id'] ?>"><?= __('forms.embed.action.edit') ?></a>
                        <form method="post" action="<?= htmlspecialchars($ap) ?>/forms/embeds/delete/<?= (int)$form['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                            <button type="submit" class="btn ghost danger"><?= __('forms.embed.action.delete') ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (empty($forms)): ?>
            <div class="empty-state">
                <h3><?= __('forms.embed.empty') ?></h3>
                <p class="muted"><?= __('forms.embed.empty_hint') ?></p>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php
$title = __('forms.embed.title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
