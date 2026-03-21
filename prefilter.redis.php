<?php
// Redis rate limiting. Requires php-redis extension and Redis unix socket.

$appRoot = __DIR__;
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$forbiddenExtensions = [
    'phar', 'pht', 'phtml', 'php', 'php5', 'php7', 'php8', 'shtml', 'cgi', 'fcgi', 'pl', 'asp',
    'aspx', 'exe', 'dll', 'so', 'bin'
];

$badPatterns = [
    '/\\b(select|insert|update|delete|drop|union|exec|declare)\\b.*\\b(from|into|table)\\b/i',
    '/(\\%27|\\%23|\\%3B|;)/',                   // SQLi punctuation
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

$ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
$window = 60;
$maxRequests = 120;

try {
    $redis = new Redis();
    $redis->connect('/run/redis/redis.sock');
    $redis->select(1);

    $key = 'prefilter:rate:' . $ip;
    $count = $redis->incr($key);
    if ($count === 1) {
        $redis->expire($key, $window);
    }

    if ($count > $maxRequests) {
        http_response_code(429);
        exit;
    }
} catch (Throwable) {
    // fail-open: Redis unavailable, allow request through
}

require __DIR__ . '/index.php';
