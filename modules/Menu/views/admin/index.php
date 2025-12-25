<div class="card stack glass">
    <div class="card-header">
        <div>
            <p class="eyebrow"><?= __('menu.admin.title') ?></p>
            <h3><?= __('menu.admin.subtitle') ?></h3>
            <p class="muted"><?= __('menu.admin.help') ?></p>
        </div>
        <div class="stack">
            <a class="btn ghost" href="<?= htmlspecialchars($adminPrefix) ?>/menu/create"><?= __('menu.admin.add') ?></a>
        </div>
    </div>
    <?php if (!empty($flash)): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($adminPrefix) ?>/menu/reorder">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= __('menu.field.label_ru') ?></th>
                        <th><?= __('menu.field.label_en') ?></th>
                        <th><?= __('menu.field.parent') ?></th>
                        <th>URL</th>
                        <th><?= __('menu.field.enabled') ?></th>
                        <th><?= __('menu.field.admin_only') ?></th>
                        <th><?= __('menu.field.position') ?></th>
                        <th><?= __('menu.admin.actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="9" class="muted"><?= __('menu.admin.empty') ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $row): ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td><?= htmlspecialchars($row['label_ru']) ?></td>
                            <td><?= htmlspecialchars($row['label_en']) ?></td>
                            <td>
                                <?php if (!empty($row['parent_id'])): ?>
                                    <?= htmlspecialchars($parentMap[(int)$row['parent_id']] ?? '—') ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($row['url']) ?></code></td>
                            <td><?= !empty($row['enabled']) ? '✔' : '—' ?></td>
                            <td><?= !empty($row['admin_only']) ? '✔' : '—' ?></td>
                            <td>
                                <input type="number" name="positions[<?= (int)$row['id'] ?>]" value="<?= (int)$row['position'] ?>">
                            </td>
                            <td class="actions">
                                <a class="btn ghost small" href="<?= htmlspecialchars($adminPrefix) ?>/menu/edit/<?= (int)$row['id'] ?>"><?= __('menu.admin.edit') ?></a>
                                <button type="submit"
                                        class="btn ghost small"
                                        formmethod="post"
                                        formaction="<?= htmlspecialchars($adminPrefix) ?>/menu/toggle/<?= (int)$row['id'] ?>">
                                    <?= __('menu.admin.toggle') ?>
                                </button>
                                <button type="submit"
                                        class="btn danger small"
                                        formmethod="post"
                                        formaction="<?= htmlspecialchars($adminPrefix) ?>/menu/delete/<?= (int)$row['id'] ?>"
                                        onclick="return confirm('Delete?');">
                                    ×
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn primary"><?= __('menu.admin.save_order') ?></button>
        </div>
    </form>
</div>
