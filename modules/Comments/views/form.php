<?php
$old = is_array($state['old'] ?? null) ? $state['old'] : [];
$isReply = (bool)($isReply ?? false);
$parentId = (int)($parentId ?? 0);
?>
<form method="post" action="/comments/store" class="comments-form">
    <input type="hidden" name="_token" value="<?= htmlspecialchars((string)$csrf) ?>">
    <input type="hidden" name="entity_type" value="<?= htmlspecialchars((string)$entityType) ?>">
    <input type="hidden" name="entity_id" value="<?= (int)$entityId ?>">
    <input type="hidden" name="parent_id" value="<?= $parentId ?>">
    <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)$currentUrl) ?>">
    <input type="hidden" name="form_started" value="<?= time() ?>">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="comments-form__honeypot" aria-hidden="true">

    <?php if (!$currentUser): ?>
        <div class="comments-form__grid">
            <label class="comments-form__field">
                <span><?= htmlspecialchars(__('comments.form.name')) ?></span>
                <input type="text" name="guest_name" value="<?= htmlspecialchars((string)($old['guest_name'] ?? '')) ?>" required>
            </label>
            <label class="comments-form__field">
                <span><?= htmlspecialchars(__('comments.form.email')) ?></span>
                <input type="email" name="guest_email" value="<?= htmlspecialchars((string)($old['guest_email'] ?? '')) ?>">
            </label>
        </div>
    <?php else: ?>
        <p class="comments-form__signed">
            <?= htmlspecialchars(__('comments.form.signed_as')) ?> <strong><?= htmlspecialchars((string)($currentUser['username'] ?? $currentUser['name'] ?? '')) ?></strong>
        </p>
    <?php endif; ?>

    <label class="comments-form__field">
        <span><?= htmlspecialchars($isReply ? __('comments.form.reply') : __('comments.form.body')) ?></span>
        <textarea name="body" rows="<?= $isReply ? 4 : 5 ?>" required><?= htmlspecialchars((string)($old['body'] ?? '')) ?></textarea>
    </label>

    <div class="comments-form__emoji" aria-label="<?= htmlspecialchars(__('comments.form.emoji')) ?>">
        <?php foreach (['🔥','🖤','😍','👏','🤘','✨','🙏','💯'] as $emoji): ?>
            <button type="button" class="comments-form__emoji-btn" data-comment-emoji="<?= htmlspecialchars($emoji) ?>" aria-label="<?= htmlspecialchars(__('comments.form.emoji_add')) . ' ' . $emoji ?>"><?= htmlspecialchars($emoji) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="comments-form__actions">
        <button type="submit" class="btn"><?= htmlspecialchars($isReply ? __('comments.action.reply_submit') : __('comments.action.submit')) ?></button>
    </div>
</form>
