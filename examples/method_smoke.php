<?php declare(strict_types=1);

use PhpTuic\Http\TuicRequestClient;

require __DIR__ . '/../vendor/autoload.php';

$client = new TuicRequestClient(__DIR__ . '/node.user.yaml');

$checks = [
    'GET http' => static fn (TuicRequestClient $c) => $c->get('http://neverssl.com/'),
    'GET https' => static fn (TuicRequestClient $c) => $c->get('https://example.com/'),
    'HEAD https' => static fn (TuicRequestClient $c) => $c->head('https://example.com/'),
    'POST https' => static fn (TuicRequestClient $c) => $c->post('https://postman-echo.com/post', ['foo' => 'bar']),
    'PUT https' => static fn (TuicRequestClient $c) => $c->put('https://postman-echo.com/put', ['foo' => 'bar']),
    'PATCH https' => static fn (TuicRequestClient $c) => $c->patch('https://postman-echo.com/patch', ['foo' => 'bar']),
    'DELETE https' => static fn (TuicRequestClient $c) => $c->delete('https://postman-echo.com/delete', ['foo' => 'bar']),
    'OPTIONS https' => static fn (TuicRequestClient $c) => $c->options('https://postman-echo.com/get'),
];

try {
    foreach ($checks as $label => $runner) {
        $response = $runner($client);
        printf(
            "[OK] %-13s status=%d body=%s\n",
            $label,
            $response->statusCode,
            substr(str_replace(["\r", "\n"], ' ', $response->body), 0, 80)
        );
    }
} finally {
    $client->stop();
}
