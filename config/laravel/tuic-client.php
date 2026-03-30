<?php

declare(strict_types=1);

return [
    'config' => env('TUIC_CONFIG', base_path('config/tuic.yaml')),
    'node_name' => env('TUIC_NODE_NAME'),
    'socks_port' => (int) env('TUIC_SOCKS_PORT', 1080),
    'proxy_mode' => env('TUIC_PROXY_MODE', 'socks5h'),
    'php_binary' => env('TUIC_PHP_BINARY'),
    'startup_timeout' => (float) env('TUIC_STARTUP_TIMEOUT', 10.0),
];
