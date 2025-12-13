<?php
return [
    'name' => 'FAQ',
    'slug' => 'faq',
    'version' => '1.0.0',
    'description' => 'FAQ module',
    'providers' => [
        'routes' => 'routes.php',
        'migrations' => 'migrations',
        'views' => 'views',
        'lang' => 'lang',
        'config' => 'config.php',
        'assets' => 'assets',
    ],
    'events' => [
        'article.saved' => 'Modules\\FAQ\\Listeners\\OnArticleSaved@handle',
    ],
    'permissions' => [
        'faq.view',
        'faq.edit',
        'faq.delete',
    ],
];
