<?php
    $locale = $locale ?? ($currentLocale ?? 'en');
    $titleKey = $locale === 'ru' ? 'title_ru' : 'title_en';
    $contentKey = $locale === 'ru' ? 'content_ru' : 'content_en';
    $title = $page[$titleKey] ?? '';
    $content = $page[$contentKey] ?? '';
    $contentSafe = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $content);
?>
<article class="page-content">
    <h1><?= htmlspecialchars($title) ?></h1>
    <div class="rich-text">
        <?= $contentSafe ?>
    </div>
</article>
