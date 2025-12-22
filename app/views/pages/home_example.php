<?php
// Example page template rendered via Renderer with _layout = true
$meta = $meta ?? [];
$this->setSection('sidebar', '<div class="card">Sidebar slot example</div>');
?>
<section class="hero">
    <h1><?= htmlspecialchars($title ?? 'Example Home') ?></h1>
    <p class="muted"><?= htmlspecialchars($intro ?? 'Minimal template using explicit layout and sections.') ?></p>
</section>
<section class="grid two">
    <div class="card stack">
        <h2><?= htmlspecialchars($blockTitle ?? 'Content block') ?></h2>
        <p><?= htmlspecialchars($blockText ?? 'Pass data explicitly via Renderer::render($view, $data, $meta).') ?></p>
    </div>
    <div class="card stack">
        <h3>Meta in use</h3>
        <p class="muted">Title: <?= htmlspecialchars($meta['title'] ?? '') ?></p>
        <p class="muted">Description: <?= htmlspecialchars($meta['description'] ?? '') ?></p>
    </div>
</section>
