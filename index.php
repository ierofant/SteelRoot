<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
}

if (!is_dir(APP_ROOT . '/storage/tmp/sessions')) {
    @mkdir(APP_ROOT . '/storage/tmp/sessions', 0775, true);
}
session_save_path(APP_ROOT . '/storage/tmp/sessions');

spl_autoload_register(function (string $class): void {
    $map = [
        'Core\\' => APP_ROOT . '/core/',
        'App\\' => APP_ROOT . '/app/',
        'Modules\\' => APP_ROOT . '/modules/',
        'Database\\' => APP_ROOT . '/database/',
    ];
    foreach ($map as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $rel = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $rel) . '.php';
            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
if (!class_exists(\Core\Database::class)) {
    require APP_ROOT . '/core/Database.php';
}

use Core\Kernel;
use Core\Request;

session_start();

$kernel = new Kernel(APP_ROOT);
$request = Request::fromGlobals();
$response = $kernel->handle($request);
$response->send();
