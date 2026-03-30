<?php declare(strict_types=1);

use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/../vendor/autoload.php';

$client = new TuicRequestClient(__DIR__ . '/node.user.yaml');

try {
    $response = $client->get('https://example.com/');
    echo "Status: {$response->statusCode}\n";
    echo "Server: " . ($response->header('server') ?? '<unknown>') . "\n";
    echo substr($response->body, 0, 120) . "\n";
} finally {
    $client->stop();
}
