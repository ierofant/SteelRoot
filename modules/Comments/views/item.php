<?php
$body = nl2br(htmlspecialchars((string)($comment['body'] ?? '')));
$children = is_array($comment['children'] ?? null) ? $comment['children'] : [];
$canReply = ($level + 1) < $maxDepth;
$isReply = true;
$parentId = (int)$comment['id'];
$replyToId = (int)$comment['id'];
?>
<article class="comment-item" id="comment-<?= (int)$comment['id'] ?>" data-depth="<?= $level ?>">
    <header class="comment-item__head">
        <div class="comment-item__author">
            <strong><?= htmlspecialchars((string)($comment['author_display'] ?? __('comments.author.fallback'))) ?></strong>
            <?php if (!empty($comment['author_url'])): ?>
                <a class="comment-item__profile" href="<?= htmlspecialchars((string)$comment['author_url']) ?>"><?= htmlspecialchars(__('comments.author.profile')) ?></a>
            <?php endif; ?>
        </div>
        <time class="comment-item__date" datetime="<?= htmlspecialchars((string)($comment['created_at'] ?? '')) ?>">
            <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)($comment['created_at'] ?? 'now')))) ?>
        </time>
    </header>
    <div class="comment-item__body"><?= $body ?></div>

    <?php if ($canReply && !empty($canPost)): ?>
        <details class="comment-item__reply">
            <summary><?= htmlspecialchars(__('comments.action.reply')) ?></summary>
            <?php include APP_ROOT . '/modules/Comments/views/form.php'; ?>
        </details>
    <?php endif; ?>

    <?php if ($children !== []): ?>
        <div class="comment-item__children">
            <?php foreach ($children as $child): ?>
                <?php $renderComment($child, $level + 1); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
