<?php
$communityPoll = $communityPoll ?? [];
$pollSettings = (array)($communityPoll['settings'] ?? []);
$pollQuestions = (array)($communityPoll['questions'] ?? []);
$pollResponse = $communityPoll['response'] ?? null;
$pollActive = (($pollSettings['status'] ?? 'hidden') === 'active') && !empty($communityPoll['table_ready']);
$pollVisible = $pollActive && (!empty($communityPoll['is_visible']) || $pollResponse !== null);
if (!$pollVisible) {
    return;
}
?>
<section class="users-card users-community-poll">
    <div class="users-card__header">
        <div>
            <p class="users-eyebrow"><?= __('users.community_poll.eyebrow') ?></p>
            <h3><?= htmlspecialchars((string)($pollSettings['title'] ?? '')) ?: __('users.community_poll.title') ?></h3>
        </div>
    </div>

    <p class="users-community-poll__lead">
        <?= htmlspecialchars((string)($pollSettings['description'] ?? '')) ?: __('users.community_poll.description') ?>
    </p>

    <aside class="users-community-poll__highlight">
        <strong><?= htmlspecialchars((string)($pollSettings['highlight_title'] ?? '')) ?: __('users.community_poll.highlight_title') ?></strong>
        <p><?= htmlspecialchars((string)($pollSettings['highlight_description'] ?? '')) ?: __('users.community_poll.highlight_description') ?></p>
    </aside>

    <?php if ($pollResponse): ?>
        <div class="users-community-poll__submitted">
            <strong><?= __('users.community_poll.thanks_title') ?></strong>
            <p><?= __('users.community_poll.thanks_text') ?></p>
            <ul class="users-list users-community-poll__answers">
                <?php foreach ($pollQuestions as $questionKey => $question): ?>
                    <?php
                    $value = (string)($pollResponse['answer_' . $questionKey] ?? '');
                    $other = trim((string)($pollResponse['answer_' . $questionKey . '_other'] ?? ''));
                    $labelKey = '';
                    foreach ((array)($question['options'] ?? []) as $option) {
                        if (($option['value'] ?? '') === $value) {
                            $labelKey = (string)($option['label_key'] ?? '');
                            break;
                        }
                    }
                    ?>
                    <li>
                        <strong><?= __((string)($question['label_key'] ?? '')) ?></strong>
                        <span><?= $labelKey !== '' ? __($labelKey) : htmlspecialchars($value) ?></span>
                        <?php if ($other !== ''): ?><small><?= htmlspecialchars($other) ?></small><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!empty($pollResponse['comment'])): ?>
                    <li>
                        <strong><?= __('users.community_poll.comment_label') ?></strong>
                        <span><?= htmlspecialchars((string)$pollResponse['comment']) ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    <?php else: ?>
        <form method="POST" action="/profile/community-poll" class="users-community-poll__form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($communityPollToken ?? '')) ?>">
            <?php foreach ($pollQuestions as $questionKey => $question): ?>
                <fieldset class="users-community-poll__question">
                    <legend><?= __((string)($question['label_key'] ?? '')) ?></legend>
                    <div class="users-community-poll__options">
                        <?php foreach ((array)($question['options'] ?? []) as $option): ?>
                            <label class="users-community-poll__option">
                                <input type="radio" name="answer_<?= htmlspecialchars($questionKey) ?>" value="<?= htmlspecialchars((string)$option['value']) ?>" required>
                                <span><?= __((string)($option['label_key'] ?? '')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="users-field users-community-poll__other">
                        <span><?= __('users.community_poll.other_label') ?></span>
                        <input
                            type="text"
                            name="answer_<?= htmlspecialchars($questionKey) ?>_other"
                            maxlength="500"
                            placeholder="<?= __((string)($question['other_placeholder_key'] ?? 'users.community_poll.other_placeholder.default')) ?>"
                        >
                    </label>
                </fieldset>
            <?php endforeach; ?>

            <?php if (!empty($pollSettings['allow_comment'])): ?>
                <label class="users-field">
                    <span><?= __('users.community_poll.comment_prompt') ?></span>
                    <textarea name="comment" rows="4" maxlength="1500" placeholder="<?= __('users.community_poll.comment_placeholder') ?>"></textarea>
                </label>
            <?php endif; ?>

            <div class="users-actions">
                <button class="users-button" type="submit"><?= __('users.community_poll.submit') ?></button>
            </div>
        </form>
    <?php endif; ?>
</section>
