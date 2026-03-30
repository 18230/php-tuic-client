#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Http\TuicHttpClient;
use PhpTuic\Tuic\TuicClient;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'config::',
    'node::',
    'node-name::',
    'url:',
    'method::',
    'header::',
    'data::',
    'follow-redirects',
    'json',
    'verbose',
]);

if ((!isset($options['config']) && !isset($options['node'])) || !isset($options['url'])) {
    fwrite(STDERR, <<<TXT
Usage:
  php bin/tuic-http-request.php --config=/path/to/node.yaml --url=http://example.com/
  php bin/tuic-http-request.php --node="{ ... }" --url=http://example.com/

Options:
  --node=YAML_OR_JSON      Inline TUIC node config in YAML or JSON.
  --node-name=NAME         Optional node name when config contains multiple proxies.
  --method=GET             HTTP method, default GET.
  --header="Key: Value"    Repeatable header.
  --data="a=1&b=2"         Request body for POST/PUT/PATCH.
  --follow-redirects       Follow HTTP redirects.
  --json                   Pretty-print JSON response bodies.
  --verbose                Print response status and headers to stderr.

TXT);
    exit(1);
}

$headers = [];
$rawHeaders = $options['header'] ?? [];
if (!is_array($rawHeaders)) {
    $rawHeaders = [$rawHeaders];
}

foreach ($rawHeaders as $line) {
    $line = (string) $line;
    if (!str_contains($line, ':')) {
        fwrite(STDERR, "Invalid header format: {$line}\n");
        exit(1);
    }

    [$name, $value] = explode(':', $line, 2);
    $headers[trim($name)] = ltrim($value);
}

$method = strtoupper((string) ($options['method'] ?? (isset($options['data']) ? 'POST' : 'GET')));
$requestOptions = [
    'headers' => $headers,
    'follow_redirects' => array_key_exists('follow-redirects', $options),
];

if (isset($options['data'])) {
    $requestOptions['body'] = (string) $options['data'];
}

try {
    $node = (new NodeInputResolver())->resolve(
        inlineNode: isset($options['node']) ? (string) $options['node'] : null,
        configPath: isset($options['config']) ? (string) $options['config'] : null,
        nodeName: isset($options['node-name']) ? (string) $options['node-name'] : null,
    );
    $tuic = new TuicClient($node);
    $http = new TuicHttpClient($tuic);

    $response = $http->request($method, (string) $options['url'], $requestOptions);

    if (array_key_exists('verbose', $options)) {
        fwrite(STDERR, sprintf("HTTP/%s %d %s\n", $response->protocolVersion, $response->statusCode, $response->reasonPhrase));
        foreach ($response->headers as $name => $values) {
            foreach ($values as $value) {
                fwrite(STDERR, "{$name}: {$value}\n");
            }
        }
        fwrite(STDERR, PHP_EOL);
    }

    if (array_key_exists('json', $options)) {
        $decoded = json_decode($response->body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            fwrite(STDOUT, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        } else {
            fwrite(STDOUT, $response->body);
        }
    } else {
        fwrite(STDOUT, $response->body);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (isset($tuic)) {
        $tuic->close();
    }
}
