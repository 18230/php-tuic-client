#!/usr/bin/env php
<?php declare(strict_types=1);

use PhpTuic\Config\NodeInputResolver;
use PhpTuic\Tuic\TuicClient;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', [
    'config::',
    'node::',
    'node-name::',
    'target-host:',
    'target-port:',
    'payload::',
    'payload-file::',
    'http-path::',
    'http-host-header::',
    'verbose',
]);

if ((!isset($options['config']) && !isset($options['node'])) || !isset($options['target-host'], $options['target-port'])) {
    fwrite(STDERR, <<<TXT
Usage:
  php bin/tuic-tcp-request.php --config=/path/to/node.yaml --target-host=example.com --target-port=80 [options]
  php bin/tuic-tcp-request.php --node="{ ... }" --target-host=example.com --target-port=80 [options]

Options:
  --node=YAML_OR_JSON         Inline TUIC node config in YAML or JSON.
  --node-name=NAME            Optional node name when config contains multiple proxies.
  --payload="RAW"             Raw payload to send after the TUIC Connect header.
  --payload-file=/path        Read payload bytes from a file.
  --http-path=/               Build a simple HTTP/1.1 GET request instead of supplying a raw payload.
  --http-host-header=host     Override Host header used with --http-path.
  --verbose                   Print TLS and QUIC diagnostics to stderr.

TXT);
    exit(1);
}

$targetHost = (string) $options['target-host'];
$targetPort = (int) $options['target-port'];
$verbose = array_key_exists('verbose', $options);

$payload = '';
if (isset($options['payload']) && isset($options['payload-file'])) {
    fwrite(STDERR, "Use either --payload or --payload-file, not both.\n");
    exit(1);
}

if (isset($options['payload'])) {
    $payload = (string) $options['payload'];
} elseif (isset($options['payload-file'])) {
    $payload = file_get_contents((string) $options['payload-file']);
    if ($payload === false) {
        fwrite(STDERR, "Unable to read payload file.\n");
        exit(1);
    }
} else {
    $path = isset($options['http-path']) ? (string) $options['http-path'] : '/';
    $hostHeader = isset($options['http-host-header']) ? (string) $options['http-host-header'] : $targetHost;
    $payload = "GET {$path} HTTP/1.1\r\n"
        . "Host: {$hostHeader}\r\n"
        . "User-Agent: php-tuic-client/0.2\r\n"
        . "Accept: */*\r\n"
        . "Connection: close\r\n\r\n";
}

$node = (new NodeInputResolver())->resolve(
    inlineNode: isset($options['node']) ? (string) $options['node'] : null,
    configPath: isset($options['config']) ? (string) $options['config'] : null,
    nodeName: isset($options['node-name']) ? (string) $options['node-name'] : null,
);
$client = new TuicClient($node);

try {
    $client->connect();

    if ($verbose) {
        $tlsInfo = $client->getTlsInfo();
        if ($tlsInfo !== null) {
            fwrite(STDERR, 'TLS protocol: ' . $tlsInfo->getVersion() . PHP_EOL);
            fwrite(STDERR, 'TLS ALPN: ' . ($tlsInfo->getApplicationLayerProtocol() ?? '<none>') . PHP_EOL);
            fwrite(STDERR, 'TLS cipher: ' . $tlsInfo->getCipherName() . PHP_EOL);
        }
    }

    $response = $client->tcpRequest($targetHost, $targetPort, $payload);
    if ($verbose && $response === '') {
        fwrite(STDERR, "No response bytes received." . PHP_EOL);
        fwrite(STDERR, 'Connection closed: ' . ($client->isClosed() ? 'yes' : 'no') . PHP_EOL);
        $closeReason = $client->getCloseReason();
        if ($closeReason !== null) {
            fwrite(
                STDERR,
                sprintf(
                    "Close reason: code=%d transport=%s message=%s\n",
                    $closeReason->code,
                    $closeReason->quicError?->name ?? 'application',
                    $closeReason->reason,
                ),
            );
        }
    }
    fwrite(STDOUT, $response);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $client->close();
}
