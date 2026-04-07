<?php
$ap = $adminPrefix ?? (defined('ADMIN_PREFIX') ? ADMIN_PREFIX : '/admin');
$stopWords = is_array($settings['stop_words'] ?? null) ? implode("\n", $settings['stop_words']) : '';
ob_start();
?>
<div class="card stack comments-settings">
    <div class="card-header comments-settings__header">
        <div>
            <p class="eyebrow"><?= htmlspecialchars(__('comments.settings.kicker')) ?></p>
            <h3><?= htmlspecialchars(__('comments.settings.title')) ?></h3>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars($ap) ?>/comments"><?= htmlspecialchars(__('comments.settings.back')) ?></a>
    </div>

    <form method="post" action="<?= htmlspecialchars($ap) ?>/comments/settings" class="stack comments-settings-form">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">

        <section class="comments-settings-section">
            <div class="comments-settings-section__header">
                <p class="eyebrow">Core</p>
                <h4><?= htmlspecialchars(__('comments.settings.title')) ?></h4>
                <p>Базовые режимы публикации и отображения комментариев.</p>
            </div>
            <div class="comments-settings-checks comments-settings-checks--two">
                <label class="comments-settings-check">
                    <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.enabled')) ?></span>
                </label>
                <label class="comments-settings-check">
                    <input type="checkbox" name="premoderation" value="1" <?= !empty($settings['premoderation']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.premoderation')) ?></span>
                </label>
                <label class="comments-settings-check">
                    <input type="checkbox" name="autopublish_admin" value="1" <?= !empty($settings['autopublish_admin']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.autopublish_admin')) ?></span>
                </label>
                <label class="comments-settings-check">
                    <input type="checkbox" name="autopublish_authenticated" value="1" <?= !empty($settings['autopublish_authenticated']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.autopublish_user')) ?></span>
                </label>
            </div>
            <div class="grid three comments-settings-grid">
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.max_depth')) ?></span>
                    <input type="number" name="max_depth" min="1" max="6" value="<?= (int)($settings['max_depth'] ?? 3) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.per_page')) ?></span>
                    <input type="number" name="per_page" min="5" max="100" value="<?= (int)($settings['per_page'] ?? 20) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.default_sort')) ?></span>
                    <select name="default_sort">
                        <option value="oldest" <?= ($settings['default_sort'] ?? 'oldest') === 'oldest' ? 'selected' : '' ?>>oldest</option>
                        <option value="newest" <?= ($settings['default_sort'] ?? '') === 'newest' ? 'selected' : '' ?>>newest</option>
                    </select>
                </label>
            </div>
        </section>

        <section class="comments-settings-section">
            <div class="comments-settings-section__header">
                <p class="eyebrow">Entities</p>
                <h4><?= htmlspecialchars(__('comments.settings.entities')) ?></h4>
                <p>Какие типы контента вообще могут принимать комментарии.</p>
            </div>
            <div class="comments-settings-checks comments-settings-checks--three">
                <?php foreach ($entityOptions as $type => $label): ?>
                    <label class="comments-settings-check">
                        <input type="checkbox" name="enabled_entity_types[]" value="<?= htmlspecialchars($type) ?>" <?= in_array($type, (array)($settings['enabled_entity_types'] ?? []), true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="comments-settings-section">
            <div class="comments-settings-section__header">
                <p class="eyebrow">Spam</p>
                <h4>Антиспам и фильтры</h4>
                <p>Автоматические ограничения для ссылок, стоп-слов и подозрительных отправок.</p>
            </div>
            <div class="grid two comments-settings-grid">
                <label class="comments-settings-check comments-settings-check--inline">
                    <input type="checkbox" name="disallow_links" value="1" <?= !empty($settings['disallow_links']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.disallow_links')) ?></span>
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.spam_action')) ?></span>
                    <select name="spam_action">
                        <option value="moderate" <?= ($settings['spam_action'] ?? 'moderate') === 'moderate' ? 'selected' : '' ?>>moderate</option>
                        <option value="reject" <?= ($settings['spam_action'] ?? '') === 'reject' ? 'selected' : '' ?>>reject</option>
                    </select>
                </label>
            </div>
            <div class="grid three comments-settings-grid">
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.max_url_patterns')) ?></span>
                    <input type="number" name="max_url_patterns" min="0" max="20" value="<?= (int)($settings['max_url_patterns'] ?? 0) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.min_length')) ?></span>
                    <input type="number" name="min_length" min="1" max="500" value="<?= (int)($settings['min_length'] ?? 8) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.max_length')) ?></span>
                    <input type="number" name="max_length" min="20" max="5000" value="<?= (int)($settings['max_length'] ?? 2000) ?>">
                </label>
            </div>
            <label class="field comments-settings-textarea">
                <span><?= htmlspecialchars(__('comments.settings.stop_words')) ?></span>
                <textarea name="stop_words" rows="6"><?= htmlspecialchars($stopWords) ?></textarea>
            </label>
        </section>

        <section class="comments-settings-section">
            <div class="comments-settings-section__header">
                <p class="eyebrow">Limits</p>
                <h4>Ограничения и ловушки</h4>
                <p>Защита от flood-комментариев и быстрых спам-отправок.</p>
            </div>
            <div class="grid four comments-settings-grid">
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.min_submit_seconds')) ?></span>
                    <input type="number" name="min_submit_seconds" min="0" max="120" value="<?= (int)($settings['min_submit_seconds'] ?? 4) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.delay_between_comments')) ?></span>
                    <input type="number" name="delay_between_comments" min="0" max="3600" value="<?= (int)($settings['delay_between_comments'] ?? 20) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.comments_per_minute')) ?></span>
                    <input type="number" name="comments_per_minute" min="1" max="60" value="<?= (int)($settings['comments_per_minute'] ?? 2) ?>">
                </label>
                <label class="field">
                    <span><?= htmlspecialchars(__('comments.settings.comments_per_hour')) ?></span>
                    <input type="number" name="comments_per_hour" min="1" max="500" value="<?= (int)($settings['comments_per_hour'] ?? 12) ?>">
                </label>
            </div>
            <div class="comments-settings-checks comments-settings-checks--two">
                <label class="comments-settings-check">
                    <input type="checkbox" name="honeypot_enabled" value="1" <?= !empty($settings['honeypot_enabled']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.honeypot')) ?></span>
                </label>
                <label class="comments-settings-check">
                    <input type="checkbox" name="time_filter_enabled" value="1" <?= !empty($settings['time_filter_enabled']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars(__('comments.settings.time_filter')) ?></span>
                </label>
            </div>
        </section>

        <div class="form-actions comments-settings-actions">
            <button type="submit" class="btn primary"><?= htmlspecialchars(__('comments.settings.save')) ?></button>
        </div>
    </form>
</div>
<?php
$headHtml = \Core\Asset::styleTag('/modules/Comments/assets/css/comments.css');
$bodyHtml = \Core\Asset::scriptTag('/modules/Comments/assets/js/comments.js');
$content = ob_get_clean();
$pageTitle = $title ?? __('comments.settings.title');
$flash = $flash ?? null;
include APP_ROOT . '/modules/Admin/views/layout.php';
