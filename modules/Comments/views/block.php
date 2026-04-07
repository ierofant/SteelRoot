<?php
$commentCount = (int)($commentCount ?? 0);
$page = (int)($page ?? 1);
$totalPages = (int)($totalPages ?? 1);
$currentUrl = $currentUrl ?? '/';
$state = is_array($state ?? null) ? $state : [];
$comments = is_array($comments ?? null) ? $comments : [];
$settings = is_array($settings ?? null) ? $settings : [];
$formView = APP_ROOT . '/modules/Comments/views/form.php';
$itemView = APP_ROOT . '/modules/Comments/views/item.php';
$maxDepth = (int)($maxDepth ?? 3);
$canPost = !empty($canPost);
$postingMessage = (string)($postingMessage ?? '');
$showLoginLink = !empty($showLoginLink);
$renderComment = static function (array $comment, int $level) use (&$renderComment, $itemView, $entityType, $entityId, $csrf, $currentUser, $currentUrl, $maxDepth, $canPost): void {
    include $itemView;
};
?>
<section class="comments-block" id="comments">
    <div class="comments-block__header">
        <div>
            <p class="comments-block__eyebrow"><?= htmlspecialchars(__('comments.block.kicker')) ?></p>
            <h2 class="comments-block__title"><?= htmlspecialchars($title ?? __('comments.block.title')) ?></h2>
        </div>
        <span class="comments-block__count"><?= $commentCount ?></span>
    </div>

    <?php if (!empty($state['error'])): ?>
        <div class="comments-alert comments-alert--error"><?= htmlspecialchars((string)$state['error']) ?></div>
    <?php elseif (!empty($state['success'])): ?>
        <div class="comments-alert comments-alert--success"><?= htmlspecialchars((string)$state['success']) ?></div>
    <?php endif; ?>

    <div class="comments-block__form">
        <?php if ($canPost): ?>
            <?php
            $isReply = false;
            $parentId = 0;
            $replyToId = null;
            include $formView;
            ?>
        <?php else: ?>
            <div class="comments-alert comments-alert--info">
                <?= htmlspecialchars($postingMessage !== '' ? $postingMessage : __('comments.form.login_prompt')) ?>
                <?php if ($showLoginLink): ?>
                    <a class="comments-alert__link" href="/login"><?= htmlspecialchars(__('comments.form.login_link')) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($comments === []): ?>
        <p class="comments-empty"><?= htmlspecialchars(__('comments.block.empty')) ?></p>
    <?php else: ?>
        <div class="comments-tree">
            <?php foreach ($comments as $comment): ?>
                <?php $renderComment($comment, 0); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="comments-pagination" aria-label="<?= htmlspecialchars(__('comments.block.pagination')) ?>">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php
                $separator = str_contains($currentUrl, '?') ? '&' : '?';
                $href = $currentUrl . $separator . 'comments_page=' . $i . '#comments';
                ?>
                <a class="comments-pagination__link<?= $i === $page ? ' is-active' : '' ?>" href="<?= htmlspecialchars($href) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
