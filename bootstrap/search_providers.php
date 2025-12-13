<?php
use Core\Search\SearchRegistry;
use Core\Logger;

/** @var SearchRegistry $registry */
/** @var \Core\Container $container */
if (!isset($registry) || !isset($container)) {
    return;
}

$modulesPath = APP_ROOT . '/modules';
$files = glob($modulesPath . '/*/search_provider.php') ?: [];
foreach ($files as $file) {
    try {
        $provider = include $file;
        if ($provider instanceof \Core\Search\SearchProviderInterface) {
            $registry->register($provider);
        }
    } catch (\Throwable $e) {
        Logger::log('Search provider load failed for ' . $file . ': ' . $e->getMessage());
    }
}
