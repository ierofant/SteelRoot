<?php
return [
    'require_email_confirmation' => false,
    'auto_activate' => true,
    'block_ips' => [],
    'block_user_agents' => [],
    'smtp' => [
        'enabled' => false,
        'from' => 'noreply@example.com',
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'secure' => 'tls',
    ],
];
