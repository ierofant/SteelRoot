<?php
    $locale = $locale ?? ($currentLocale ?? 'en');
    $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
    $contentKey = $locale === 'ru' ? 'content_ru' : 'content_en';
    $title = $page[$titleKey] ?? '';
    $content = $page[$contentKey] ?? '';
    $contentSafe = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
?>
<div class="page-shell">
<article class="page-content">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="rich-text">
        <?= $contentSafe ?>
    </div>
    <?php if (!empty($tags)): ?>
        <div class="page-tags">
            <?php foreach ($tags as $tag): ?>
                <a class="pill" href="/tags/<?= htmlspecialchars($tag['slug'] ?? '') ?>">#<?= htmlspecialchars($tag['name'] ?? '') ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $commentsHtml ?? '' ?>
</article>
</div>
