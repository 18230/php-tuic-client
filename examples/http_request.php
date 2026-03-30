<?php declare(strict_types=1);

use PhpTuic\Config\NodeLoader;
use PhpTuic\Http\TuicHttpClient;
use PhpTuic\Tuic\TuicClient;

require __DIR__ . '/../vendor/autoload.php';

$node = NodeLoader::fromFile(__DIR__ . '/node.user.yaml');
$tuic = new TuicClient($node);
$http = new TuicHttpClient($tuic);

try {
    $response = $http->get('http://neverssl.com/');

    echo "Status: {$response->statusCode}\n";
    echo "Body preview:\n";
    echo substr($response->body, 0, 200), "\n";
} finally {
    $tuic->close();
}
