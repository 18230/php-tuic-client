<?php

declare(strict_types=1);

return [
    'config' => env('TUIC_CONFIG', root_path() . 'config/tuic.yaml'),
    'node_name' => env('TUIC_NODE_NAME'),
    'http_port' => (int) env('TUIC_HTTP_PORT', 8080),
    'socks_port' => (int) env('TUIC_SOCKS_PORT', 1080),
    'proxy_mode' => env('TUIC_PROXY_MODE', 'http'),
    'php_binary' => env('TUIC_PHP_BINARY'),
    'startup_timeout' => (float) env('TUIC_STARTUP_TIMEOUT', 10.0),
];
