<?php
$appRoot = dirname(__DIR__);
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$forbiddenExtensions = [
    'phar', 'pht', 'phtml', 'php', 'php5', 'php7', 'php8', 'shtml', 'cgi', 'fcgi', 'pl', 'asp',
    'aspx', 'exe', 'dll', 'so', 'bin'
];

$badPatterns = [
    '/\\b(select|insert|update|delete|drop|union|exec|declare)\\b.*\\b(from|into|table)\\b/i',
    '/(\\%27|\\-|\\%23|\\%3B|;)/',                   // SQLi punctuation
    '/(\\.{2}\\/?)+/',                              // traversal
    '/\\$\\{[^}]*\\}/',                             // OGNL style
    '/\\b(?:or|and)\\b\\s+\\d=\\d/i'
];

$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (in_array($extension, $forbiddenExtensions, true)) {
    http_response_code(403);
    exit;
}

if (preg_match('/\\.php(\\/|$)/i', $path)) {
    http_response_code(444);
    exit;
}

foreach ($badPatterns as $pattern) {
    if (preg_match($pattern, $uri)) {
        http_response_code(403);
        exit;
    }
}

$rateFile = $appRoot . '/public_html/storage/tmp/prefilter_rate.json';
if (!is_dir(dirname($rateFile))) {
    mkdir(dirname($rateFile), 0775, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
$window = 60;
$maxRequests = 120;
$now = time();
$rates = [];

if (file_exists($rateFile)) {
    $json = file_get_contents($rateFile);
    $rates = $json ? json_decode($json, true) : [];
}

$entry = $rates[$ip] ?? ['time' => $now, 'count' => 0];
if ($now - $entry['time'] > $window) {
    $entry = ['time' => $now, 'count' => 0];
}
$entry['count']++;
$rates[$ip] = $entry;
file_put_contents($rateFile, json_encode($rates, JSON_PRETTY_PRINT));

if ($entry['count'] > $maxRequests) {
    http_response_code(444);
    exit;
}

require __DIR__ . '/index.php';
