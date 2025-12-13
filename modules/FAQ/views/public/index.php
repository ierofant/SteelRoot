<?php ob_start(); ?>
<section class="faq-hero">
    <div class="faq-hero__heading">
        <p class="eyebrow">Knowledge Base</p>
        <h1>FAQ</h1>
        <p class="muted">Частые вопросы и ответы.</p>
    </div>
</section>
<section class="faq-list">
    <?php if (!empty($items)): ?>
        <?php foreach ($items as $item): ?>
            <article class="faq-item">
                <header class="faq-item__header">
                    <h3><?= htmlspecialchars($item['question'] ?? '') ?></h3>
                    <span class="faq-item__status"><?= htmlspecialchars($item['status'] ?? '') ?></span>
                </header>
                <div class="faq-item__body">
                    <p><?= nl2br(htmlspecialchars($item['answer'] ?? '')) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="muted">Нет опубликованных вопросов.</p>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
include APP_ROOT . '/app/views/layout.php';
?>
