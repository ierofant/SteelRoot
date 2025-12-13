<?php ob_start(); ?>
<div class="toolbar">
    <div class="stacked">
        <h3><?= __('modules.title') ?></h3>
        <p class="muted"><?= __('modules.subtitle') ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/upload') ?>"><?= __('modules.action.upload') ?></a>
    </div>
</div>

<div class="card">
    <table class="table">
        <thead>
        <tr>
            <th><?= __('modules.table.name') ?></th>
            <th><?= __('modules.table.slug') ?></th>
            <th><?= __('modules.table.version') ?></th>
            <th><?= __('modules.table.status') ?></th>
            <th><?= __('modules.table.migrations') ?></th>
            <th><?= __('modules.table.description') ?></th>
            <th><?= __('modules.table.actions') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($modules as $slug => $module): ?>
            <tr>
                <td>
                    <div class="stacked">
                        <strong><?= htmlspecialchars($module['name'] ?? $slug) ?></strong>
                        <?php if (!empty($module['errors'])): ?>
                            <small class="danger"><?= htmlspecialchars(implode('; ', $module['errors'])) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($module['definition']['events'])): ?>
                            <small class="muted">Listeners: <?= htmlspecialchars(implode(', ', array_keys($module['definition']['events']))) ?></small>
                        <?php endif; ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($slug) ?></td>
                <td><?= htmlspecialchars($module['version'] ?? '-') ?></td>
                <td>
                    <?php if (!empty($module['enabled'])): ?>
                        <span class="pill success"><?= __('modules.status.enabled') ?></span>
                    <?php else: ?>
                        <span class="pill muted"><?= __('modules.status.disabled') ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach (($statuses[$slug] ?? ['-']) as $st): ?>
                        <div class="muted"><?= htmlspecialchars($st) ?></div>
                    <?php endforeach; ?>
                </td>
                <td><?= htmlspecialchars($module['description'] ?? '') ?></td>
                <td class="actions">
                    <?php if (strtolower($slug) === 'popups' && !empty($module['enabled'])): ?>
                        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/popups') ?>"><?= __('modules.actions.settings') ?></a>
                    <?php endif; ?>
                    <?php if (strtolower($slug) === 'articles' && !empty($module['enabled'])): ?>
                        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/articles/settings') ?>"><?= __('modules.actions.settings') ?></a>
                    <?php endif; ?>
                    <?php if (strtolower($slug) === 'gallery' && !empty($module['enabled'])): ?>
                        <a class="btn ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/gallery/settings') ?>"><?= __('modules.actions.settings') ?></a>
                    <?php endif; ?>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/' . (!empty($module['enabled']) ? 'disable' : 'enable') . '/' . rawurlencode($slug)) ?>" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn ghost"><?= !empty($module['enabled']) ? __('modules.actions.disable') : __('modules.actions.enable') ?></button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/migrate/' . rawurlencode($slug)) ?>" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="btn ghost"><?= __('modules.actions.migrate') ?></button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/rollback/' . rawurlencode($slug)) ?>" style="display:inline-block">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <input type="hidden" name="steps" value="1">
                        <button type="submit" class="btn ghost danger"><?= __('modules.actions.rollback') ?></button>
                    </form>
                    <a class="btn danger ghost" href="<?= htmlspecialchars((defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin') . '/modules/delete/' . rawurlencode($slug)) ?>"><?= __('modules.actions.delete') ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($modules)): ?>
        <p class="muted"><?= __('modules.empty') ?></p>
    <?php endif; ?>
</div>
<?php
$title = $title ?? __('modules.page_title');
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>
