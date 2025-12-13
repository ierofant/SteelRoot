<?php ob_start(); ?>
<section class="tags-hero">
    <div>
        <p class="eyebrow">Навигация</p>
        <h1>Все теги</h1>
        <p class="muted">Каталог тематик, меток и коллекций.</p>
    </div>
</section>

<section class="tags-grid">
    <?php foreach ($tags as $tag): ?>
        <?php $name = $tag['name'] ?? $tag['slug']; ?>
        <a class="tag-chip" href="/tags/<?= urlencode($tag['slug']) ?>">
            <span class="tag-bullet"></span>
            <span class="tag-name"><?= htmlspecialchars($name) ?></span>
            <span class="tag-arrow">→</span>
        </a>
    <?php endforeach; ?>
    <?php if (empty($tags)): ?>
        <p class="muted">Теги отсутствуют.</p>
    <?php endif; ?>
</section>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
