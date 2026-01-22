<?php
declare(strict_types=1);
return [
    'name' => 'Api',
    'slug' => 'api',
    'version' => '1.0.0',
    'description' => 'API layer with bearer auth and OpenAPI generation.',
    'providers' => [
        'routes' => 'routes.php',
        'config' => 'config.php',
        'migrations' => 'migrations',
    ],
];
