<?php
return [
    'name' => 'Users',
    'slug' => 'users',
    'version' => '1.0.0',
    'description' => 'User accounts, auth, profiles, avatars, and admin management.',
    'providers' => [
        'routes' => 'routes.php',
        'migrations' => 'migrations',
        'views' => 'views',
        'lang' => 'lang',
        'config' => 'config.php',
        'assets' => 'assets',
    ],
];
