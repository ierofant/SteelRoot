<?php
$base = rtrim((include __DIR__ . '/../../app/config/app.php')['url'] ?? 'http://localhost', '/');
return [
    $base . '/admin',
];
