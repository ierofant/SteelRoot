<?php ob_start(); ?>
<?php
$ap = defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin';
$report = $report ?? [];
$poll = (array)($report['settings'] ?? []);
$questions = (array)($report['questions'] ?? []);
$summary = (array)($report['summary'] ?? []);
$other = (array)($report['other'] ?? []);
$comments = (array)($report['comments'] ?? []);
$responses = (array)($report['responses'] ?? []);
$audience = (string)($report['audience'] ?? 'all');
$audienceOptions = (array)($report['audience_options'] ?? []);
?>
<div class="card users-admin-directory users-admin-poll">
    <div class="card-header users-admin-directory__header">
        <div>
            <p class="eyebrow"><?= __('users.community_poll.admin.eyebrow') ?></p>
            <h3><?= __('users.community_poll.admin.title') ?></h3>
        </div>
        <div class="form-actions users-admin-actions">
            <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/users"><?= __('users.settings.back') ?></a>
        </div>
    </div>

    <div class="users-admin-poll__layout">
        <section class="users-admin-panel">
            <p class="eyebrow"><?= __('users.community_poll.admin.settings') ?></p>
            <form method="POST" action="<?= htmlspecialchars($ap) ?>/users/community-poll" class="users-admin-poll__settings">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($csrf ?? '')) ?>">

                <label class="field">
                    <span><?= __('users.community_poll.admin.status') ?></span>
                    <select name="users_community_poll_status">
                        <?php foreach (['active', 'closed', 'hidden'] as $status): ?>
                            <option value="<?= $status ?>"<?= (($poll['status'] ?? 'active') === $status) ? ' selected' : '' ?>>
                                <?= __('users.community_poll.status.' . $status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= __('users.community_poll.admin.key') ?></span>
                    <input type="text" name="users_community_poll_key" value="<?= htmlspecialchars((string)($poll['key'] ?? 'community_features_v1')) ?>" maxlength="64">
                </label>

                <label class="field users-admin-choice">
                    <input type="checkbox" name="users_community_poll_allow_comment" value="1" <?= !empty($poll['allow_comment']) ? 'checked' : '' ?>>
                    <span><?= __('users.community_poll.admin.allow_comment') ?></span>
                </label>

                <label class="field">
                    <span><?= __('users.community_poll.admin.title_field') ?></span>
                    <input type="text" name="users_community_poll_title" value="<?= htmlspecialchars((string)($poll['title'] ?? '')) ?>" maxlength="180" placeholder="<?= __('users.community_poll.title') ?>">
                </label>

                <label class="field">
                    <span><?= __('users.community_poll.admin.description_field') ?></span>
                    <textarea name="users_community_poll_description" rows="3" maxlength="500" placeholder="<?= __('users.community_poll.description') ?>"><?= htmlspecialchars((string)($poll['description'] ?? '')) ?></textarea>
                </label>

                <label class="field">
                    <span><?= __('users.community_poll.admin.highlight_title') ?></span>
                    <input type="text" name="users_community_poll_highlight_title" value="<?= htmlspecialchars((string)($poll['highlight_title'] ?? '')) ?>" maxlength="120" placeholder="<?= __('users.community_poll.highlight_title') ?>">
                </label>

                <label class="field">
                    <span><?= __('users.community_poll.admin.highlight_description') ?></span>
                    <textarea name="users_community_poll_highlight_description" rows="3" maxlength="600" placeholder="<?= __('users.community_poll.highlight_description') ?>"><?= htmlspecialchars((string)($poll['highlight_description'] ?? '')) ?></textarea>
                </label>

                <div class="form-actions">
                    <button class="btn primary" type="submit"><?= __('users.settings.save') ?></button>
                </div>
            </form>
        </section>

        <section class="users-admin-panel">
            <p class="eyebrow"><?= __('users.community_poll.admin.analytics') ?></p>

            <?php if (empty($report['table_ready'])): ?>
                <p class="muted"><?= __('users.community_poll.admin.table_not_ready') ?></p>
            <?php else: ?>
                <form method="GET" action="<?= htmlspecialchars($ap) ?>/users/community-poll" class="filters users-admin-poll__filters">
                    <label class="field">
                        <span><?= __('users.community_poll.admin.filter_audience') ?></span>
                        <select name="audience">
                            <?php foreach ($audienceOptions as $value => $labelKey): ?>
                                <option value="<?= htmlspecialchars((string)$value) ?>"<?= $audience === $value ? ' selected' : '' ?>><?= __((string)$labelKey) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="form-actions">
                        <button class="btn ghost" type="submit"><?= __('users.community_poll.admin.apply_filter') ?></button>
                    </div>
                </form>

                <div class="users-admin-poll__stats">
                    <div class="users-admin-chip users-admin-chip--id"><?= __('users.community_poll.admin.responses_total') ?>: <?= (int)count($responses) ?></div>
                    <div class="users-admin-chip"><?= __('users.community_poll.admin.current_status') ?>: <?= __('users.community_poll.status.' . ($poll['status'] ?? 'active')) ?></div>
                </div>

                <?php foreach ($questions as $questionKey => $question): ?>
                    <section class="users-admin-poll__group">
                        <div class="users-card__header">
                            <h4><?= __((string)($question['label_key'] ?? '')) ?></h4>
                        </div>
                        <div class="users-admin-poll__summary">
                            <?php foreach ((array)($summary[$questionKey] ?? []) as $item): ?>
                                <div class="users-admin-poll__summary-item">
                                    <strong><?= (int)($item['count'] ?? 0) ?></strong>
                                    <span><?= __((string)($item['label_key'] ?? '')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($other[$questionKey])): ?>
                            <div class="users-admin-poll__other">
                                <p class="eyebrow"><?= __('users.community_poll.admin.other_answers') ?></p>
                                <ul class="users-list">
                                    <?php foreach ($other[$questionKey] as $row): ?>
                                        <li>
                                            <strong><?= htmlspecialchars((string)$row['display_identity']) ?></strong>
                                            <span><?= __('users.community_poll.audience.' . (string)$row['audience']) ?></span>
                                            <p><?= htmlspecialchars((string)$row['text']) ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>

                <section class="users-admin-poll__group">
                    <div class="users-card__header">
                        <h4><?= __('users.community_poll.admin.comments') ?></h4>
                    </div>
                    <?php if ($comments): ?>
                        <ul class="users-list">
                            <?php foreach ($comments as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars((string)$row['display_identity']) ?></strong>
                                    <span><?= __('users.community_poll.audience.' . (string)$row['audience']) ?></span>
                                    <p><?= htmlspecialchars((string)$row['text']) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted"><?= __('users.community_poll.admin.no_comments') ?></p>
                    <?php endif; ?>
                </section>

                <section class="users-admin-poll__group">
                    <div class="users-card__header">
                        <h4><?= __('users.community_poll.admin.responses_table') ?></h4>
                    </div>
                    <div class="table-wrap users-admin-directory__table-wrap">
                        <table class="table data users-admin-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th><?= __('users.community_poll.admin.user') ?></th>
                                <th><?= __('users.community_poll.admin.audience_column') ?></th>
                                <th><?= __('users.community_poll.admin.primary_column') ?></th>
                                <th><?= __('users.community_poll.admin.access_column') ?></th>
                                <th><?= __('users.community_poll.admin.goal_column') ?></th>
                                <th><?= __('users.community_poll.admin.comment_column') ?></th>
                                <th><?= __('users.community_poll.admin.created_at') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($responses as $row): ?>
                                <tr>
                                    <td><span class="users-admin-chip users-admin-chip--id">#<?= (int)$row['id'] ?></span></td>
                                    <td>
                                        <div class="users-admin-usermeta">
                                            <strong><?= htmlspecialchars((string)($row['display_identity'] ?? '')) ?></strong>
                                            <span>@<?= htmlspecialchars((string)($row['username'] ?? '')) ?></span>
                                            <span><?= htmlspecialchars((string)($row['email'] ?? '')) ?></span>
                                        </div>
                                    </td>
                                    <td><span class="users-admin-chip"><?= __('users.community_poll.audience.' . (string)($row['audience'] ?? 'registered')) ?></span></td>
                                    <td>
                                        <?= __('users.community_poll.option.primary.' . (string)($row['answer_primary'] ?? 'other')) ?>
                                        <?php if (!empty($row['answer_primary_other'])): ?><div class="users-admin-poll__text"><?= htmlspecialchars((string)$row['answer_primary_other']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <?= __('users.community_poll.option.access.' . (string)($row['answer_access'] ?? 'other')) ?>
                                        <?php if (!empty($row['answer_access_other'])): ?><div class="users-admin-poll__text"><?= htmlspecialchars((string)$row['answer_access_other']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <?= __('users.community_poll.option.goal.' . (string)($row['answer_goal'] ?? 'other')) ?>
                                        <?php if (!empty($row['answer_goal_other'])): ?><div class="users-admin-poll__text"><?= htmlspecialchars((string)$row['answer_goal_other']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['comment'])): ?>
                                            <div class="users-admin-poll__text"><?= htmlspecialchars((string)$row['comment']) ?></div>
                                        <?php else: ?>
                                            <span class="users-admin-chip users-admin-chip--empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="users-admin-last-login"><?= htmlspecialchars((string)($row['created_at'] ?? '')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$responses): ?>
                                <tr><td colspan="8" class="muted"><?= __('users.community_poll.admin.no_responses') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php
$title = __('users.community_poll.admin.title');
$content = ob_get_clean();
$headHtml = \Core\Asset::styleTag('/modules/Users/assets/css/users-admin.css');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
?>
